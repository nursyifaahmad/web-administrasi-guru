<?php

namespace App\Services;

use App\Models\ClassStudent;
use App\Models\TeachingClass;

class StudentGradeShareService
{
    /**
     * Normalisasi string NIS/NISN agar toleran terhadap spasi, titik, dan tanda hubung.
     */
    public function normalizeNisToken(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\.\-\_]+/', '', trim($value)));
    }

    /**
     * Pecah format gabungan NIS / NISN menjadi kandidat token yang valid untuk pencocokan.
     *
     * @return list<string>
     */
    protected function extractCandidateTokens(string $stored): array
    {
        $tokens = [];
        $fullNormalized = $this->normalizeNisToken($stored);
        if ($fullNormalized !== '') {
            $tokens[] = $fullNormalized;
        }

        $parts = preg_split('/[\/|,]+/', $stored) ?: [];
        foreach ($parts as $part) {
            $normalizedPart = $this->normalizeNisToken($part);
            if ($normalizedPart !== '' && mb_strlen($normalizedPart) >= 4) {
                $tokens[] = $normalizedPart;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Cocokkan NIS/NISN pada murid aktif di dalam kelas ajar tanpa membocorkan daftar siswa lain.
     *
     * @return array{status: 'matched'|'not_found'|'ambiguous', student: ClassStudent|null}
     */
    public function findActiveStudentByNisNisn(TeachingClass $teachingClass, string $rawInput): array
    {
        $trimmedInput = trim($rawInput);
        $normalizedInput = $this->normalizeNisToken($trimmedInput);

        if ($trimmedInput === '' || $normalizedInput === '') {
            return ['status' => 'not_found', 'student' => null];
        }

        $candidates = $teachingClass->activeStudents()
            ->whereNotNull('nis_nisn')
            ->get();

        $matches = $candidates->filter(function (ClassStudent $student) use ($trimmedInput, $normalizedInput) {
            $stored = trim((string) $student->nis_nisn);
            if ($stored === '') {
                return false;
            }

            if (strcasecmp($stored, $trimmedInput) === 0) {
                return true;
            }

            if (mb_strlen($normalizedInput) < 4) {
                return false;
            }

            return in_array($normalizedInput, $this->extractCandidateTokens($stored), true);
        })->values();

        if ($matches->count() === 1) {
            return ['status' => 'matched', 'student' => $matches->first()];
        }

        if ($matches->count() > 1) {
            return ['status' => 'ambiguous', 'student' => null];
        }

        return ['status' => 'not_found', 'student' => null];
    }

    /**
     * Susun rincian nilai lengkap hanya untuk 1 murid terverifikasi dari buku kelas.
     */
    public function buildSingleStudentReport(
        TeachingClass $teachingClass,
        ClassStudent $student,
        ?GradeCalculationService $gradeService = null,
        ?AttendanceJournalSyncService $attendanceService = null
    ): ?array {
        $gradeSvc = $gradeService ?? app(GradeCalculationService::class);
        $syncSvc = $attendanceService ?? app(AttendanceJournalSyncService::class);

        $gradebook = $gradeSvc->buildGradebookData($teachingClass, $syncSvc);

        $matchedRow = collect($gradebook['rows'])->first(
            fn (array $row) => (int) ($row['student']?->id ?? 0) === (int) $student->id
        );

        if (! $matchedRow) {
            return null;
        }

        $weight = $gradebook['weight'];
        $precision = in_array((int) ($weight->rounding_precision ?? 2), [0, 1, 2], true)
            ? (int) $weight->rounding_precision
            : 2;

        $phItems = $gradebook['ph_columns']->map(function ($col) use ($matchedRow) {
            $val = $matchedRow['dynamic_scores'][(string) $col->id] ?? null;

            return [
                'id' => $col->id,
                'label' => $col->label,
                'topic_note' => $col->topic_note,
                'assessment_date_label' => $col->assessment_date_label,
                'score' => $val,
            ];
        })->values();

        $ntItems = $gradebook['nt_columns']->map(function ($col) use ($matchedRow) {
            $val = $matchedRow['dynamic_scores'][(string) $col->id] ?? null;

            return [
                'id' => $col->id,
                'label' => $col->label,
                'topic_note' => $col->topic_note,
                'score' => $val,
            ];
        })->values();

        return [
            'teaching_class' => $teachingClass,
            'student' => $matchedRow['student'],
            'no' => $matchedRow['no'],
            'kktp' => $gradebook['kktp'],
            'weight' => $weight,
            'is_auto_attendance' => $weight->isAutoAttendance(),
            'rounding_precision' => $precision,
            'ph_items' => $phItems,
            'nt_items' => $ntItems,
            'avg_ph' => $matchedRow['avg_ph'],
            'avg_nt' => $matchedRow['avg_nt'],
            'attendance_score' => $matchedRow['attendance_score'],
            'attendance_ref' => $matchedRow['attendance_ref'],
            'sts_score' => $matchedRow['sts_score'],
            'sas_score' => $matchedRow['sas_score'],
            'final_score' => $matchedRow['final_score'],
            'formatted_final_score' => number_format((float) $matchedRow['final_score'], $precision, '.', ''),
            'is_tuntas' => (bool) $matchedRow['is_tuntas'],
        ];
    }

    /**
     * Hitung jumlah murid aktif yang belum memiliki NIS/NISN per school_class_id.
     *
     * @param  iterable<int|string|null>  $schoolClassIds
     * @return array<int, int>
     */
    public function countMissingNisBySchoolClass(iterable $schoolClassIds): array
    {
        $ids = collect($schoolClassIds)
            ->filter(fn ($id) => ! empty($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return ClassStudent::query()
            ->whereIn('school_class_id', $ids)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('nis_nisn')
                    ->orWhereRaw("TRIM(nis_nisn) = ''");
            })
            ->selectRaw('school_class_id, COUNT(*) as missing_count')
            ->groupBy('school_class_id')
            ->pluck('missing_count', 'school_class_id')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();
    }
}
