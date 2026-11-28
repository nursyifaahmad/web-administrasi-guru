<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DailyJournal;
use App\Models\TeachingClass;
use App\Services\AttendanceJournalSyncService;
use App\Services\SchoolScheduleService;
use App\Support\IndonesianDate;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $existingJournalsByDate = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->orderBy('journal_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (DailyJournal $j) => [
                $j->journal_date->toDateString() => $this->formatJournalPayload($teachingClass, $j),
            ])
            ->all();

        return view('workspace.journals.index', [
            'teachingClass' => $teachingClass,
            'journals' => $query->get(),
            'existingJournalsByDate' => $existingJournalsByDate,
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

        $rawDate = $request->string('date')->trim()->toString() ?: now()->toDateString();
        try {
            $date = Carbon::parse($rawDate)->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $summary = $syncService->summarizeForDate($teachingClass, $date);
        $summary['schedule'] = $scheduleService->getLessonHoursForClassDate(
            $teachingClass,
            $date,
            $request->user()
        );

        $existingJournal = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('journal_date', $date)
            ->first();
        $summary['existing_journal'] = $existingJournal
            ? $this->formatJournalPayload($teachingClass, $existingJournal)
            : null;

        return response()->json($summary);
    }

    public function absentStudents(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $syncService
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

        return response()->json($syncService->getAbsentDispenDetailForDate($teachingClass, $normalizedDate));
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

        $validated = $this->validateJournalPayload($request);
        $normalizedDate = Carbon::parse($validated['journal_date'])->toDateString();

        $this->assertUniqueJournalDate($teachingClass, $normalizedDate);

        $summary = $syncService->summarizeForDate($teachingClass, $normalizedDate);
        $signature = $this->resolveJournalSignature($request, $teachingClass, 'profile');

        $this->saveWithUniqueDateGuard(function () use ($teachingClass, $normalizedDate, $validated, $summary, $signature) {
            DailyJournal::query()->create([
                'teaching_class_id' => $teachingClass->id,
                'journal_date' => $normalizedDate,
                'lesson_hours' => trim($validated['lesson_hours']),
                'activity_material' => trim($validated['activity_material']),
                'present_count' => $summary['present_count'],
                'absent_count' => $summary['absent_count'],
                'attendance_breakdown' => $summary['breakdown'],
                'is_attendance_overridden' => false,
                'notes' => $validated['notes'] ?? null,
                'signature_data' => $signature,
            ]);
        }, $normalizedDate);

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

        $validated = $this->validateJournalPayload($request);
        $normalizedDate = Carbon::parse($validated['journal_date'])->toDateString();

        $this->assertUniqueJournalDate($teachingClass, $normalizedDate, $journal->id);

        $summary = $syncService->summarizeForDate($teachingClass, $normalizedDate);
        $signature = $this->resolveJournalSignature($request, $teachingClass, 'keep', $journal->signature_data);

        $this->saveWithUniqueDateGuard(function () use ($journal, $normalizedDate, $validated, $summary, $signature) {
            $journal->update([
                'journal_date' => $normalizedDate,
                'lesson_hours' => trim($validated['lesson_hours']),
                'activity_material' => trim($validated['activity_material']),
                'present_count' => $summary['present_count'],
                'absent_count' => $summary['absent_count'],
                'attendance_breakdown' => $summary['breakdown'],
                'is_attendance_overridden' => false,
                'notes' => $validated['notes'] ?? null,
                'signature_data' => $signature,
            ]);
        }, $normalizedDate);

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

    /**
     * Format ringkasan satu entri jurnal untuk kebutuhan UI modal dan pengecekan duplikat tanggal.
     *
     * @return array<string, mixed>
     */
    protected function formatJournalPayload(TeachingClass $teachingClass, DailyJournal $journal): array
    {
        return [
            'id' => $journal->id,
            'journal_date' => $journal->journal_date->toDateString(),
            'formatted_date' => IndonesianDate::formatLong($journal->journal_date),
            'lesson_hours' => $journal->lesson_hours,
            'activity_material' => $journal->activity_material,
            'present_count' => $journal->present_count,
            'absent_count' => $journal->absent_count,
            'is_attendance_overridden' => (bool) $journal->is_attendance_overridden,
            'notes' => $journal->notes,
            'has_signature' => ! empty($journal->signature_data),
            'update_url' => route('journals.update', [$teachingClass, $journal]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateJournalPayload(Request $request): array
    {
        return $request->validate([
            'journal_date' => ['required', 'date'],
            'lesson_hours' => ['required', 'string', 'max:50'],
            'activity_material' => ['required', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_mode' => ['nullable', 'string'], // keep, profile, canvas, empty
            'signature_data' => ['nullable', 'string'],
        ]);
    }

    protected function assertUniqueJournalDate(
        TeachingClass $teachingClass,
        string $normalizedDate,
        ?int $ignoreJournalId = null
    ): void {
        $exists = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('journal_date', $normalizedDate)
            ->when($ignoreJournalId !== null, fn ($q) => $q->where('id', '!=', $ignoreJournalId))
            ->exists();

        if ($exists) {
            $formattedDate = IndonesianDate::formatLong($normalizedDate);
            throw ValidationException::withMessages([
                'journal_date' => "Jurnal harian untuk tanggal {$formattedDate} sudah ada di kelas ini. Setiap hari maksimal 1 jurnal — silakan klik Edit pada jurnal tanggal tersebut.",
            ]);
        }
    }

    protected function resolveJournalSignature(
        Request $request,
        TeachingClass $teachingClass,
        string $defaultMode,
        ?string $currentSignature = null
    ): ?string {
        $mode = $request->input('signature_mode') ?: $defaultMode;

        if ($mode === 'empty') {
            return null;
        }
        if ($mode === 'keep') {
            return $currentSignature;
        }
        if ($mode === 'canvas') {
            $raw = $request->input('signature_data');
            return ! empty($raw) ? $this->sanitizeRasterImageDataUri($raw, 'signature_data') : null;
        }

        return $teachingClass->teacher?->signature_data ?? $request->user()->signature_data;
    }

    protected function saveWithUniqueDateGuard(callable $persist, string $normalizedDate): void
    {
        try {
            $persist();
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if ($e instanceof QueryException && ! $e instanceof UniqueConstraintViolationException) {
                $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
                if ($sqlState !== '23000' && $sqlState !== '23505') {
                    throw $e;
                }
            }

            $formattedDate = IndonesianDate::formatLong($normalizedDate);
            throw ValidationException::withMessages([
                'journal_date' => "Jurnal harian untuk tanggal {$formattedDate} sudah ada di kelas ini. Setiap hari maksimal 1 jurnal — silakan klik Edit pada jurnal tanggal tersebut.",
            ]);
        }
    }
}
