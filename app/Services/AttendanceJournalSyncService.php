<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyJournal;
use App\Models\SchoolEvent;
use App\Models\TeachingClass;
use App\Support\IndonesianDate;
use Carbon\Carbon;

class AttendanceJournalSyncService
{
    public const NON_REGULAR_STATUS_META = [
        's' => ['label' => 'Sakit', 'color' => 'amber', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'],
        'i' => ['label' => 'Izin', 'color' => 'blue', 'badge' => 'bg-blue-100 text-blue-800 border-blue-200'],
        'a' => ['label' => 'Alpa (Tanpa Keterangan)', 'color' => 'rose', 'badge' => 'bg-rose-100 text-rose-800 border-rose-200'],
        'd' => ['label' => 'Dispensasi', 'color' => 'purple', 'badge' => 'bg-purple-100 text-purple-800 border-purple-200'],
    ];

    /**
     * Hitung ringkasan kehadiran pada suatu tanggal untuk kelas tertentu.
     * Aturan: '.' (Hadir) dan 'd' (Dispen) keduanya dihitung ke dalam Jumlah Hadir.
     * Ketidakhadiran terdiri dari 's' (Sakit), 'i' (Izin), dan 'a' (Alfa).
     */
    public function summarizeForDate(TeachingClass $teachingClass, string $date): array
    {
        $normalizedDate = Carbon::parse($date)->toDateString();

        $activeStudentIds = $teachingClass->activeStudents()->pluck('id');

        $records = Attendance::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('attendance_date', $normalizedDate)
            ->whereIn('class_student_id', $activeStudentIds)
            ->pluck('status');

        $dotCount = 0;
        $dispenCount = 0;
        $sakitCount = 0;
        $izinCount = 0;
        $alfaCount = 0;

        foreach ($records as $rawStatus) {
            $st = strtolower(trim((string) $rawStatus));
            if ($st === '.') {
                $dotCount++;
            } elseif ($st === 'd') {
                $dispenCount++;
            } elseif ($st === 's') {
                $sakitCount++;
            } elseif ($st === 'i') {
                $izinCount++;
            } elseif ($st === 'a') {
                $alfaCount++;
            }
        }

        $presentCount = $dotCount + $dispenCount;
        $absentCount = $sakitCount + $izinCount + $alfaCount;
        $totalRecorded = $presentCount + $absentCount;

        $parts = [];
        if ($dispenCount > 0) {
            $parts[] = "D:{$dispenCount}";
        }
        $parts[] = "S:{$sakitCount}";
        $parts[] = "I:{$izinCount}";
        $parts[] = "A:{$alfaCount}";

        return [
            'date' => $normalizedDate,
            'dot_count' => $dotCount,
            'dispen_count' => $dispenCount,
            'present_count' => $presentCount,
            'sakit_count' => $sakitCount,
            'izin_count' => $izinCount,
            'alfa_count' => $alfaCount,
            'absent_count' => $absentCount,
            'total_recorded' => $totalRecorded,
            'breakdown' => $totalRecorded > 0 ? implode(', ', $parts) : null,
        ];
    }

    /**
     * Sinkronkan data kehadiran tanggal tertentu ke baris Jurnal Harian kelas yang sama.
     */
    public function syncJournalForDate(TeachingClass $teachingClass, string $date, bool $forceResetOverride = true): array
    {
        $summary = $this->summarizeForDate($teachingClass, $date);

        $journals = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('journal_date', $summary['date'])
            ->get();

        foreach ($journals as $journal) {
            $journal->update([
                'present_count' => $summary['present_count'],
                'absent_count' => $summary['absent_count'],
                'attendance_breakdown' => $summary['breakdown'],
                'is_attendance_overridden' => false,
            ]);
        }

        return $summary;
    }

    /**
     * Bangun struktur matriks absensi bulanan (tanggal 1..N) beserta rekap per siswa.
     */
    public function buildMonthlyMatrix(TeachingClass $teachingClass, int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $students = $teachingClass->activeStudents()->get();

        $eventsInMonth = SchoolEvent::query()
            ->where('academic_year_id', $teachingClass->academic_year_id)
            ->inMonth($year, $month)
            ->orderBy('event_date')
            ->get();

        $daysMeta = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $cDate = Carbon::createFromDate($year, $month, $d);
            $dayOfWeek = (int) $cDate->dayOfWeek;
            $isSunday = ($dayOfWeek === 0);

            $matchedEvent = $eventsInMonth->first(fn ($ev) => $ev->coversDate($cDate));
            $isHoliday = $isSunday || ($matchedEvent && $matchedEvent->is_holiday);
            $isTeachingDay = $teachingClass->isTeachingDay($dayOfWeek);

            $daysMeta[$d] = [
                'day' => $d,
                'day_name' => IndonesianDate::DAYS_SHORT[$dayOfWeek] ?? '',
                'day_of_week' => $dayOfWeek,
                'is_sunday' => $isSunday,
                'is_holiday' => $isHoliday,
                'is_teaching_day' => $isTeachingDay,
                'is_event' => ! empty($matchedEvent),
                'event_title' => $matchedEvent?->title,
                'event_type' => $matchedEvent?->type,
            ];
        }

        $attendances = Attendance::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $matrix = [];
        foreach ($attendances as $att) {
            $day = (int) $att->attendance_date->day;
            $matrix[$att->class_student_id][$day] = strtolower(trim($att->status));
        }

        $rows = [];
        $dailyTotals = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dailyTotals[$d] = ['present' => 0, 'absent' => 0, 'recorded' => 0];
        }

        $grandTotals = [
            'present' => 0,
            'dispen' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alfa' => 0,
            'absent' => 0,
        ];

        foreach ($students as $index => $student) {
            $days = [];
            $dot = 0;
            $dispen = 0;
            $sakit = 0;
            $izin = 0;
            $alfa = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $st = $matrix[$student->id][$d] ?? '';
                $days[$d] = $st;

                if ($st === '.') {
                    $dot++;
                    $dailyTotals[$d]['present']++;
                    $dailyTotals[$d]['recorded']++;
                } elseif ($st === 'd') {
                    $dispen++;
                    $dailyTotals[$d]['present']++;
                    $dailyTotals[$d]['recorded']++;
                } elseif ($st === 's') {
                    $sakit++;
                    $dailyTotals[$d]['absent']++;
                    $dailyTotals[$d]['recorded']++;
                } elseif ($st === 'i') {
                    $izin++;
                    $dailyTotals[$d]['absent']++;
                    $dailyTotals[$d]['recorded']++;
                } elseif ($st === 'a') {
                    $alfa++;
                    $dailyTotals[$d]['absent']++;
                    $dailyTotals[$d]['recorded']++;
                }
            }

            $present = $dot + $dispen;
            $absent = $sakit + $izin + $alfa;

            $grandTotals['present'] += $present;
            $grandTotals['dispen'] += $dispen;
            $grandTotals['sakit'] += $sakit;
            $grandTotals['izin'] += $izin;
            $grandTotals['alfa'] += $alfa;
            $grandTotals['absent'] += $absent;

            $rows[] = [
                'student' => $student,
                'no' => $index + 1,
                'days' => $days,
                'dot' => $dot,
                'dispen' => $dispen,
                'present' => $present,
                'sakit' => $sakit,
                'izin' => $izin,
                'alfa' => $alfa,
                'absent' => $absent,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'days_meta' => $daysMeta,
            'has_teaching_days' => ! empty($teachingClass->teaching_days),
            'teaching_days' => $teachingClass->teaching_days ?? [],
            'events_in_month' => $eventsInMonth,
            'rows' => $rows,
            'daily_totals' => $dailyTotals,
            'grand_totals' => $grandTotals,
        ];
    }

    /**
     * Bangun rekapitulasi absensi selama 1 semester penuh pada kelas tersebut.
     */
    public function buildSemesterRecap(TeachingClass $teachingClass): array
    {
        $students = $teachingClass->activeStudents()->get();
        $attendances = Attendance::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->get();

        $distinctDates = $attendances
            ->pluck('attendance_date')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->sort()
            ->values();

        $byStudent = $attendances->groupBy('class_student_id');
        $rows = [];

        foreach ($students as $index => $student) {
            $records = $byStudent->get($student->id, collect());
            $dot = 0;
            $dispen = 0;
            $sakit = 0;
            $izin = 0;
            $alfa = 0;

            foreach ($records as $rec) {
                $st = strtolower(trim($rec->status));
                if ($st === '.') {
                    $dot++;
                } elseif ($st === 'd') {
                    $dispen++;
                } elseif ($st === 's') {
                    $sakit++;
                } elseif ($st === 'i') {
                    $izin++;
                } elseif ($st === 'a') {
                    $alfa++;
                }
            }

            $present = $dot + $dispen;
            $absent = $sakit + $izin + $alfa;
            $totalRecorded = $present + $absent;
            $percentage = $totalRecorded > 0 ? round(($present / $totalRecorded) * 100, 1) : 0.0;

            $rows[] = [
                'student' => $student,
                'no' => $index + 1,
                'dot' => $dot,
                'dispen' => $dispen,
                'present' => $present,
                'sakit' => $sakit,
                'izin' => $izin,
                'alfa' => $alfa,
                'absent' => $absent,
                'total_meetings' => $totalRecorded,
                'percentage' => $percentage,
            ];
        }

        return [
            'total_active_dates' => $distinctDates->count(),
            'active_dates' => $distinctDates,
            'rows' => $rows,
        ];
    }

    /**
     * Hitung Nilai Kehadiran dengan kuota toleransi gabungan Sakit + Izin (S + I).
     * - Hadir ('.') dan Dispen ('d') dihitung hadir penuh tanpa memotong kuota toleransi.
     * - Sakit ('s') + Izin ('i') ditoleransi hingga batas $tolerance per semester.
     * - Alfa ('a') tidak mendapat toleransi (0 toleransi).
     */
    public function calculateToleranceScore(
        int $present,
        int $sakit,
        int $izin,
        int $alfa,
        int $tolerance
    ): array {
        $safeTolerance = max(0, $tolerance);
        $sickLeaveTotal = max(0, $sakit) + max(0, $izin);
        $toleratedSickLeave = min($sickLeaveTotal, $safeTolerance);
        $excessSickLeave = max(0, $sickLeaveTotal - $safeTolerance);
        $effectivePresent = max(0, $present) + $toleratedSickLeave;
        $totalMeetings = max(0, $present) + $sickLeaveTotal + max(0, $alfa);
        $score = $totalMeetings > 0
            ? round(($effectivePresent / $totalMeetings) * 100, 2)
            : null;

        return [
            'present' => $present,
            'sakit' => $sakit,
            'izin' => $izin,
            'alfa' => $alfa,
            'tolerance' => $safeTolerance,
            'sick_leave_total' => $sickLeaveTotal,
            'tolerated_sick_leave' => $toleratedSickLeave,
            'excess_sick_leave' => $excessSickLeave,
            'effective_present' => $effectivePresent,
            'total_meetings' => $totalMeetings,
            'score' => $score,
        ];
    }

    /**
     * Bangun peta rincian absensi semester beserta skor kehadiran bertoleransi per murid (key: class_student_id).
     */
    public function buildToleranceMapForClass(TeachingClass $teachingClass, int $tolerance): array
    {
        $recap = $this->buildSemesterRecap($teachingClass);
        $map = [];

        foreach ($recap['rows'] as $r) {
            $tol = $this->calculateToleranceScore(
                $r['present'],
                $r['sakit'],
                $r['izin'],
                $r['alfa'],
                $tolerance
            );

            $map[$r['student']->id] = [
                'dot' => $r['dot'],
                'dispen' => $r['dispen'],
                'present' => $r['present'],
                'sakit' => $r['sakit'],
                'izin' => $r['izin'],
                'alfa' => $r['alfa'],
                'total' => $r['total_meetings'],
                'percentage' => $r['percentage'],
                'tolerance' => $tol['tolerance'],
                'sick_leave_total' => $tol['sick_leave_total'],
                'tolerated_si' => $tol['tolerated_sick_leave'],
                'excess_si' => $tol['excess_sick_leave'],
                'effective_present' => $tol['effective_present'],
                'auto_score' => $tol['score'],
            ];
        }

        return $map;
    }

    /**
     * Ambil daftar detail murid berstatus Sakit, Izin, Alpa, atau Dispensasi (S/I/A/D) pada tanggal tertentu.
     */
    public function getAbsentDispenDetailForDate(TeachingClass $teachingClass, string $date): array
    {
        $normalizedDate = Carbon::parse($date)->toDateString();
        $teachingClass->loadMissing('subject');

        $activeStudentIds = $teachingClass->activeStudents()->pluck('id');

        $attendances = Attendance::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('attendance_date', $normalizedDate)
            ->whereIn('class_student_id', $activeStudentIds)
            ->whereIn('status', ['s', 'i', 'a', 'd'])
            ->with('student')
            ->get();

        $students = $attendances->map(function (Attendance $att) {
            $st = strtolower(trim((string) $att->status));
            $meta = self::NON_REGULAR_STATUS_META[$st] ?? [
                'label' => strtoupper($st),
                'color' => 'slate',
                'badge' => 'bg-slate-100 text-slate-800 border-slate-200',
            ];

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
        })->sortBy([
            ['order_number', 'asc'],
            ['full_name', 'asc'],
        ])->values();

        $journal = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereDate('journal_date', $normalizedDate)
            ->first();

        $carbonDate = Carbon::parse($normalizedDate);

        return [
            'date' => $normalizedDate,
            'day_name' => IndonesianDate::dayName($normalizedDate),
            'formatted_date' => IndonesianDate::formatLong($normalizedDate),
            'class_name' => $teachingClass->class_name,
            'subject_name' => $teachingClass->subject?->name,
            'journal_material' => $journal?->activity_material,
            'total_absent' => $students->whereIn('status_code', ['S', 'I', 'A'])->count(),
            'total_dispen' => $students->where('status_code', 'D')->count(),
            'total_affected' => $students->count(),
            'sakit_count' => $students->where('status_code', 'S')->count(),
            'izin_count' => $students->where('status_code', 'I')->count(),
            'alfa_count' => $students->where('status_code', 'A')->count(),
            'students' => $students->all(),
            'journal_absent_count' => $journal?->absent_count,
            'is_overridden' => (bool) ($journal?->is_attendance_overridden ?? false),
            'attendance_url' => route('attendances.index', [
                'teachingClass' => $teachingClass->id,
                'month' => $carbonDate->month,
            ]),
        ];
    }

    /**
     * Ambil daftar tanggal Jurnal Harian & Absensi S/I/A/D untuk pilihan cepat Penilaian Harian.
     */
    public function getClassAttendanceDateOptions(TeachingClass $teachingClass): array
    {
        $journals = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->orderByDesc('journal_date')
            ->get()
            ->keyBy(fn (DailyJournal $j) => $j->journal_date->toDateString());

        $activeStudentIds = $teachingClass->activeStudents()->pluck('id');

        $nonRegularRows = Attendance::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->whereIn('class_student_id', $activeStudentIds)
            ->whereIn('status', ['s', 'i', 'a', 'd'])
            ->get(['attendance_date', 'status']);

        $countsByDate = [];
        foreach ($nonRegularRows as $att) {
            $d = $att->attendance_date->toDateString();
            $st = strtolower(trim((string) $att->status));
            if (! isset($countsByDate[$d])) {
                $countsByDate[$d] = ['s' => 0, 'i' => 0, 'a' => 0, 'd' => 0];
            }
            if (isset($countsByDate[$d][$st])) {
                $countsByDate[$d][$st]++;
            }
        }

        $allDates = collect(array_keys($journals->all()))
            ->merge(array_keys($countsByDate))
            ->unique()
            ->sortDesc()
            ->values();

        return $allDates->map(function (string $d) use ($journals, $countsByDate) {
            /** @var DailyJournal|null $journal */
            $journal = $journals->get($d);
            $counts = $countsByDate[$d] ?? ['s' => 0, 'i' => 0, 'a' => 0, 'd' => 0];
            $totalAffected = $counts['s'] + $counts['i'] + $counts['a'] + $counts['d'];
            $formattedDate = IndonesianDate::formatLong($d);
            $dayName = IndonesianDate::dayName($d);

            $statusSummary = $totalAffected > 0
                ? "{$totalAffected} S/I/A/D (S:{$counts['s']} I:{$counts['i']} A:{$counts['a']} D:{$counts['d']})"
                : 'Hadir Lengkap';

            $materialPreview = $journal && $journal->activity_material
                ? ' — ' . mb_strimwidth(trim(preg_replace('/\s+/', ' ', $journal->activity_material)), 0, 38, '...')
                : '';

            return [
                'date' => $d,
                'formatted_date' => $formattedDate,
                'day_name' => $dayName,
                'journal_material' => $journal?->activity_material,
                'sakit_count' => $counts['s'],
                'izin_count' => $counts['i'],
                'alfa_count' => $counts['a'],
                'dispen_count' => $counts['d'],
                'total_affected' => $totalAffected,
                'option_label' => "{$formattedDate} ({$dayName}) • {$statusSummary}{$materialPreview}",
            ];
        })->all();
    }
}
