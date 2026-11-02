<?php

namespace App\Services;

use App\Models\GradeWeight;
use App\Models\StudentGrade;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GradeCalculationService
{
    /**
     * Hitung rata-rata PH, rata-rata NT, dan Nilai Akhir (NA) satu murid.
     * Seluruh kolom aktif menjadi pembagi (nilai kosong dianggap 0).
     */
    public function calculateStudentRow(
        array $dynamicScores,
        ?float $attendanceScore,
        ?float $stsScore,
        ?float $sasScore,
        Collection $phColumns,
        Collection $ntColumns,
        GradeWeight $weight
    ): array {
        $phCount = $phColumns->count();
        $phSum = 0.0;
        foreach ($phColumns as $col) {
            $raw = $dynamicScores[(string) $col->id] ?? $dynamicScores[$col->id] ?? null;
            $phSum += ($raw !== null && $raw !== '') ? (float) $raw : 0.0;
        }
        $avgPh = $phCount > 0 ? ($phSum / $phCount) : 0.0;

        $ntCount = $ntColumns->count();
        $ntSum = 0.0;
        foreach ($ntColumns as $col) {
            $raw = $dynamicScores[(string) $col->id] ?? $dynamicScores[$col->id] ?? null;
            $ntSum += ($raw !== null && $raw !== '') ? (float) $raw : 0.0;
        }
        $avgNt = $ntCount > 0 ? ($ntSum / $ntCount) : 0.0;

        $attVal = ($attendanceScore !== null) ? (float) $attendanceScore : 0.0;
        $stsVal = ($stsScore !== null) ? (float) $stsScore : 0.0;
        $sasVal = ($sasScore !== null) ? (float) $sasScore : 0.0;

        $finalRaw = ($avgPh * ((float) $weight->weight_ph / 100.0))
            + ($attVal * ((float) $weight->weight_attendance / 100.0))
            + ($avgNt * ((float) $weight->weight_nt / 100.0))
            + ($stsVal * ((float) $weight->weight_sts / 100.0))
            + ($sasVal * ((float) $weight->weight_sas / 100.0));

        $precision = in_array((int) $weight->rounding_precision, [0, 1, 2], true)
            ? (int) $weight->rounding_precision
            : 2;

        return [
            'avg_ph' => round($avgPh, 2),
            'avg_nt' => round($avgNt, 2),
            'final_score' => round($finalRaw, $precision),
        ];
    }

    /**
     * Hitung ulang seluruh nilai murid di dalam satu kelas ajar.
     */
    public function recalculateClass(TeachingClass $teachingClass): void
    {
        $teachingClass->ensureDefaultsInitialized();

        $phColumns = $teachingClass->phColumns()->get();
        $ntColumns = $teachingClass->ntColumns()->get();
        $weight = $teachingClass->gradeWeight()->firstOrFail();
        $students = $teachingClass->activeStudents()->get();
        $existingGrades = $teachingClass->studentGrades()->get()->keyBy('class_student_id');

        DB::transaction(function () use ($teachingClass, $students, $existingGrades, $phColumns, $ntColumns, $weight) {
            foreach ($students as $student) {
                /** @var StudentGrade|null $grade */
                $grade = $existingGrades->get($student->id);
                $dynamicScores = $grade?->dynamic_scores ?? [];
                $att = $grade?->attendance_score;
                $sts = $grade?->sts_score;
                $sas = $grade?->sas_score;

                $calc = $this->calculateStudentRow(
                    $dynamicScores,
                    $att,
                    $sts,
                    $sas,
                    $phColumns,
                    $ntColumns,
                    $weight
                );

                StudentGrade::query()->updateOrCreate(
                    [
                        'teaching_class_id' => $teachingClass->id,
                        'class_student_id' => $student->id,
                    ],
                    [
                        'attendance_score' => $att,
                        'sts_score' => $sts,
                        'sas_score' => $sas,
                        'dynamic_scores' => $dynamicScores,
                        'avg_ph' => $calc['avg_ph'],
                        'avg_nt' => $calc['avg_nt'],
                        'final_score' => $calc['final_score'],
                    ]
                );
            }
        });
    }

    /**
     * Bangun struktur lengkap buku nilai kelas untuk UI, PDF, dan Excel.
     */
    public function buildGradebookData(TeachingClass $teachingClass, ?AttendanceJournalSyncService $attendanceService = null): array
    {
        $teachingClass->ensureDefaultsInitialized();
        $teachingClass->loadMissing(['subject.kktps', 'academicYear', 'teacher']);

        $phColumns = $teachingClass->phColumns()->get();
        $ntColumns = $teachingClass->ntColumns()->get();
        $weight = $teachingClass->gradeWeight()->firstOrFail();
        $students = $teachingClass->activeStudents()->get();
        $gradesByStudent = $teachingClass->studentGrades()->get()->keyBy('class_student_id');
        $kktp = $teachingClass->effective_kktp;

        $attendanceRef = [];
        if ($attendanceService) {
            $recap = $attendanceService->buildSemesterRecap($teachingClass);
            foreach ($recap['rows'] as $r) {
                $attendanceRef[$r['student']->id] = [
                    'present' => $r['present'],
                    'total' => $r['total_meetings'],
                    'percentage' => $r['percentage'],
                ];
            }
        }

        $rows = [];
        $scores = [];
        $tuntasCount = 0;
        $remedialCount = 0;

        foreach ($students as $index => $student) {
            /** @var StudentGrade|null $grade */
            $grade = $gradesByStudent->get($student->id);
            $dynamic = $grade?->dynamic_scores ?? [];

            $normalizedDynamic = [];
            foreach ($phColumns->concat($ntColumns) as $col) {
                $val = $dynamic[(string) $col->id] ?? $dynamic[$col->id] ?? null;
                $normalizedDynamic[(string) $col->id] = ($val !== null && $val !== '') ? (float) $val : null;
            }

            $calc = $this->calculateStudentRow(
                $normalizedDynamic,
                $grade?->attendance_score,
                $grade?->sts_score,
                $grade?->sas_score,
                $phColumns,
                $ntColumns,
                $weight
            );

            $finalScore = $calc['final_score'];
            $isTuntas = $finalScore >= $kktp;
            if ($isTuntas) {
                $tuntasCount++;
            } else {
                $remedialCount++;
            }
            $scores[] = $finalScore;

            $rows[] = [
                'no' => $index + 1,
                'student' => $student,
                'dynamic_scores' => $normalizedDynamic,
                'attendance_score' => $grade?->attendance_score,
                'sts_score' => $grade?->sts_score,
                'sas_score' => $grade?->sas_score,
                'avg_ph' => $calc['avg_ph'],
                'avg_nt' => $calc['avg_nt'],
                'final_score' => $finalScore,
                'is_tuntas' => $isTuntas,
                'attendance_ref' => $attendanceRef[$student->id] ?? ['present' => 0, 'total' => 0, 'percentage' => 0],
            ];
        }

        $studentCount = count($scores);
        $stats = [
            'student_count' => $studentCount,
            'class_average' => $studentCount > 0 ? round(array_sum($scores) / $studentCount, 2) : 0.0,
            'highest_score' => $studentCount > 0 ? max($scores) : 0.0,
            'lowest_score' => $studentCount > 0 ? min($scores) : 0.0,
            'tuntas_count' => $tuntasCount,
            'remedial_count' => $remedialCount,
            'tuntas_percentage' => $studentCount > 0 ? round(($tuntasCount / $studentCount) * 100, 1) : 0.0,
        ];

        return [
            'kktp' => $kktp,
            'ph_columns' => $phColumns,
            'nt_columns' => $ntColumns,
            'weight' => $weight,
            'rows' => $rows,
            'stats' => $stats,
        ];
    }

    /**
     * Salin rumus persentase bobot NA ke kelas-kelas lain milik guru (atau kelas mana pun oleh Admin).
     */
    public function copyWeightsToClasses(TeachingClass $sourceClass, array $targetClassIds, User $actor): int
    {
        $sourceClass->ensureDefaultsInitialized();
        $sourceWeight = $sourceClass->gradeWeight()->firstOrFail();

        $query = TeachingClass::query()
            ->whereIn('id', $targetClassIds)
            ->where('id', '!=', $sourceClass->id);

        if (! $actor->isAdmin()) {
            $query->where('teacher_id', $actor->id);
        }

        $targets = $query->get();
        $copied = 0;

        foreach ($targets as $target) {
            $target->ensureDefaultsInitialized();
            $target->gradeWeight()->updateOrCreate(
                ['teaching_class_id' => $target->id],
                [
                    'weight_ph' => $sourceWeight->weight_ph,
                    'weight_attendance' => $sourceWeight->weight_attendance,
                    'weight_nt' => $sourceWeight->weight_nt,
                    'weight_sts' => $sourceWeight->weight_sts,
                    'weight_sas' => $sourceWeight->weight_sas,
                    'rounding_precision' => $sourceWeight->rounding_precision,
                    'agreement_note' => $sourceWeight->agreement_note,
                ]
            );
            $this->recalculateClass($target);
            $copied++;
        }

        return $copied;
    }
}
