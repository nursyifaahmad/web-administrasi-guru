<?php

namespace App\Services;

use App\Models\DailyJournal;
use App\Models\SchoolSetting;
use App\Models\TeachingClass;
use App\Support\IndonesianDate;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;

class PdfExportService
{
    public function __construct(
        protected AttendanceJournalSyncService $attendanceService,
        protected GradeCalculationService $gradeService
    ) {}

    public function normalizeOptions(array $input, SchoolSetting $school, string $defaultOrientation = 'landscape', ?TeachingClass $teachingClass = null): array
    {
        $city = trim((string) ($input['city'] ?? $school->default_city ?? 'Lemahabang'));
        if ($city === '') {
            $city = $school->default_city ?: 'Lemahabang';
        }

        $printDate = ! empty($input['print_date'])
            ? Carbon::parse($input['print_date'])->toDateString()
            : now()->toDateString();

        $paperSize = strtoupper((string) ($input['paper_size'] ?? 'F4')) === 'A4' ? 'A4' : 'F4';
        $orientation = strtolower((string) ($input['orientation'] ?? $defaultOrientation)) === 'portrait'
            ? 'portrait'
            : 'landscape';

        $showNicknameDefault = $teachingClass ? (bool) $teachingClass->show_nickname_column : true;

        return [
            'city' => $city,
            'print_date' => $printDate,
            'titimangsa' => IndonesianDate::titimangsa($city, $printDate),
            'paper_size' => $paperSize,
            'orientation' => $orientation,
            'show_header' => $this->boolOption($input, 'show_header', true),
            'show_headmaster' => $this->boolOption($input, 'show_headmaster', false),
            'show_digital_signature' => $this->boolOption($input, 'show_digital_signature', true),
            'show_journal_paraf' => $this->boolOption($input, 'show_journal_paraf', true),
            'show_nickname' => $this->boolOption($input, 'show_nickname', $showNicknameDefault),
        ];
    }

    protected function boolOption(array $input, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $input)) {
            return $default;
        }

        return filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
    }

    protected function configurePaper(DomPdfInstance $pdf, array $options): DomPdfInstance
    {
        $paper = $options['paper_size'] === 'F4'
            ? [0.0, 0.0, 612.0, 936.0] // 8.5 x 13 inch (F4 / Folio Resmi Indonesia)
            : 'a4';

        return $pdf->setPaper($paper, $options['orientation'])
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);
    }

    public function generateJournalPdf(TeachingClass $teachingClass, array $input = []): DomPdfInstance
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->normalizeOptions($input, $school, 'landscape', $teachingClass);

        $query = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->orderBy('journal_date')
            ->orderBy('lesson_hours');

        $periodLabel = $teachingClass->academicYear->full_label;

        if (! empty($input['month']) && ! empty($input['year'])) {
            $m = (int) $input['month'];
            $y = (int) $input['year'];
            $query->whereYear('journal_date', $y)->whereMonth('journal_date', $m);
            $periodLabel = IndonesianDate::monthName($m) . ' ' . $y . ' (' . $teachingClass->academicYear->full_label . ')';
        } elseif (! empty($input['start_date']) && ! empty($input['end_date'])) {
            $query->whereBetween('journal_date', [$input['start_date'], $input['end_date']]);
            $periodLabel = IndonesianDate::formatLong($input['start_date']) . ' s/d ' . IndonesianDate::formatLong($input['end_date']);
        }

        $journals = $query->get();

        $pdf = Pdf::loadView('exports.pdf.journal', [
            'school' => $school,
            'teachingClass' => $teachingClass,
            'journals' => $journals,
            'periodLabel' => $periodLabel,
            'options' => $options,
        ]);

        return $this->configurePaper($pdf, $options);
    }

    public function generateAttendanceMonthlyPdf(TeachingClass $teachingClass, int $year, int $month, array $input = []): DomPdfInstance
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->normalizeOptions($input, $school, 'landscape', $teachingClass);
        $matrix = $this->attendanceService->buildMonthlyMatrix($teachingClass, $year, $month);
        $monthLabel = IndonesianDate::monthName($month) . ' ' . $year;

        $pdf = Pdf::loadView('exports.pdf.attendance-monthly', [
            'school' => $school,
            'teachingClass' => $teachingClass,
            'matrix' => $matrix,
            'monthLabel' => $monthLabel,
            'options' => $options,
        ]);

        return $this->configurePaper($pdf, $options);
    }

    public function generateAttendanceRecapPdf(TeachingClass $teachingClass, array $input = []): DomPdfInstance
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->normalizeOptions($input, $school, 'portrait', $teachingClass);
        $recap = $this->attendanceService->buildSemesterRecap($teachingClass);

        $pdf = Pdf::loadView('exports.pdf.attendance-recap', [
            'school' => $school,
            'teachingClass' => $teachingClass,
            'recap' => $recap,
            'options' => $options,
        ]);

        return $this->configurePaper($pdf, $options);
    }

    public function generateGradebookPdf(TeachingClass $teachingClass, array $input = []): DomPdfInstance
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->normalizeOptions($input, $school, 'landscape', $teachingClass);
        $gradebook = $this->gradeService->buildGradebookData($teachingClass, $this->attendanceService);

        $pdf = Pdf::loadView('exports.pdf.gradebook', [
            'school' => $school,
            'teachingClass' => $teachingClass,
            'gradebook' => $gradebook,
            'options' => $options,
        ]);

        return $this->configurePaper($pdf, $options);
    }

    public function generateBundlePdf(TeachingClass $teachingClass, int $year, int $month, array $input = []): DomPdfInstance
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->normalizeOptions($input, $school, 'landscape', $teachingClass);

        $journals = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->orderBy('journal_date')
            ->orderBy('lesson_hours')
            ->get();

        $matrix = $this->attendanceService->buildMonthlyMatrix($teachingClass, $year, $month);
        $recap = $this->attendanceService->buildSemesterRecap($teachingClass);
        $gradebook = $this->gradeService->buildGradebookData($teachingClass, $this->attendanceService);
        $monthLabel = IndonesianDate::monthName($month) . ' ' . $year;

        $pdf = Pdf::loadView('exports.pdf.bundle', [
            'school' => $school,
            'teachingClass' => $teachingClass,
            'journals' => $journals,
            'matrix' => $matrix,
            'recap' => $recap,
            'gradebook' => $gradebook,
            'monthLabel' => $monthLabel,
            'periodLabel' => $teachingClass->academicYear->full_label,
            'options' => $options,
        ]);

        return $this->configurePaper($pdf, $options);
    }
}
