<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\SchoolSetting;
use App\Models\StudentGrade;
use App\Models\TeachingClass;
use App\Support\IndonesianDate;
use App\Support\SignatureImageHelper;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportImportService
{
    /** @var array<int, string> */
    protected array $tempFiles = [];

    public function __construct(
        protected AttendanceJournalSyncService $attendanceService,
        protected GradeCalculationService $gradeService,
        protected PdfExportService $pdfService
    ) {}

    protected function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    protected function cell(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    protected function range(int $c1, int $r1, int $c2, int $r2): string
    {
        return $this->cell($c1, $r1) . ':' . $this->cell($c2, $r2);
    }

    protected function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $tempFiles = $this->tempFiles;
        $this->tempFiles = [];

        return response()->streamDownload(function () use ($spreadsheet, $tempFiles) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            foreach ($tempFiles as $tmp) {
                if (is_string($tmp) && file_exists($tmp)) {
                    @unlink($tmp);
                }
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function createTempImageFromBase64(?string $dataUri): ?string
    {
        if (empty($dataUri) || ! str_starts_with($dataUri, 'data:image/')) {
            return null;
        }

        $parts = explode(',', $dataUri, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false || strlen($binary) < 20) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
        file_put_contents($tmpPath, $binary);
        $this->tempFiles[] = $tmpPath;

        return $tmpPath;
    }

    protected function applyPageSetup(Worksheet $sheet, array $options): void
    {
        $orientation = ($options['orientation'] ?? 'landscape') === 'portrait'
            ? PageSetup::ORIENTATION_PORTRAIT
            : PageSetup::ORIENTATION_LANDSCAPE;

        $paperSize = ($options['paper_size'] ?? 'F4') === 'A4'
            ? PageSetup::PAPERSIZE_A4
            : PageSetup::PAPERSIZE_FOLIO;

        $sheet->getPageSetup()->setOrientation($orientation);
        $sheet->getPageSetup()->setPaperSize($paperSize);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
    }

    /**
     * Render Kop Sekolah + Judul + Identitas Kelas di bagian atas sheet.
     * Mengembalikan nomor baris berikutnya yang siap diisi tabel data.
     */
    protected function renderOfficialHeader(
        Worksheet $sheet,
        SchoolSetting $school,
        TeachingClass $teachingClass,
        string $docTitle,
        string $docSubtitle,
        int $totalCols,
        array $options
    ): int {
        $row = 1;
        $totalCols = max(6, $totalCols);

        if ($options['show_header'] ?? true) {
            $kopStartRow = $row;

            $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
            $sheet->setCellValue($this->cell(1, $row), strtoupper((string) $school->gov_header));
            $sheet->getStyle($this->cell(1, $row))->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(16);
            $row++;

            $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
            $sheet->setCellValue($this->cell(1, $row), strtoupper((string) $school->department_header));
            $sheet->getStyle($this->cell(1, $row))->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(15);
            $row++;

            $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
            $sheet->setCellValue($this->cell(1, $row), strtoupper((string) $school->school_name));
            $sheet->getStyle($this->cell(1, $row))->applyFromArray([
                'font' => ['bold' => true, 'size' => 15],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(19);
            $row++;

            $addressLine = $school->singleLineAddress();
            $contactLine = $school->contactAndIdentityLine();

            if ($addressLine !== '') {
                $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
                $sheet->setCellValue($this->cell(1, $row), $addressLine);
                $sheet->getStyle($this->range(1, $row, $totalCols, $row))->applyFromArray([
                    'font' => ['size' => 9],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                if ($contactLine === '') {
                    $sheet->getStyle($this->range(1, $row, $totalCols, $row))->applyFromArray([
                        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']]],
                    ]);
                }
                $sheet->getRowDimension($row)->setRowHeight(16);
            }

            if ($contactLine !== '') {
                if ($addressLine !== '') {
                    $row++;
                }
                $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
                $sheet->setCellValue($this->cell(1, $row), $contactLine);
                $sheet->getStyle($this->range(1, $row, $totalCols, $row))->applyFromArray([
                    'font' => ['size' => 9],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']]],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(18);
            }

            $leftBox = $school->resolvedLogoBox('left');
            $leftLogoTmp = $this->createTempImageFromBase64($leftBox['data_uri'] ?? null);
            if ($leftLogoTmp) {
                $drawingLeft = new Drawing();
                $drawingLeft->setName('Logo Kiri');
                $drawingLeft->setPath($leftLogoTmp);
                $drawingLeft->setCoordinates($this->cell(1, $kopStartRow));
                $drawingLeft->setOffsetX(8);
                $drawingLeft->setOffsetY(2);
                $drawingLeft->setHeight($leftBox['height']);
                $drawingLeft->setWorksheet($sheet);
            }

            $rightBox = $school->resolvedLogoBox('right');
            $rightLogoTmp = $this->createTempImageFromBase64($rightBox['data_uri'] ?? null);
            if ($rightLogoTmp) {
                $drawingRight = new Drawing();
                $drawingRight->setName('Logo Kanan');
                $drawingRight->setPath($rightLogoTmp);
                $drawingRight->setCoordinates($this->cell(max(1, $totalCols - 1), $kopStartRow));
                $drawingRight->setOffsetX(4);
                $drawingRight->setOffsetY(2);
                $drawingRight->setHeight($rightBox['height']);
                $drawingRight->setWorksheet($sheet);
            }

            $row += 2;
        }

        $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
        $sheet->setCellValue($this->cell(1, $row), $docTitle);
        $sheet->getStyle($this->cell(1, $row))->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $sheet->mergeCells($this->range(1, $row, $totalCols, $row));
        $sheet->setCellValue($this->cell(1, $row), $docSubtitle);
        $sheet->getStyle($this->cell(1, $row))->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row += 2;

        $midCol = max(4, (int) floor($totalCols / 2) + 1);

        $metaLeft = [
            ['Mata Pelajaran', ': ' . ($teachingClass->subject->name ?? '-')],
            ['Kelas / Tingkat', ': ' . $teachingClass->class_name . ' (Kelas ' . $teachingClass->grade_level . ')'],
            ['Guru Pengampu', ': ' . ($teachingClass->teacher->name ?? '-')],
        ];
        $metaRight = [
            ['Tahun Ajaran', ': ' . ($teachingClass->academicYear->year_name ?? '-') . ' (' . ($teachingClass->academicYear->semester ?? '-') . ')'],
            ['KKM / KKTP', ': ' . $teachingClass->effective_kktp],
            ['Wali Kelas', ': ' . $teachingClass->homeroom_teacher_name],
        ];

        for ($i = 0; $i < 3; $i++) {
            $sheet->mergeCells($this->range(1, $row, 2, $row));
            $sheet->setCellValue($this->cell(1, $row), $metaLeft[$i][0]);
            $sheet->getStyle($this->cell(1, $row))->getFont()->setBold(true);

            $sheet->mergeCells($this->range(3, $row, max(3, $midCol - 1), $row));
            $sheet->setCellValue($this->cell(3, $row), $metaLeft[$i][1]);

            $rightLabelEnd = min($totalCols - 1, $midCol + 1);
            $sheet->mergeCells($this->range($midCol, $row, $rightLabelEnd, $row));
            $sheet->setCellValue($this->cell($midCol, $row), $metaRight[$i][0]);
            $sheet->getStyle($this->cell($midCol, $row))->getFont()->setBold(true);

            $rightValStart = min($totalCols, $rightLabelEnd + 1);
            if ($rightValStart <= $totalCols) {
                $sheet->mergeCells($this->range($rightValStart, $row, $totalCols, $row));
                $sheet->setCellValue($this->cell($rightValStart, $row), $metaRight[$i][1]);
            }
            $row++;
        }

        return $row + 1;
    }

    /**
     * Render blok Titimangsa, Tanda Tangan + NIP Wali Kelas, Guru Mapel, dan opsional Kepala Sekolah.
     */
    protected function renderSignatureBlock(
        Worksheet $sheet,
        SchoolSetting $school,
        TeachingClass $teachingClass,
        int $startRow,
        int $totalCols,
        array $options
    ): void {
        $row = $startRow + 2;
        $totalCols = max(6, $totalCols);

        $leftStart = 1;
        $leftEnd = max(3, (int) floor($totalCols * 0.4));
        $rightStart = max($leftEnd + 2, $totalCols - max(2, (int) floor($totalCols * 0.4)) + 1);
        if ($rightStart > $totalCols) {
            $rightStart = max($leftEnd + 1, $totalCols - 2);
        }
        $rightEnd = $totalCols;

        // Baris 1: Mengetahui vs Titimangsa
        $sheet->mergeCells($this->range($leftStart, $row, $leftEnd, $row));
        $sheet->setCellValue($this->cell($leftStart, $row), 'Mengetahui,');
        $sheet->mergeCells($this->range($rightStart, $row, $rightEnd, $row));
        $sheet->setCellValue($this->cell($rightStart, $row), $options['titimangsa']);

        // Baris 2: Jabatan Wali Kelas vs Guru Mapel
        $row++;
        $sheet->mergeCells($this->range($leftStart, $row, $leftEnd, $row));
        $sheet->setCellValue($this->cell($leftStart, $row), 'Wali Kelas ' . $teachingClass->class_name);
        $sheet->mergeCells($this->range($rightStart, $row, $rightEnd, $row));
        $sheet->setCellValue($this->cell($rightStart, $row), 'Guru Mata Pelajaran');

        // Ruang tanda tangan basah (menimpa baris jabatan di atas & nama bergaris bawah di bawah)
        if ($options['show_digital_signature'] ?? true) {
            $wkNorm = SignatureImageHelper::normalizeInk($teachingClass->homeroom_teacher_signature);
            $wkImg = $this->createTempImageFromBase64($wkNorm['data_uri'] ?? null);
            if ($wkImg) {
                $leftAnchor = max($leftStart, (int) floor(($leftStart + $leftEnd) / 2));
                $drawing = new Drawing();
                $drawing->setPath($wkImg);
                $drawing->setHeight(98);
                $drawing->setCoordinates($this->cell($leftAnchor, $row));
                $drawing->setOffsetY(2);
                $drawing->setWorksheet($sheet);
            }

            $guruNorm = SignatureImageHelper::normalizeInk($teachingClass->teacher?->signature_data);
            $guruImg = $this->createTempImageFromBase64($guruNorm['data_uri'] ?? null);
            if ($guruImg) {
                $rightAnchor = max($rightStart, (int) floor(($rightStart + $rightEnd) / 2) - ($rightEnd - $rightStart >= 3 ? 1 : 0));
                $drawing = new Drawing();
                $drawing->setPath($guruImg);
                $drawing->setHeight(98);
                $drawing->setCoordinates($this->cell($rightAnchor, $row));
                $drawing->setOffsetY(2);
                $drawing->setWorksheet($sheet);
            }
        }

        $nameRow = $row + 4;
        $sheet->mergeCells($this->range($leftStart, $nameRow, $leftEnd, $nameRow));
        $sheet->setCellValue($this->cell($leftStart, $nameRow), $teachingClass->homeroom_teacher_name ?: '....................................');
        $sheet->mergeCells($this->range($rightStart, $nameRow, $rightEnd, $nameRow));
        $sheet->setCellValue($this->cell($rightStart, $nameRow), $teachingClass->teacher->name ?? '....................................');

        $sheet->getStyle($this->range($leftStart, $nameRow, $leftEnd, $nameRow))->getFont()->setBold(true)->setUnderline(true);
        $sheet->getStyle($this->range($rightStart, $nameRow, $rightEnd, $nameRow))->getFont()->setBold(true)->setUnderline(true);

        $nipRow = $nameRow + 1;
        $sheet->mergeCells($this->range($leftStart, $nipRow, $leftEnd, $nipRow));
        $sheet->setCellValue($this->cell($leftStart, $nipRow), 'NIP. ' . ($teachingClass->homeroom_teacher_nip ?: '-'));
        $sheet->mergeCells($this->range($rightStart, $nipRow, $rightEnd, $nipRow));
        $sheet->setCellValue($this->cell($rightStart, $nipRow), 'NIP. ' . ($teachingClass->teacher->nip ?: '-'));

        $sheet->getStyle($this->range(1, $startRow + 2, $totalCols, $nipRow))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($options['show_headmaster'] ?? false) {
            $hmRow = $nipRow + 2;
            $sheet->mergeCells($this->range(1, $hmRow, $totalCols, $hmRow));
            $sheet->setCellValue($this->cell(1, $hmRow), 'Mengetahui / Mengesahkan,');
            $hmRow++;
            $sheet->mergeCells($this->range(1, $hmRow, $totalCols, $hmRow));
            $sheet->setCellValue($this->cell(1, $hmRow), 'Kepala ' . $school->school_name);

            if ($options['show_digital_signature'] ?? true) {
                $hmNorm = SignatureImageHelper::normalizeInk($school->headmaster_signature);
                $hmImg = $this->createTempImageFromBase64($hmNorm['data_uri'] ?? null);
                if ($hmImg) {
                    $drawing = new Drawing();
                    $drawing->setPath($hmImg);
                    $drawing->setHeight(98);
                    $drawing->setCoordinates($this->cell(max(1, (int) floor($totalCols / 2)), $hmRow));
                    $drawing->setOffsetY(2);
                    $drawing->setWorksheet($sheet);
                }
            }

            $hmNameRow = $hmRow + 4;
            $sheet->mergeCells($this->range(1, $hmNameRow, $totalCols, $hmNameRow));
            $sheet->setCellValue($this->cell(1, $hmNameRow), $school->headmaster_name ?: '....................................');
            $sheet->getStyle($this->cell(1, $hmNameRow))->getFont()->setBold(true)->setUnderline(true);

            $hmNipRow = $hmNameRow + 1;
            $sheet->mergeCells($this->range(1, $hmNipRow, $totalCols, $hmNipRow));
            $sheet->setCellValue($this->cell(1, $hmNipRow), 'NIP. ' . ($school->headmaster_nip ?: '-'));

            $sheet->getStyle($this->range(1, $nipRow + 2, $totalCols, $hmNipRow))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    protected function styleTableHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']],
            ],
        ]);
    }

    protected function styleTableBody(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['size' => 9.5],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']],
            ],
        ]);
    }

    public function populateJournalSheet(Worksheet $sheet, TeachingClass $teachingClass, SchoolSetting $school, array $options, array $input = []): void
    {
        $sheet->setTitle('Jurnal Harian');
        $this->applyPageSetup($sheet, $options);

        $query = DailyJournal::query()
            ->where('teaching_class_id', $teachingClass->id)
            ->orderBy('journal_date')
            ->orderBy('lesson_hours');

        $periodLabel = $teachingClass->academicYear->full_label;
        if (! empty($input['month']) && ! empty($input['year'])) {
            $m = (int) $input['month'];
            $y = (int) $input['year'];
            $query->whereYear('journal_date', $y)->whereMonth('journal_date', $m);
            $periodLabel = IndonesianDate::monthName($m) . ' ' . $y;
        }
        $journals = $query->get();

        $totalCols = 8;
        $row = $this->renderOfficialHeader(
            $sheet,
            $school,
            $teachingClass,
            'JURNAL HARIAN PELAKSANAAN PEMBELAJARAN',
            'Periode: ' . $periodLabel,
            $totalCols,
            $options
        );

        $h1 = $row;
        $h2 = $row + 1;

        $headers = [
            1 => 'No',
            2 => 'Tanggal',
            3 => 'Jam Pelajaran Ke',
            4 => 'Kegiatan / Materi Pembelajaran',
            7 => 'Catatan',
            8 => 'Paraf',
        ];
        foreach ($headers as $c => $label) {
            $sheet->mergeCells($this->range($c, $h1, $c, $h2));
            $sheet->setCellValue($this->cell($c, $h1), $label);
        }
        $sheet->mergeCells($this->range(5, $h1, 6, $h1));
        $sheet->setCellValue($this->cell(5, $h1), 'Jumlah Murid');
        $sheet->setCellValue($this->cell(5, $h2), 'Hadir');
        $sheet->setCellValue($this->cell(6, $h2), 'Tidak Hadir');

        $this->styleTableHeader($sheet, $this->range(1, $h1, $totalCols, $h2));

        $widths = [1 => 6, 2 => 20, 3 => 16, 4 => 45, 5 => 12, 6 => 13, 7 => 32, 8 => 15];
        foreach ($widths as $c => $w) {
            $sheet->getColumnDimension($this->col($c))->setWidth($w);
        }

        $dataStart = $h2 + 1;
        $curr = $dataStart;

        if ($journals->isEmpty()) {
            $sheet->mergeCells($this->range(1, $curr, $totalCols, $curr));
            $sheet->setCellValue($this->cell(1, $curr), 'Belum ada data Jurnal Harian.');
            $this->styleTableBody($sheet, $this->range(1, $curr, $totalCols, $curr));
            $sheet->getStyle($this->cell(1, $curr))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $curr++;
        } else {
            foreach ($journals as $idx => $journal) {
                $sheet->setCellValue($this->cell(1, $curr), $idx + 1);
                $sheet->setCellValue($this->cell(2, $curr), IndonesianDate::formatWithDay($journal->journal_date));
                $sheet->setCellValue($this->cell(3, $curr), $journal->lesson_hours);
                $sheet->setCellValue($this->cell(4, $curr), $journal->activity_material);
                $sheet->setCellValue($this->cell(5, $curr), $journal->present_count);
                $sheet->setCellValue($this->cell(6, $curr), $journal->absent_count);
                $noteText = trim(($journal->notes ?? '') . ($journal->attendance_breakdown ? ' (' . $journal->attendance_breakdown . ')' : ''));
                $sheet->setCellValue($this->cell(7, $curr), $noteText);

                $sheet->getRowDimension($curr)->setRowHeight(28);

                if ($options['show_journal_paraf'] ?? true) {
                    $parafData = $journal->signature_data ?: $teachingClass->teacher?->signature_data;
                    $parafNorm = SignatureImageHelper::normalizeInk($parafData);
                    $tmpParaf = $this->createTempImageFromBase64($parafNorm['data_uri'] ?? null);
                    if ($tmpParaf) {
                        $dr = new Drawing();
                        $dr->setPath($tmpParaf);
                        $dr->setHeight(34);
                        $dr->setCoordinates($this->cell(8, $curr));
                        $dr->setOffsetX(8);
                        $dr->setOffsetY(1);
                        $dr->setWorksheet($sheet);
                    } elseif (! empty($journal->signature_data)) {
                        $sheet->setCellValue($this->cell(8, $curr), 'Terparaf');
                    }
                }
                $curr++;
            }

            $dataEnd = $curr - 1;
            $this->styleTableBody($sheet, $this->range(1, $dataStart, $totalCols, $dataEnd));
            foreach ([1, 2, 3, 5, 6, 8] as $centerCol) {
                $sheet->getStyle($this->range($centerCol, $dataStart, $centerCol, $dataEnd))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $this->renderSignatureBlock($sheet, $school, $teachingClass, $curr, $totalCols, $options);
    }

    public function populateAttendanceMonthlySheet(Worksheet $sheet, TeachingClass $teachingClass, SchoolSetting $school, int $year, int $month, array $options): void
    {
        $monthLabel = IndonesianDate::monthName($month) . ' ' . $year;
        $sheet->setTitle(substr('Absensi ' . IndonesianDate::monthName($month), 0, 31));
        $this->applyPageSetup($sheet, $options);

        $matrix = $this->attendanceService->buildMonthlyMatrix($teachingClass, $year, $month);
        $daysInMonth = $matrix['days_in_month'];
        $totalCols = 4 + $daysInMonth + 4; // No, Nama Lengkap, Panggilan, L/P, 1..N, Jml Hadir, S, I, A

        $row = $this->renderOfficialHeader(
            $sheet,
            $school,
            $teachingClass,
            'DAFTAR HADIR (ABSENSI) PESERTA DIDIK',
            'Bulan: ' . $monthLabel,
            $totalCols,
            $options
        );

        $h1 = $row;
        $h2 = $row + 1;

        $fixedLeft = [1 => 'No', 2 => 'Nama Lengkap', 3 => 'Nama Panggilan', 4 => 'Jenis Kelamin'];
        foreach ($fixedLeft as $c => $lbl) {
            $sheet->mergeCells($this->range($c, $h1, $c, $h2));
            $sheet->setCellValue($this->cell($c, $h1), $lbl);
        }

        $dayStartCol = 5;
        $dayEndCol = 4 + $daysInMonth;
        $sheet->mergeCells($this->range($dayStartCol, $h1, $dayEndCol, $h1));
        $sheet->setCellValue($this->cell($dayStartCol, $h1), 'Bulan ' . $monthLabel . ' (Tanggal)');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $c = 4 + $d;
            $sheet->setCellValue($this->cell($c, $h2), $d);
            $sheet->getColumnDimension($this->col($c))->setWidth(3.6);
        }

        $hadirCol = $dayEndCol + 1;
        $sCol = $dayEndCol + 2;
        $iCol = $dayEndCol + 3;
        $aCol = $dayEndCol + 4;

        $sheet->mergeCells($this->range($hadirCol, $h1, $hadirCol, $h2));
        $sheet->setCellValue($this->cell($hadirCol, $h1), 'Jml Hadir');

        $sheet->mergeCells($this->range($sCol, $h1, $aCol, $h1));
        $sheet->setCellValue($this->cell($sCol, $h1), 'Ketidakhadiran');
        $sheet->setCellValue($this->cell($sCol, $h2), 'S');
        $sheet->setCellValue($this->cell($iCol, $h2), 'I');
        $sheet->setCellValue($this->cell($aCol, $h2), 'A');

        $this->styleTableHeader($sheet, $this->range(1, $h1, $totalCols, $h2));

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(10);
        $sheet->getColumnDimension($this->col($hadirCol))->setWidth(9);
        $sheet->getColumnDimension($this->col($sCol))->setWidth(5.5);
        $sheet->getColumnDimension($this->col($iCol))->setWidth(5.5);
        $sheet->getColumnDimension($this->col($aCol))->setWidth(5.5);

        $dataStart = $h2 + 1;
        $curr = $dataStart;

        foreach ($matrix['rows'] as $r) {
            $sheet->setCellValue($this->cell(1, $curr), $r['no']);
            $sheet->setCellValue($this->cell(2, $curr), $r['student']->full_name);
            $sheet->setCellValue($this->cell(3, $curr), $r['student']->nickname ?: '-');
            $sheet->setCellValue($this->cell(4, $curr), $r['student']->gender);

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $st = $r['days'][$d] ?? '';
                $c = 4 + $d;
                $sheet->setCellValue($this->cell($c, $curr), $st);
                if ($st === 'd') {
                    $sheet->getStyle($this->cell($c, $curr))->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F2FE');
                } elseif ($st === 'a') {
                    $sheet->getStyle($this->cell($c, $curr))->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
                }
            }

            $dayRange = $this->range($dayStartCol, $curr, $dayEndCol, $curr);
            // Rumus Excel Aktif: "." dan "d" sama-sama dihitung ke dalam Jumlah Hadir!
            $sheet->setCellValue($this->cell($hadirCol, $curr), "=COUNTIF({$dayRange},\".\")+COUNTIF({$dayRange},\"d\")");
            $sheet->setCellValue($this->cell($sCol, $curr), "=COUNTIF({$dayRange},\"s\")");
            $sheet->setCellValue($this->cell($iCol, $curr), "=COUNTIF({$dayRange},\"i\")");
            $sheet->setCellValue($this->cell($aCol, $curr), "=COUNTIF({$dayRange},\"a\")");
            $curr++;
        }

        if ($curr > $dataStart) {
            $dataEnd = $curr - 1;
            $this->styleTableBody($sheet, $this->range(1, $dataStart, $totalCols, $dataEnd));
            $sheet->getStyle($this->range(1, $dataStart, 1, $dataEnd))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($this->range(3, $dataStart, $totalCols, $dataEnd))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($this->range($hadirCol, $dataStart, $hadirCol, $dataEnd))->getFont()->setBold(true);
        }

        $sheet->mergeCells($this->range(1, $curr + 1, $totalCols, $curr + 1));
        $sheet->setCellValue(
            $this->cell(1, $curr + 1),
            'Keterangan: "." = Hadir | "d" = Dispen (dihitung Hadir) | "s" = Sakit | "i" = Izin | "a" = Alfa'
        );
        $sheet->getStyle($this->cell(1, $curr + 1))->getFont()->setItalic(true)->setSize(8.5);

        $this->renderSignatureBlock($sheet, $school, $teachingClass, $curr + 1, $totalCols, $options);
    }

    public function populateAttendanceRecapSheet(Worksheet $sheet, TeachingClass $teachingClass, SchoolSetting $school, array $options): void
    {
        $sheet->setTitle('Rekap Absensi');
        $this->applyPageSetup($sheet, $options);

        $recap = $this->attendanceService->buildSemesterRecap($teachingClass);
        $totalCols = 12;

        $row = $this->renderOfficialHeader(
            $sheet,
            $school,
            $teachingClass,
            'REKAPITULASI KEHADIRAN PESERTA DIDIK',
            $teachingClass->academicYear->full_label . ' (Total Pertemuan: ' . $recap['total_active_dates'] . ' Hari)',
            $totalCols,
            $options
        );

        $h1 = $row;
        $h2 = $row + 1;

        foreach ([1 => 'No', 2 => 'Nama Lengkap', 3 => 'Nama Panggilan', 4 => 'L/P', 12 => '% Kehadiran'] as $c => $lbl) {
            $sheet->mergeCells($this->range($c, $h1, $c, $h2));
            $sheet->setCellValue($this->cell($c, $h1), $lbl);
        }
        $sheet->mergeCells($this->range(5, $h1, 7, $h1));
        $sheet->setCellValue($this->cell(5, $h1), 'Rincian Kehadiran');
        $sheet->setCellValue($this->cell(5, $h2), 'Hadir (.)');
        $sheet->setCellValue($this->cell(6, $h2), 'Dispen (d)');
        $sheet->setCellValue($this->cell(7, $h2), 'Jml Hadir');

        $sheet->mergeCells($this->range(8, $h1, 11, $h1));
        $sheet->setCellValue($this->cell(8, $h1), 'Jumlah Ketidakhadiran');
        $sheet->setCellValue($this->cell(8, $h2), 'Sakit');
        $sheet->setCellValue($this->cell(9, $h2), 'Izin');
        $sheet->setCellValue($this->cell(10, $h2), 'Alfa');
        $sheet->setCellValue($this->cell(11, $h2), 'Total');

        $this->styleTableHeader($sheet, $this->range(1, $h1, $totalCols, $h2));

        $widths = [1 => 5, 2 => 28, 3 => 15, 4 => 7, 5 => 10, 6 => 11, 7 => 11, 8 => 9, 9 => 9, 10 => 9, 11 => 10, 12 => 13];
        foreach ($widths as $c => $w) {
            $sheet->getColumnDimension($this->col($c))->setWidth($w);
        }

        $dataStart = $h2 + 1;
        $curr = $dataStart;
        foreach ($recap['rows'] as $r) {
            $sheet->setCellValue($this->cell(1, $curr), $r['no']);
            $sheet->setCellValue($this->cell(2, $curr), $r['student']->full_name);
            $sheet->setCellValue($this->cell(3, $curr), $r['student']->nickname ?: '-');
            $sheet->setCellValue($this->cell(4, $curr), $r['student']->gender);
            $sheet->setCellValue($this->cell(5, $curr), $r['dot']);
            $sheet->setCellValue($this->cell(6, $curr), $r['dispen']);
            $sheet->setCellValue($this->cell(7, $curr), $r['present']);
            $sheet->setCellValue($this->cell(8, $curr), $r['sakit']);
            $sheet->setCellValue($this->cell(9, $curr), $r['izin']);
            $sheet->setCellValue($this->cell(10, $curr), $r['alfa']);
            $sheet->setCellValue($this->cell(11, $curr), $r['absent']);
            $sheet->setCellValue($this->cell(12, $curr), $r['percentage'] . '%');
            $curr++;
        }

        if ($curr > $dataStart) {
            $dataEnd = $curr - 1;
            $this->styleTableBody($sheet, $this->range(1, $dataStart, $totalCols, $dataEnd));
            $sheet->getStyle($this->range(1, $dataStart, 1, $dataEnd))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($this->range(3, $dataStart, $totalCols, $dataEnd))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->renderSignatureBlock($sheet, $school, $teachingClass, $curr, $totalCols, $options);
    }

    public function populateGradebookSheet(Worksheet $sheet, TeachingClass $teachingClass, SchoolSetting $school, array $options): void
    {
        $sheet->setTitle('Daftar Nilai');
        $this->applyPageSetup($sheet, $options);

        $gradebook = $this->gradeService->buildGradebookData($teachingClass, $this->attendanceService);
        $phCols = $gradebook['ph_columns'];
        $ntCols = $gradebook['nt_columns'];
        $w = $gradebook['weight'];
        $kktp = $gradebook['kktp'];

        $phCount = $phCols->count();
        $ntCount = $ntCols->count();
        $totalCols = 3 + $phCount + 1 + $ntCount + 4; // No, Nama, L/P, PH.., Kehadiran, NT.., STS, SAS, NA, Ketuntasan

        $subtitle = sprintf(
            'Rumus NA: PH (%s%%) + Kehadiran (%s%%) + NT (%s%%) + STS (%s%%) + SAS (%s%%) = 100%%',
            rtrim(rtrim(number_format($w->weight_ph, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_attendance, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_nt, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_sts, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_sas, 2), '0'), '.')
        );
        if ($w->isAutoAttendance()) {
            $subtitle .= sprintf(' | Kehadiran Otomatis (Toleransi S+I: %dx)', (int) ($w->sick_leave_tolerance ?? 3));
        }

        $row = $this->renderOfficialHeader(
            $sheet,
            $school,
            $teachingClass,
            'DAFTAR NILAI & CAPAIAN KOMPETENSI PESERTA DIDIK',
            $subtitle,
            $totalCols,
            $options
        );

        $h1 = $row;
        $h2 = $row + 1;

        foreach ([1 => 'No', 2 => 'Nama Lengkap', 3 => 'Jenis Kelamin'] as $c => $lbl) {
            $sheet->mergeCells($this->range($c, $h1, $c, $h2));
            $sheet->setCellValue($this->cell($c, $h1), $lbl);
        }

        $cursor = 4;
        $phStartCol = null;
        $phEndCol = null;
        if ($phCount > 0) {
            $phStartCol = $cursor;
            $phEndCol = $cursor + $phCount - 1;
            $sheet->mergeCells($this->range($phStartCol, $h1, $phEndCol, $h1));
            $sheet->setCellValue($this->cell($phStartCol, $h1), 'Nilai Harian / Keaktifan / Karakter');
            foreach ($phCols as $col) {
                $sheet->setCellValue($this->cell($cursor, $h2), $col->label);
                $sheet->getColumnDimension($this->col($cursor))->setWidth(9);
                $cursor++;
            }
        }

        $attCol = $cursor++;
        $sheet->mergeCells($this->range($attCol, $h1, $attCol, $h2));
        $sheet->setCellValue($this->cell($attCol, $h1), 'Nilai Kehadiran');
        $sheet->getColumnDimension($this->col($attCol))->setWidth(12);

        $ntStartCol = null;
        $ntEndCol = null;
        if ($ntCount > 0) {
            $ntStartCol = $cursor;
            $ntEndCol = $cursor + $ntCount - 1;
            $sheet->mergeCells($this->range($ntStartCol, $h1, $ntEndCol, $h1));
            $sheet->setCellValue($this->cell($ntStartCol, $h1), 'Nilai Tugas');
            foreach ($ntCols as $col) {
                $sheet->setCellValue($this->cell($cursor, $h2), $col->label);
                $sheet->getColumnDimension($this->col($cursor))->setWidth(9);
                $cursor++;
            }
        }

        $stsCol = $cursor++;
        $sasCol = $cursor++;
        $naCol = $cursor++;
        $statusCol = $cursor;

        foreach ([
            $stsCol => ['Nilai STS', 10],
            $sasCol => ['Nilai SAS', 10],
            $naCol => ['NA (Nilai Akhir)', 13],
            $statusCol => ["Ketuntasan (KKTP {$kktp})", 16],
        ] as $c => [$lbl, $width]) {
            $sheet->mergeCells($this->range($c, $h1, $c, $h2));
            $sheet->setCellValue($this->cell($c, $h1), $lbl);
            $sheet->getColumnDimension($this->col($c))->setWidth($width);
        }

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(29);
        $sheet->getColumnDimension('C')->setWidth(11);

        $this->styleTableHeader($sheet, $this->range(1, $h1, $totalCols, $h2));

        $dataStart = $h2 + 1;
        $curr = $dataStart;

        foreach ($gradebook['rows'] as $r) {
            $sheet->setCellValue($this->cell(1, $curr), $r['no']);
            $sheet->setCellValue($this->cell(2, $curr), $r['student']->full_name);
            $sheet->setCellValue($this->cell(3, $curr), $r['student']->gender);

            $c = 4;
            foreach ($phCols as $col) {
                $val = $r['dynamic_scores'][(string) $col->id] ?? null;
                if ($val !== null) {
                    $sheet->setCellValue($this->cell($c, $curr), $val);
                }
                $c++;
            }

            if ($r['attendance_score'] !== null) {
                $sheet->setCellValue($this->cell($attCol, $curr), $r['attendance_score']);
            }

            $c = $attCol + 1;
            foreach ($ntCols as $col) {
                $val = $r['dynamic_scores'][(string) $col->id] ?? null;
                if ($val !== null) {
                    $sheet->setCellValue($this->cell($c, $curr), $val);
                }
                $c++;
            }

            if ($r['sts_score'] !== null) {
                $sheet->setCellValue($this->cell($stsCol, $curr), $r['sts_score']);
            }
            if ($r['sas_score'] !== null) {
                $sheet->setCellValue($this->cell($sasCol, $curr), $r['sas_score']);
            }

            // Rumus Excel aktif untuk NA (sel kosong dianggap 0 sesuai aturan A5)
            $phExpr = ($phCount > 0 && $phStartCol && $phEndCol)
                ? "(SUM(" . $this->range($phStartCol, $curr, $phEndCol, $curr) . ")/{$phCount})"
                : '0';
            $ntExpr = ($ntCount > 0 && $ntStartCol && $ntEndCol)
                ? "(SUM(" . $this->range($ntStartCol, $curr, $ntEndCol, $curr) . ")/{$ntCount})"
                : '0';

            $attCell = $this->cell($attCol, $curr);
            $stsCell = $this->cell($stsCol, $curr);
            $sasCell = $this->cell($sasCol, $curr);
            $naCell = $this->cell($naCol, $curr);
            $precision = (int) $w->rounding_precision;

            $formula = sprintf(
                '=ROUND((%s*%s/100)+(%s*%s/100)+(%s*%s/100)+(%s*%s/100)+(%s*%s/100),%d)',
                $phExpr,
                $w->weight_ph,
                $attCell,
                $w->weight_attendance,
                $ntExpr,
                $w->weight_nt,
                $stsCell,
                $w->weight_sts,
                $sasCell,
                $w->weight_sas,
                $precision
            );

            $sheet->setCellValue($naCell, $formula);
            $sheet->setCellValue($this->cell($statusCol, $curr), "=IF({$naCell}>={$kktp},\"Tuntas\",\"Remedial\")");

            if (! $r['is_tuntas']) {
                $sheet->getStyle($this->range($naCol, $curr, $statusCol, $curr))->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '991B1B']],
                ]);
            } else {
                $sheet->getStyle($this->range($naCol, $curr, $statusCol, $curr))->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
                ]);
            }

            $curr++;
        }

        if ($curr > $dataStart) {
            $dataEnd = $curr - 1;
            $this->styleTableBody($sheet, $this->range(1, $dataStart, $totalCols, $dataEnd));
            $sheet->getStyle($this->range(1, $dataStart, 1, $dataEnd))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($this->range(3, $dataStart, $totalCols, $dataEnd))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->renderSignatureBlock($sheet, $school, $teachingClass, $curr, $totalCols, $options);
    }

    public function exportJournal(TeachingClass $teachingClass, array $input = []): StreamedResponse
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->pdfService->normalizeOptions($input, $school, 'landscape');

        $spreadsheet = new Spreadsheet();
        $this->populateJournalSheet($spreadsheet->getActiveSheet(), $teachingClass, $school, $options, $input);

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $this->streamSpreadsheet($spreadsheet, "Jurnal_Harian_{$safeClass}.xlsx");
    }

    public function exportAttendanceMonthly(TeachingClass $teachingClass, int $year, int $month, array $input = []): StreamedResponse
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->pdfService->normalizeOptions($input, $school, 'landscape');

        $spreadsheet = new Spreadsheet();
        $this->populateAttendanceMonthlySheet($spreadsheet->getActiveSheet(), $teachingClass, $school, $year, $month, $options);

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);
        $mName = IndonesianDate::monthName($month);

        return $this->streamSpreadsheet($spreadsheet, "Absensi_{$safeClass}_{$mName}_{$year}.xlsx");
    }

    public function exportAttendanceRecap(TeachingClass $teachingClass, array $input = []): StreamedResponse
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->pdfService->normalizeOptions($input, $school, 'portrait');

        $spreadsheet = new Spreadsheet();
        $this->populateAttendanceRecapSheet($spreadsheet->getActiveSheet(), $teachingClass, $school, $options);

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $this->streamSpreadsheet($spreadsheet, "Rekap_Absensi_Semester_{$safeClass}.xlsx");
    }

    public function exportGradebook(TeachingClass $teachingClass, array $input = []): StreamedResponse
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->pdfService->normalizeOptions($input, $school, 'landscape');

        $spreadsheet = new Spreadsheet();
        $this->populateGradebookSheet($spreadsheet->getActiveSheet(), $teachingClass, $school, $options);

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $this->streamSpreadsheet($spreadsheet, "Daftar_Nilai_{$safeClass}.xlsx");
    }

    public function exportBundle(TeachingClass $teachingClass, int $year, int $month, array $input = []): StreamedResponse
    {
        $teachingClass->loadMissing(['teacher', 'subject.kktps', 'academicYear']);
        $school = SchoolSetting::current();
        $options = $this->pdfService->normalizeOptions($input, $school, 'landscape');

        $spreadsheet = new Spreadsheet();
        $this->populateJournalSheet($spreadsheet->getActiveSheet(), $teachingClass, $school, $options, $input);

        $attSheet = $spreadsheet->createSheet();
        $this->populateAttendanceMonthlySheet($attSheet, $teachingClass, $school, $year, $month, $options);

        $recapSheet = $spreadsheet->createSheet();
        $this->populateAttendanceRecapSheet($recapSheet, $teachingClass, $school, $options);

        $gradeSheet = $spreadsheet->createSheet();
        $this->populateGradebookSheet($gradeSheet, $teachingClass, $school, $options);

        $spreadsheet->setActiveSheetIndex(0);
        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $this->streamSpreadsheet($spreadsheet, "Bundel_Administrasi_Lengkap_{$safeClass}.xlsx");
    }

    /**
     * Unduh Template Excel Daftar Murid untuk Kelas.
     */
    public function downloadStudentTemplate(TeachingClass $teachingClass): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Daftar Murid');

        $headers = ['No', 'NIS / NISN', 'Nama Lengkap', 'Nama Panggilan', 'Jenis Kelamin (L/P)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->cell($i + 1, 1), $h);
        }
        $this->styleTableHeader($sheet, 'A1:E1');

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(32);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(20);

        $students = $teachingClass->activeStudents()->get();
        if ($students->isNotEmpty()) {
            foreach ($students as $idx => $st) {
                $r = $idx + 2;
                $sheet->setCellValue("A{$r}", $idx + 1);
                $sheet->setCellValue("B{$r}", $st->nis_nisn);
                $sheet->setCellValue("C{$r}", $st->full_name);
                $sheet->setCellValue("D{$r}", $st->nickname);
                $sheet->setCellValue("E{$r}", $st->gender);
            }
        } else {
            $sheet->setCellValue('A2', 1);
            $sheet->setCellValue('B2', '009812001');
            $sheet->setCellValue('C2', 'Ahmad Fauzan Pratama');
            $sheet->setCellValue('D2', 'Fauzan');
            $sheet->setCellValue('E2', 'L');

            $sheet->setCellValue('A3', 2);
            $sheet->setCellValue('B3', '009812002');
            $sheet->setCellValue('C3', 'Siti Aisyah Putri');
            $sheet->setCellValue('D3', 'Aisyah');
            $sheet->setCellValue('E3', 'P');
        }

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $this->streamSpreadsheet($spreadsheet, "Template_Murid_{$safeClass}.xlsx");
    }

    /**
     * Import Daftar Murid dari file Excel (.xlsx / .xls / .csv).
     */
    public function importStudents(TeachingClass $teachingClass, string $filePath, bool $replaceExisting = false): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $imported = 0;

        DB::transaction(function () use ($teachingClass, $rows, $replaceExisting, &$imported) {
            if ($replaceExisting) {
                $teachingClass->students()->delete();
            }

            $nextOrder = (int) ($teachingClass->students()->max('order_number') ?? 0);

            foreach ($rows as $idx => $row) {
                if ($idx === 0) {
                    continue; // Header baris 1
                }

                $fullName = trim((string) ($row[2] ?? ''));
                if ($fullName === '' || strtolower($fullName) === 'nama lengkap') {
                    continue;
                }

                $orderNo = is_numeric($row[0] ?? null) ? (int) $row[0] : (++$nextOrder);
                $nisNisn = trim((string) ($row[1] ?? '')) ?: null;
                $nickname = trim((string) ($row[3] ?? '')) ?: null;
                $rawGender = strtoupper(trim((string) ($row[4] ?? 'L')));
                $gender = str_starts_with($rawGender, 'P') ? 'P' : 'L';

                ClassStudent::query()->create([
                    'school_class_id' => $teachingClass->school_class_id,
                    'teaching_class_id' => $teachingClass->id,
                    'order_number' => $orderNo,
                    'nis_nisn' => $nisNisn,
                    'full_name' => $fullName,
                    'nickname' => $nickname,
                    'gender' => $gender,
                    'is_active' => true,
                ]);
                $imported++;
            }
        });

        return $imported;
    }

    /**
     * Unduh Template Excel Daftar Murid untuk Master Rombel (Admin).
     */
    public function downloadStudentTemplateForSchoolClass(SchoolClass $schoolClass): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Murid ' . $schoolClass->name);

        $headers = ['No', 'NIS / NISN', 'Nama Lengkap', 'Nama Panggilan', 'Jenis Kelamin (L/P)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->cell($i + 1, 1), $h);
        }
        $this->styleTableHeader($sheet, 'A1:E1');

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(32);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(20);

        $students = $schoolClass->activeStudents()->get();
        if ($students->isNotEmpty()) {
            foreach ($students as $idx => $st) {
                $r = $idx + 2;
                $sheet->setCellValue("A{$r}", $idx + 1);
                $sheet->setCellValue("B{$r}", $st->nis_nisn);
                $sheet->setCellValue("C{$r}", $st->full_name);
                $sheet->setCellValue("D{$r}", $st->nickname);
                $sheet->setCellValue("E{$r}", $st->gender);
            }
        } else {
            $sheet->setCellValue('A2', 1);
            $sheet->setCellValue('B2', '009812001');
            $sheet->setCellValue('C2', 'Ahmad Fauzan Pratama');
            $sheet->setCellValue('D2', 'Fauzan');
            $sheet->setCellValue('E2', 'L');

            $sheet->setCellValue('A3', 2);
            $sheet->setCellValue('B3', '009812002');
            $sheet->setCellValue('C3', 'Siti Aisyah Putri');
            $sheet->setCellValue('D3', 'Aisyah');
            $sheet->setCellValue('E3', 'P');
        }

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $schoolClass->name);

        return $this->streamSpreadsheet($spreadsheet, "Template_Murid_Rombel_{$safeClass}.xlsx");
    }

    /**
     * Import Daftar Murid dari file Excel (.xlsx / .xls / .csv) untuk Master Rombel (Admin).
     */
    public function importStudentsForSchoolClass(SchoolClass $schoolClass, string $filePath, bool $replaceExisting = false): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $imported = 0;

        DB::transaction(function () use ($schoolClass, $rows, $replaceExisting, &$imported) {
            if ($replaceExisting) {
                $schoolClass->students()->delete();
            }

            $nextOrder = (int) ($schoolClass->students()->max('order_number') ?? 0);
            $firstTcId = $schoolClass->teachingClasses()->value('id');

            foreach ($rows as $idx => $row) {
                if ($idx === 0) {
                    continue; // Header baris 1
                }

                $fullName = trim((string) ($row[2] ?? ''));
                if ($fullName === '' || strtolower($fullName) === 'nama lengkap') {
                    continue;
                }

                $orderNo = is_numeric($row[0] ?? null) ? (int) $row[0] : (++$nextOrder);
                $nisNisn = trim((string) ($row[1] ?? '')) ?: null;
                $nickname = trim((string) ($row[3] ?? '')) ?: null;
                $rawGender = strtoupper(trim((string) ($row[4] ?? 'L')));
                $gender = str_starts_with($rawGender, 'P') ? 'P' : 'L';

                ClassStudent::query()->create([
                    'school_class_id' => $schoolClass->id,
                    'teaching_class_id' => $firstTcId,
                    'order_number' => $orderNo,
                    'nis_nisn' => $nisNisn,
                    'full_name' => $fullName,
                    'nickname' => $nickname,
                    'gender' => $gender,
                    'is_active' => true,
                ]);
                $imported++;
            }
        });

        return $imported;
    }

    /**
     * Unduh Template Excel Nilai sesuai struktur kolom PH & NT dinamis kelas tersebut.
     */
    public function downloadGradeTemplate(TeachingClass $teachingClass): StreamedResponse
    {
        $teachingClass->ensureDefaultsInitialized();
        $phCols = $teachingClass->phColumns()->get();
        $ntCols = $teachingClass->ntColumns()->get();
        $students = $teachingClass->activeStudents()->get();
        $grades = $teachingClass->studentGrades()->get()->keyBy('class_student_id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Nilai');

        $headers = ['No', 'ID_MURID', 'Nama Lengkap', 'L/P'];
        foreach ($phCols as $col) {
            $headers[] = $col->label;
        }
        $headers[] = 'Nilai Kehadiran';
        foreach ($ntCols as $col) {
            $headers[] = $col->label;
        }
        $headers[] = 'Nilai STS';
        $headers[] = 'Nilai SAS';

        foreach ($headers as $idx => $h) {
            $c = $idx + 1;
            $sheet->setCellValue($this->cell($c, 1), $h);
            $sheet->getColumnDimension($this->col($c))->setWidth($c === 3 ? 30 : ($c <= 4 ? 10 : 14));
        }
        $this->styleTableHeader($sheet, $this->range(1, 1, count($headers), 1));

        foreach ($students as $sIdx => $student) {
            $r = $sIdx + 2;
            $g = $grades->get($student->id);
            $dyn = $g?->dynamic_scores ?? [];

            $c = 1;
            $sheet->setCellValue($this->cell($c++, $r), $sIdx + 1);
            $sheet->setCellValue($this->cell($c++, $r), $student->id);
            $sheet->setCellValue($this->cell($c++, $r), $student->full_name);
            $sheet->setCellValue($this->cell($c++, $r), $student->gender);

            foreach ($phCols as $col) {
                $val = $dyn[(string) $col->id] ?? null;
                if ($val !== null && $val !== '') {
                    $sheet->setCellValue($this->cell($c, $r), (float) $val);
                }
                $c++;
            }

            if ($g?->attendance_score !== null) {
                $sheet->setCellValue($this->cell($c, $r), (float) $g->attendance_score);
            }
            $c++;

            foreach ($ntCols as $col) {
                $val = $dyn[(string) $col->id] ?? null;
                if ($val !== null && $val !== '') {
                    $sheet->setCellValue($this->cell($c, $r), (float) $val);
                }
                $c++;
            }

            if ($g?->sts_score !== null) {
                $sheet->setCellValue($this->cell($c, $r), (float) $g->sts_score);
            }
            $c++;

            if ($g?->sas_score !== null) {
                $sheet->setCellValue($this->cell($c, $r), (float) $g->sas_score);
            }
        }

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $this->streamSpreadsheet($spreadsheet, "Template_Input_Nilai_{$safeClass}.xlsx");
    }

    /**
     * Import Nilai dari file Excel yang sesuai template dinamis kelas tersebut.
     */
    public function importGrades(TeachingClass $teachingClass, string $filePath): int
    {
        $teachingClass->ensureDefaultsInitialized();
        $phCols = $teachingClass->phColumns()->get()->values();
        $ntCols = $teachingClass->ntColumns()->get()->values();
        $students = $teachingClass->activeStudents()->get();
        $studentsById = $students->keyBy('id');
        $studentsByName = $students->keyBy(fn ($s) => mb_strtolower(trim($s->full_name)));

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $updated = 0;
        DB::transaction(function () use ($teachingClass, $rows, $phCols, $ntCols, $studentsById, $studentsByName, &$updated) {
            foreach ($rows as $idx => $row) {
                if ($idx === 0) {
                    continue;
                }

                $rawId = isset($row[1]) && is_numeric($row[1]) ? (int) $row[1] : null;
                $rawName = mb_strtolower(trim((string) ($row[2] ?? '')));

                $student = ($rawId && $studentsById->has($rawId))
                    ? $studentsById->get($rawId)
                    : ($rawName !== '' ? $studentsByName->get($rawName) : null);

                if (! $student) {
                    continue;
                }

                $cursor = 4;
                $dyn = [];
                foreach ($phCols as $col) {
                    $cellVal = $row[$cursor++] ?? null;
                    $dyn[(string) $col->id] = ($cellVal !== null && $cellVal !== '' && is_numeric($cellVal))
                        ? max(0, min(100, (float) $cellVal))
                        : null;
                }

                $rawAtt = $row[$cursor++] ?? null;
                $attScore = ($rawAtt !== null && $rawAtt !== '' && is_numeric($rawAtt))
                    ? max(0, min(100, (float) $rawAtt))
                    : null;

                foreach ($ntCols as $col) {
                    $cellVal = $row[$cursor++] ?? null;
                    $dyn[(string) $col->id] = ($cellVal !== null && $cellVal !== '' && is_numeric($cellVal))
                        ? max(0, min(100, (float) $cellVal))
                        : null;
                }

                $rawSts = $row[$cursor++] ?? null;
                $stsScore = ($rawSts !== null && $rawSts !== '' && is_numeric($rawSts))
                    ? max(0, min(100, (float) $rawSts))
                    : null;

                $rawSas = $row[$cursor++] ?? null;
                $sasScore = ($rawSas !== null && $rawSas !== '' && is_numeric($rawSas))
                    ? max(0, min(100, (float) $rawSas))
                    : null;

                StudentGrade::query()->updateOrCreate(
                    [
                        'teaching_class_id' => $teachingClass->id,
                        'class_student_id' => $student->id,
                    ],
                    [
                        'attendance_score' => $attScore,
                        'sts_score' => $stsScore,
                        'sas_score' => $sasScore,
                        'dynamic_scores' => $dyn,
                    ]
                );
                $updated++;
            }
        });

        $this->gradeService->recalculateClass($teachingClass);

        return $updated;
    }

    /**
     * Unduh Template Excel Absensi Bulanan & Import Absensi Bulanan.
     */
    public function downloadAttendanceTemplate(TeachingClass $teachingClass, int $year, int $month): StreamedResponse
    {
        $matrix = $this->attendanceService->buildMonthlyMatrix($teachingClass, $year, $month);
        $daysInMonth = $matrix['days_in_month'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Absensi');

        $headers = ['No', 'ID_MURID', 'Nama Lengkap', 'Nama Panggilan', 'L/P'];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $headers[] = (string) $d;
        }

        foreach ($headers as $idx => $h) {
            $c = $idx + 1;
            $sheet->setCellValue($this->cell($c, 1), $h);
            $sheet->getColumnDimension($this->col($c))->setWidth($c === 3 ? 28 : ($c <= 5 ? 12 : 4.2));
        }
        $this->styleTableHeader($sheet, $this->range(1, 1, count($headers), 1));

        foreach ($matrix['rows'] as $rIdx => $r) {
            $rowNo = $rIdx + 2;
            $sheet->setCellValue($this->cell(1, $rowNo), $r['no']);
            $sheet->setCellValue($this->cell(2, $rowNo), $r['student']->id);
            $sheet->setCellValue($this->cell(3, $rowNo), $r['student']->full_name);
            $sheet->setCellValue($this->cell(4, $rowNo), $r['student']->nickname);
            $sheet->setCellValue($this->cell(5, $rowNo), $r['student']->gender);

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $sheet->setCellValue($this->cell(5 + $d, $rowNo), $r['days'][$d] ?? '');
            }
        }

        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);
        $mName = IndonesianDate::monthName($month);

        return $this->streamSpreadsheet($spreadsheet, "Template_Absensi_{$safeClass}_{$mName}_{$year}.xlsx");
    }

    public function importAttendance(TeachingClass $teachingClass, int $year, int $month, string $filePath): int
    {
        $daysInMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $students = $teachingClass->activeStudents()->get();
        $studentsById = $students->keyBy('id');
        $studentsByName = $students->keyBy(fn ($s) => mb_strtolower(trim($s->full_name)));

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $affectedDates = [];
        $updatedRows = 0;

        DB::transaction(function () use ($teachingClass, $year, $month, $daysInMonth, $rows, $studentsById, $studentsByName, &$affectedDates, &$updatedRows) {
            foreach ($rows as $idx => $row) {
                if ($idx === 0) {
                    continue;
                }

                $rawId = isset($row[1]) && is_numeric($row[1]) ? (int) $row[1] : null;
                $rawName = mb_strtolower(trim((string) ($row[2] ?? '')));
                $student = ($rawId && $studentsById->has($rawId))
                    ? $studentsById->get($rawId)
                    : ($rawName !== '' ? $studentsByName->get($rawName) : null);

                if (! $student) {
                    continue;
                }

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $rawVal = strtolower(trim((string) ($row[4 + $d] ?? '')));
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);

                    if (in_array($rawVal, Attendance::VALID_STATUSES, true)) {
                        Attendance::query()->updateOrCreate(
                            [
                                'teaching_class_id' => $teachingClass->id,
                                'class_student_id' => $student->id,
                                'attendance_date' => $dateStr,
                            ],
                            ['status' => $rawVal]
                        );
                        $affectedDates[$dateStr] = true;
                    } elseif ($rawVal === '') {
                        Attendance::query()
                            ->where('teaching_class_id', $teachingClass->id)
                            ->where('class_student_id', $student->id)
                            ->whereDate('attendance_date', $dateStr)
                            ->delete();
                        $affectedDates[$dateStr] = true;
                    }
                }
                $updatedRows++;
            }
        });

        foreach (array_keys($affectedDates) as $dateStr) {
            $this->attendanceService->syncJournalForDate($teachingClass, $dateStr);
        }

        if ($teachingClass->gradeWeight()->first()?->isAutoAttendance()) {
            $this->gradeService->recalculateClass($teachingClass, $this->attendanceService);
        }

        return $updatedRows;
    }
}
