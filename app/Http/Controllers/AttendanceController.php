<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TeachingClass;
use App\Services\AttendanceJournalSyncService;
use App\Services\ExcelExportImportService;
use App\Services\GradeCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $attendanceService
    ): View {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $teachingClass->load(['subject.kktps', 'teacher', 'academicYear']);

        $tab = $request->string('tab')->toString() === 'recap' ? 'recap' : 'monthly';
        $month = max(1, min(12, $request->integer('month') ?: (int) now()->month));
        $year = max(2020, min(2099, $request->integer('year') ?: (int) now()->year));

        $matrix = $attendanceService->buildMonthlyMatrix($teachingClass, $year, $month);
        $recap = $tab === 'recap' ? $attendanceService->buildSemesterRecap($teachingClass) : null;

        // Data JSON bersih untuk grid interaktif Alpine.js
        $alpineStudents = [];
        $alpineCells = [];
        foreach ($matrix['rows'] as $r) {
            $st = $r['student'];
            $alpineStudents[] = [
                'id' => $st->id,
                'no' => $r['no'],
                'full_name' => $st->full_name,
                'nickname' => $st->nickname ?: '-',
                'gender' => $st->gender,
            ];
            $alpineCells[(string) $st->id] = $r['days'];
        }

        return view('workspace.attendances.index', [
            'teachingClass' => $teachingClass,
            'tab' => $tab,
            'month' => $month,
            'year' => $year,
            'matrix' => $matrix,
            'recap' => $recap,
            'alpineStudents' => $alpineStudents,
            'alpineCells' => $alpineCells,
            'alpineDaysMeta' => $matrix['days_meta'],
        ]);
    }

    public function bulkSave(
        Request $request,
        TeachingClass $teachingClass,
        AttendanceJournalSyncService $syncService,
        GradeCalculationService $gradeService
    ): JsonResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_if(
            $request->user()->isAdmin(),
            403,
            'Akses ditolak. Administrator/Wakasek hanya memiliki izin untuk melihat (monitoring) absensi, tidak dapat mengubah data absensi guru.'
        );

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'cells' => ['required', 'array'],
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $validStudentIds = $teachingClass->activeStudents()->pluck('id')->flip();

        $upsertRows = [];
        $deletePairs = [];
        $touchedDays = [];
        $now = now();

        foreach ($validated['cells'] as $studentId => $daysMap) {
            $sid = (int) $studentId;
            if (! $validStudentIds->has($sid) || ! is_array($daysMap)) {
                continue;
            }

            foreach ($daysMap as $day => $rawStatus) {
                $d = (int) $day;
                if ($d < 1 || $d > $daysInMonth) {
                    continue;
                }

                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $st = strtolower(trim((string) $rawStatus));

                if ($st === '') {
                    $deletePairs[$dateStr][] = $sid;
                    $touchedDays[$dateStr] = true;
                } elseif (in_array($st, Attendance::VALID_STATUSES, true)) {
                    $upsertRows[] = [
                        'teaching_class_id' => $teachingClass->id,
                        'class_student_id' => $sid,
                        'attendance_date' => $dateStr,
                        'status' => $st,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $touchedDays[$dateStr] = true;
                } else {
                    return response()->json([
                        'message' => "Kode absensi '{$rawStatus}' tidak valid. Gunakan hanya '.', 's', 'i', 'a', atau 'd'.",
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($teachingClass, $upsertRows, $deletePairs) {
            foreach ($deletePairs as $dateStr => $sids) {
                Attendance::query()
                    ->where('teaching_class_id', $teachingClass->id)
                    ->whereDate('attendance_date', $dateStr)
                    ->whereIn('class_student_id', $sids)
                    ->delete();
            }

            if (! empty($upsertRows)) {
                foreach (array_chunk($upsertRows, 500) as $chunk) {
                    Attendance::query()->upsert(
                        $chunk,
                        ['teaching_class_id', 'class_student_id', 'attendance_date'],
                        ['status', 'updated_at']
                    );
                }
            }
        });

        // Sinkronkan seluruh tanggal di bulan tersebut yang tersentuh ke Jurnal Harian!
        foreach (array_keys($touchedDays) as $dateStr) {
            $syncService->syncJournalForDate($teachingClass, $dateStr);
        }

        // Jika kelas menggunakan Nilai Kehadiran Otomatis, sinkronkan Nilai Kehadiran & NA!
        $weight = $teachingClass->gradeWeight()->first();
        $autoSyncedGrades = false;
        if ($weight && $weight->isAutoAttendance()) {
            $gradeService->recalculateClass($teachingClass, $syncService);
            $autoSyncedGrades = true;
        }

        return response()->json([
            'status' => 'ok',
            'message' => $autoSyncedGrades
                ? 'Data Absensi berhasil disimpan & disinkronkan otomatis ke Jurnal Harian serta Nilai Kehadiran.'
                : 'Data Absensi berhasil disimpan & disinkronkan otomatis ke Jurnal Harian.',
            'saved_at' => now()->format('H:i:s'),
        ]);
    }
}
