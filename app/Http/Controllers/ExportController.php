<?php

namespace App\Http\Controllers;

use App\Models\TeachingClass;
use App\Services\ExcelExportImportService;
use App\Services\PdfExportService;
use App\Support\IndonesianDate;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function journalPdf(Request $request, TeachingClass $teachingClass, PdfExportService $pdfService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $pdf = $pdfService->generateJournalPdf($teachingClass, $request->all());
        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $request->boolean('download')
            ? $pdf->download("Jurnal_Harian_{$safeClass}.pdf")
            : $pdf->stream("Jurnal_Harian_{$safeClass}.pdf");
    }

    public function journalExcel(Request $request, TeachingClass $teachingClass, ExcelExportImportService $excelService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        return $excelService->exportJournal($teachingClass, $request->all());
    }

    public function attendanceMonthlyPdf(Request $request, TeachingClass $teachingClass, PdfExportService $pdfService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $month = max(1, min(12, $request->integer('month') ?: (int) now()->month));
        $year = max(2020, min(2099, $request->integer('year') ?: (int) now()->year));

        $pdf = $pdfService->generateAttendanceMonthlyPdf($teachingClass, $year, $month, $request->all());
        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);
        $mName = IndonesianDate::monthName($month);

        return $request->boolean('download')
            ? $pdf->download("Absensi_{$safeClass}_{$mName}_{$year}.pdf")
            : $pdf->stream("Absensi_{$safeClass}_{$mName}_{$year}.pdf");
    }

    public function attendanceMonthlyExcel(Request $request, TeachingClass $teachingClass, ExcelExportImportService $excelService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $month = max(1, min(12, $request->integer('month') ?: (int) now()->month));
        $year = max(2020, min(2099, $request->integer('year') ?: (int) now()->year));

        return $excelService->exportAttendanceMonthly($teachingClass, $year, $month, $request->all());
    }

    public function attendanceRecapPdf(Request $request, TeachingClass $teachingClass, PdfExportService $pdfService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $pdf = $pdfService->generateAttendanceRecapPdf($teachingClass, $request->all());
        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $request->boolean('download')
            ? $pdf->download("Rekap_Absensi_Semester_{$safeClass}.pdf")
            : $pdf->stream("Rekap_Absensi_Semester_{$safeClass}.pdf");
    }

    public function attendanceRecapExcel(Request $request, TeachingClass $teachingClass, ExcelExportImportService $excelService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        return $excelService->exportAttendanceRecap($teachingClass, $request->all());
    }

    public function gradebookPdf(Request $request, TeachingClass $teachingClass, PdfExportService $pdfService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $pdf = $pdfService->generateGradebookPdf($teachingClass, $request->all());
        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $request->boolean('download')
            ? $pdf->download("Daftar_Nilai_{$safeClass}.pdf")
            : $pdf->stream("Daftar_Nilai_{$safeClass}.pdf");
    }

    public function gradebookExcel(Request $request, TeachingClass $teachingClass, ExcelExportImportService $excelService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        return $excelService->exportGradebook($teachingClass, $request->all());
    }

    public function bundlePdf(Request $request, TeachingClass $teachingClass, PdfExportService $pdfService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $month = max(1, min(12, $request->integer('month') ?: (int) now()->month));
        $year = max(2020, min(2099, $request->integer('year') ?: (int) now()->year));

        $pdf = $pdfService->generateBundlePdf($teachingClass, $year, $month, $request->all());
        $safeClass = preg_replace('/[^A-Za-z0-9\-_]/', '_', $teachingClass->class_name);

        return $request->boolean('download')
            ? $pdf->download("Bundel_Administrasi_Lengkap_{$safeClass}.pdf")
            : $pdf->stream("Bundel_Administrasi_Lengkap_{$safeClass}.pdf");
    }

    public function bundleExcel(Request $request, TeachingClass $teachingClass, ExcelExportImportService $excelService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        $month = max(1, min(12, $request->integer('month') ?: (int) now()->month));
        $year = max(2020, min(2099, $request->integer('year') ?: (int) now()->year));

        return $excelService->exportBundle($teachingClass, $year, $month, $request->all());
    }
}
