<?php

namespace App\Http\Controllers;

use App\Models\DailyJournal;
use App\Models\TeachingClass;
use App\Services\AttendanceJournalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyJournalController extends Controller
{
    public function index(Request $request, TeachingClass $teachingClass): View
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $teachingClass->load(['subject.kktps', 'teacher', 'academicYear']);

        $filterMonth = $request->integer('month') ?: null;
        $filterYear = $request->integer('year') ?: (int) now()->year;
        $search = $request->string('q')->trim()->toString();

        $query = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->orderBy('journal_date')
            ->orderBy('lesson_hours');

        if ($filterMonth) {
            $query->whereYear('journal_date', $filterYear)->whereMonth('journal_date', $filterMonth);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('activity_material', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('lesson_hours', 'like', "%{$search}%");
            });
        }

        return view('workspace.journals.index', [
            'teachingClass' => $teachingClass,
            'journals' => $query->get(),
            'studentsCount' => $teachingClass->activeStudents()->count(),
            'filterMonth' => $filterMonth,
            'filterYear' => $filterYear,
            'search' => $search,
        ]);
    }

    public function attendancePreview(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $syncService
    ): JsonResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        $date = $request->string('date')->trim()->toString() ?: now()->toDateString();
        $summary = $syncService->summarizeForDate($teachingClass, $date);

        return response()->json($summary);
    }

    public function store(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $syncService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'lesson_hours' => ['required', 'string', 'max:50'],
            'activity_material' => ['required', 'string', 'max:3000'],
            'present_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'absent_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'is_attendance_overridden' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_mode' => ['nullable', 'string'], // profile, canvas, empty
            'signature_data' => ['nullable', 'string'],
        ]);

        $summary = $syncService->summarizeForDate($teachingClass, $validated['journal_date']);
        $manualOverride = $request->boolean('is_attendance_overridden');

        if (! $manualOverride && $summary['total_recorded'] > 0) {
            $present = $summary['present_count'];
            $absent = $summary['absent_count'];
            $breakdown = $summary['breakdown'];
        } else {
            $present = (int) ($validated['present_count'] ?? $summary['present_count']);
            $absent = (int) ($validated['absent_count'] ?? $summary['absent_count']);
            $breakdown = $manualOverride ? 'Input Manual' : $summary['breakdown'];
        }

        $signature = null;
        $mode = $validated['signature_mode'] ?? 'profile';
        if ($mode === 'canvas' && ! empty($validated['signature_data']) && str_starts_with($validated['signature_data'], 'data:image/')) {
            $signature = $validated['signature_data'];
        } elseif ($mode === 'profile') {
            $signature = $teachingClass->teacher?->signature_data ?? $request->user()->signature_data;
        }

        DailyJournal::query()->create([
            'teaching_class_id' => $teachingClass->id,
            'journal_date' => $validated['journal_date'],
            'lesson_hours' => trim($validated['lesson_hours']),
            'activity_material' => trim($validated['activity_material']),
            'present_count' => $present,
            'absent_count' => $absent,
            'attendance_breakdown' => $breakdown,
            'is_attendance_overridden' => $manualOverride,
            'notes' => $validated['notes'] ?? null,
            'signature_data' => $signature,
        ]);

        return back()->with('success', 'Entri Jurnal Harian berhasil disimpan.');
    }

    public function update(
        Request $request,
        TeachingClass $teachingClass,
        DailyJournal $journal,
        AttendanceJournalSyncService $syncService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        abort_unless((int) $journal->teaching_class_id === (int) $teachingClass->id, 404);

        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'lesson_hours' => ['required', 'string', 'max:50'],
            'activity_material' => ['required', 'string', 'max:3000'],
            'present_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'absent_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'is_attendance_overridden' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_mode' => ['nullable', 'string'], // keep, profile, canvas, empty
            'signature_data' => ['nullable', 'string'],
        ]);

        $summary = $syncService->summarizeForDate($teachingClass, $validated['journal_date']);
        $manualOverride = $request->boolean('is_attendance_overridden');

        if (! $manualOverride && $summary['total_recorded'] > 0) {
            $present = $summary['present_count'];
            $absent = $summary['absent_count'];
            $breakdown = $summary['breakdown'];
        } else {
            $present = (int) ($validated['present_count'] ?? $journal->present_count);
            $absent = (int) ($validated['absent_count'] ?? $journal->absent_count);
            $breakdown = $manualOverride ? 'Input Manual' : $summary['breakdown'];
        }

        $signature = $journal->signature_data;
        $mode = $validated['signature_mode'] ?? 'keep';
        if ($mode === 'empty') {
            $signature = null;
        } elseif ($mode === 'profile') {
            $signature = $teachingClass->teacher?->signature_data ?? $request->user()->signature_data;
        } elseif ($mode === 'canvas' && ! empty($validated['signature_data']) && str_starts_with($validated['signature_data'], 'data:image/')) {
            $signature = $validated['signature_data'];
        }

        $journal->update([
            'journal_date' => $validated['journal_date'],
            'lesson_hours' => trim($validated['lesson_hours']),
            'activity_material' => trim($validated['activity_material']),
            'present_count' => $present,
            'absent_count' => $absent,
            'attendance_breakdown' => $breakdown,
            'is_attendance_overridden' => $manualOverride,
            'notes' => $validated['notes'] ?? null,
            'signature_data' => $signature,
        ]);

        return back()->with('success', 'Data Jurnal Harian berhasil diperbarui.');
    }

    public function resyncAttendance(
        Request $request,
        TeachingClass $teachingClass,
        DailyJournal $journal,
        AttendanceJournalSyncService $syncService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        abort_unless((int) $journal->teaching_class_id === (int) $teachingClass->id, 404);

        $summary = $syncService->syncJournalForDate($teachingClass, $journal->journal_date->toDateString(), true);

        return back()->with(
            'success',
            "Sinkronisasi selesai: Hadir {$summary['present_count']} murid, Tidak Hadir {$summary['absent_count']} murid."
        );
    }

    public function destroy(Request $request, TeachingClass $teachingClass, DailyJournal $journal): RedirectResponse
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        abort_unless((int) $journal->teaching_class_id === (int) $teachingClass->id, 404);

        $journal->delete();

        return back()->with('success', 'Entri Jurnal Harian berhasil dihapus.');
    }
}
