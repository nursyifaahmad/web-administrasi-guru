<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DailyJournal;
use App\Models\TeachingClass;
use App\Services\AttendanceJournalSyncService;
use App\Services\SchoolScheduleService;
use App\Support\IndonesianDate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyJournalController extends Controller
{
    public function index(Request $request, TeachingClass $teachingClass, SchoolScheduleService $scheduleService): View
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

        $todaySchedule = $scheduleService->getLessonHoursForClassDate(
            $teachingClass,
            now()->toDateString(),
            $request->user()
        );

        return view('workspace.journals.index', [
            'teachingClass' => $teachingClass,
            'journals' => $query->get(),
            'studentsCount' => $teachingClass->activeStudents()->count(),
            'filterMonth' => $filterMonth,
            'filterYear' => $filterYear,
            'search' => $search,
            'todaySchedule' => $todaySchedule,
        ]);
    }

    public function attendancePreview(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $syncService,
        SchoolScheduleService $scheduleService
    ): JsonResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        $date = $request->string('date')->trim()->toString() ?: now()->toDateString();
        $summary = $syncService->summarizeForDate($teachingClass, $date);
        $summary['schedule'] = $scheduleService->getLessonHoursForClassDate(
            $teachingClass,
            $date,
            $request->user()
        );

        return response()->json($summary);
    }

    public function absentStudents(
        Request $request,
        TeachingClass $teachingClass
    ): JsonResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        $rawDate = $request->string('date')->trim()->toString();
        if (! $rawDate) {
            return response()->json(['error' => 'Tanggal tidak boleh kosong.'], 422);
        }

        try {
            $normalizedDate = Carbon::parse($rawDate)->toDateString();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Format tanggal tidak valid.'], 422);
        }

        $activeStudentIds = $teachingClass->activeStudents()->pluck('id');

        $attendances = Attendance::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('attendance_date', $normalizedDate)
            ->whereIn('class_student_id', $activeStudentIds)
            ->whereIn('status', ['s', 'i', 'a', 'd'])
            ->with('student')
            ->get();

        $statusLabels = [
            's' => ['label' => 'Sakit', 'color' => 'amber', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'],
            'i' => ['label' => 'Izin', 'color' => 'blue', 'badge' => 'bg-blue-100 text-blue-800 border-blue-200'],
            'a' => ['label' => 'Alpa (Tanpa Keterangan)', 'color' => 'rose', 'badge' => 'bg-rose-100 text-rose-800 border-rose-200'],
            'd' => ['label' => 'Dispensasi', 'color' => 'purple', 'badge' => 'bg-purple-100 text-purple-800 border-purple-200'],
        ];

        $students = $attendances->map(function (Attendance $att) use ($statusLabels) {
            $st = strtolower(trim((string) $att->status));
            $meta = $statusLabels[$st] ?? ['label' => strtoupper($st), 'color' => 'slate', 'badge' => 'bg-slate-100 text-slate-800 border-slate-200'];

            return [
                'id' => $att->student?->id,
                'order_number' => $att->student?->order_number ?? '-',
                'nis_nisn' => $att->student?->nis_nisn ?: '-',
                'full_name' => $att->student?->full_name ?? 'Murid Tidak Ditemukan',
                'gender' => $att->student?->gender ?? '-',
                'status_code' => strtoupper($st),
                'status_label' => $meta['label'],
                'badge_class' => $meta['badge'],
            ];
        })->sortBy('order_number')->values();

        $journal = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('journal_date', $normalizedDate)
            ->first();

        $carbonDate = Carbon::parse($normalizedDate);
        $month = $carbonDate->month;

        return response()->json([
            'date' => $normalizedDate,
            'day_name' => IndonesianDate::dayName($normalizedDate),
            'formatted_date' => IndonesianDate::formatLong($normalizedDate),
            'class_name' => $teachingClass->class_name,
            'subject_name' => $teachingClass->subject?->name,
            'total_absent' => $students->whereIn('status_code', ['S', 'I', 'A'])->count(),
            'total_dispen' => $students->where('status_code', 'D')->count(),
            'sakit_count' => $students->where('status_code', 'S')->count(),
            'izin_count' => $students->where('status_code', 'I')->count(),
            'alfa_count' => $students->where('status_code', 'A')->count(),
            'students' => $students,
            'journal_absent_count' => $journal?->absent_count,
            'is_overridden' => (bool) ($journal?->is_attendance_overridden ?? false),
            'attendance_url' => route('attendances.index', ['teachingClass' => $teachingClass->id, 'month' => $month]),
        ]);
    }

    public function store(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $syncService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek hanya memiliki izin untuk melihat (monitoring) jurnal harian guru lain, tidak dapat menambah catatan KBM guru lain.'
        );

        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'lesson_hours' => ['required', 'string', 'max:50'],
            'activity_material' => ['required', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_mode' => ['nullable', 'string'], // profile, canvas, empty
            'signature_data' => ['nullable', 'string'],
        ]);

        $summary = $syncService->summarizeForDate($teachingClass, $validated['journal_date']);
        $present = $summary['present_count'];
        $absent = $summary['absent_count'];
        $breakdown = $summary['breakdown'];

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
            'is_attendance_overridden' => false,
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

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek hanya memiliki izin untuk melihat (monitoring) jurnal harian guru lain, tidak dapat mengubah catatan KBM guru lain.'
        );

        abort_unless((int) $journal->teaching_class_id === (int) $teachingClass->id, 404);

        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'lesson_hours' => ['required', 'string', 'max:50'],
            'activity_material' => ['required', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_mode' => ['nullable', 'string'], // keep, profile, canvas, empty
            'signature_data' => ['nullable', 'string'],
        ]);

        $summary = $syncService->summarizeForDate($teachingClass, $validated['journal_date']);
        $present = $summary['present_count'];
        $absent = $summary['absent_count'];
        $breakdown = $summary['breakdown'];

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
            'is_attendance_overridden' => false,
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

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek hanya memiliki izin untuk melihat (monitoring) jurnal harian guru lain.'
        );

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

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek hanya memiliki izin untuk melihat (monitoring) jurnal harian guru lain, tidak dapat menghapus catatan KBM guru lain.'
        );

        abort_unless((int) $journal->teaching_class_id === (int) $teachingClass->id, 404);

        $journal->delete();

        return back()->with('success', 'Entri Jurnal Harian berhasil dihapus.');
    }
}
