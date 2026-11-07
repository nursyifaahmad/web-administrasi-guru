<?php

namespace App\Http\Controllers;

use App\Models\GradeColumn;
use App\Models\GradeWeight;
use App\Models\StudentGrade;
use App\Models\TeachingClass;
use App\Services\AttendanceJournalSyncService;
use App\Services\ExcelExportImportService;
use App\Services\GradeCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GradebookController extends Controller
{
    public function toggleAdminAccess(Request $request, TeachingClass $teachingClass): RedirectResponse
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_unless(
            (int) $teachingClass->teacher_id === (int) $request->user()->id,
            403,
            'Hanya guru pengampu yang berhak mengubah izin akses nilai untuk Administrator.'
        );

        $newStatus = ! (bool) $teachingClass->is_grade_shared_with_admin;
        $teachingClass->update([
            'is_grade_shared_with_admin' => $newStatus,
        ]);

        $msg = $newStatus
            ? 'Izin akses detail nilai untuk Administrator/Wakasek berhasil DIBUKA (Mode Hanya Lihat).'
            : 'Izin akses detail nilai untuk Administrator/Wakasek berhasil DIKUNCI (Privat).';

        return back()->with('success', $msg);
    }

    public function index(
        Request $request,
        TeachingClass $teachingClass,
        GradeCalculationService $gradeService,
        AttendanceJournalSyncService $attendanceService
    ): View {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);

        $isGradeLockedForAdmin = $user->isAdmin() && (int) $teachingClass->teacher_id !== (int) $user->id && ! (bool) $teachingClass->is_grade_shared_with_admin;

        $gradebook = $gradeService->buildGradebookData($teachingClass, $attendanceService);

        $siblingClassesQuery = TeachingClass::query()
            ->with(['subject', 'academicYear'])
            ->where('id', '!=', $teachingClass->id)
            ->where('academic_year_id', $teachingClass->academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('class_name');

        if (! $user->isAdmin()) {
            $siblingClassesQuery->where('teacher_id', $user->id);
        } else {
            $siblingClassesQuery->where('teacher_id', $teachingClass->teacher_id);
        }

        $alpineRows = [];
        if (! $isGradeLockedForAdmin) {
            foreach ($gradebook['rows'] as $r) {
                $st = $r['student'];
                $alpineRows[] = [
                    'student_id' => $st->id,
                    'no' => $r['no'],
                    'full_name' => $st->full_name,
                    'gender' => $st->gender,
                    'dynamic_scores' => $r['dynamic_scores'],
                    'attendance_score' => $r['attendance_score'],
                    'sts_score' => $r['sts_score'],
                    'sas_score' => $r['sas_score'],
                    'attendance_ref' => $r['attendance_ref'],
                ];
            }
        }

        return view('workspace.grades.index', [
            'teachingClass' => $teachingClass,
            'gradebook' => $gradebook,
            'alpineRows' => $alpineRows,
            'siblingClasses' => $siblingClassesQuery->get(),
            'isGradeLockedForAdmin' => $isGradeLockedForAdmin,
            'isAdminReadOnly' => $user->isAdmin() && (int) $teachingClass->teacher_id !== (int) $user->id,
        ]);
    }

    public function bulkSaveScores(
        Request $request,
        TeachingClass $teachingClass,
        GradeCalculationService $gradeService,
        AttendanceJournalSyncService $attendanceService
    ): JsonResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek tidak memiliki izin untuk mengubah nilai guru lain.'
        );

        $teachingClass->ensureDefaultsInitialized();

        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.student_id' => ['required', 'integer'],
            'rows.*.dynamic_scores' => ['nullable', 'array'],
            'rows.*.attendance_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.sts_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.sas_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $validStudentIds = $teachingClass->activeStudents()->pluck('id')->flip();
        $phColumns = $teachingClass->phColumns()->get();
        $ntColumns = $teachingClass->ntColumns()->get();
        $allColumnIds = $phColumns->concat($ntColumns)->pluck('id')->map(fn ($id) => (string) $id)->flip();
        $weight = $teachingClass->gradeWeight()->firstOrFail();

        $autoAttendanceMap = [];
        if ($weight->isAutoAttendance()) {
            $autoAttendanceMap = $attendanceService->buildToleranceMapForClass(
                $teachingClass,
                (int) ($weight->sick_leave_tolerance ?? 3)
            );
        }

        $syncedAttendanceScores = [];

        DB::transaction(function () use (
            $teachingClass,
            $validated,
            $validStudentIds,
            $allColumnIds,
            $phColumns,
            $ntColumns,
            $weight,
            $gradeService,
            $autoAttendanceMap,
            &$syncedAttendanceScores
        ) {
            foreach ($validated['rows'] as $row) {
                $sid = (int) $row['student_id'];
                if (! $validStudentIds->has($sid)) {
                    continue;
                }

                $cleanDynamic = [];
                foreach (($row['dynamic_scores'] ?? []) as $colId => $val) {
                    $key = (string) $colId;
                    if (! $allColumnIds->has($key)) {
                        continue;
                    }
                    if ($val !== null && $val !== '' && is_numeric($val)) {
                        $num = (float) $val;
                        if ($num < 0 || $num > 100) {
                            abort(422, 'Seluruh nilai harus berada pada rentang 0 sampai 100.');
                        }
                        $cleanDynamic[$key] = round($num, 2);
                    } else {
                        $cleanDynamic[$key] = null;
                    }
                }

                if ($weight->isAutoAttendance()) {
                    $att = $autoAttendanceMap[$sid]['auto_score'] ?? null;
                } else {
                    $attRaw = $row['attendance_score'] ?? null;
                    $att = ($attRaw !== null && $attRaw !== '') ? round((float) $attRaw, 2) : null;
                }
                $syncedAttendanceScores[(string) $sid] = $att;

                $stsRaw = $row['sts_score'] ?? null;
                $sasRaw = $row['sas_score'] ?? null;
                $sts = ($stsRaw !== null && $stsRaw !== '') ? round((float) $stsRaw, 2) : null;
                $sas = ($sasRaw !== null && $sasRaw !== '') ? round((float) $sasRaw, 2) : null;

                $calc = $gradeService->calculateStudentRow($cleanDynamic, $att, $sts, $sas, $phColumns, $ntColumns, $weight);

                StudentGrade::query()->updateOrCreate(
                    [
                        'teaching_class_id' => $teachingClass->id,
                        'class_student_id' => $sid,
                    ],
                    [
                        'attendance_score' => $att,
                        'sts_score' => $sts,
                        'sas_score' => $sas,
                        'dynamic_scores' => $cleanDynamic,
                        'avg_ph' => $calc['avg_ph'],
                        'avg_nt' => $calc['avg_nt'],
                        'final_score' => $calc['final_score'],
                    ]
                );
            }
        });

        return response()->json([
            'status' => 'ok',
            'message' => 'Seluruh nilai & Nilai Akhir (NA) berhasil disimpan.',
            'saved_at' => now()->format('H:i:s'),
            'attendance_scores' => $syncedAttendanceScores,
        ]);
    }

    public function addColumn(
        Request $request,
        TeachingClass $teachingClass,
        GradeCalculationService $gradeService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek tidak memiliki izin untuk mengubah nilai guru lain.'
        );

        $validated = $request->validate([
            'type' => ['required', Rule::in(['PH', 'NT'])],
            'label' => ['nullable', 'string', 'max:50'],
            'topic_note' => ['nullable', 'string', 'max:120'],
        ]);

        $type = $validated['type'];
        $existingCount = $teachingClass->gradeColumns()->where('type', $type)->count();
        $nextOrder = ((int) $teachingClass->gradeColumns()->where('type', $type)->max('sort_order')) + 1;
        $defaultLabel = $type . ' ' . ($existingCount + 1);

        $col = $teachingClass->gradeColumns()->create([
            'type' => $type,
            'label' => ! empty($validated['label']) ? trim($validated['label']) : $defaultLabel,
            'topic_note' => $validated['topic_note'] ?? null,
            'sort_order' => $nextOrder,
        ]);

        $gradeService->recalculateClass($teachingClass);

        return back()->with('success', "Kolom {$col->label} berhasil ditambahkan & rumus NA telah diperbarui.");
    }

    public function updateColumn(
        Request $request,
        TeachingClass $teachingClass,
        GradeColumn $column
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        abort_unless((int) $column->teaching_class_id === (int) $teachingClass->id, 404);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek tidak memiliki izin untuk mengubah nilai guru lain.'
        );

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'topic_note' => ['nullable', 'string', 'max:120'],
        ]);

        $column->update([
            'label' => trim($validated['label']),
            'topic_note' => $validated['topic_note'] ?? null,
        ]);

        return back()->with('success', "Kolom {$column->label} berhasil diperbarui.");
    }

    public function deleteColumn(
        Request $request,
        TeachingClass $teachingClass,
        GradeColumn $column,
        GradeCalculationService $gradeService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);
        abort_unless((int) $column->teaching_class_id === (int) $teachingClass->id, 404);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek tidak memiliki izin untuk mengubah nilai guru lain.'
        );

        $deletedId = (string) $column->id;
        $label = $column->label;

        DB::transaction(function () use ($teachingClass, $column, $deletedId) {
            $column->delete();

            foreach ($teachingClass->studentGrades()->get() as $sg) {
                $dyn = $sg->dynamic_scores ?? [];
                if (array_key_exists($deletedId, $dyn)) {
                    unset($dyn[$deletedId]);
                    $sg->update(['dynamic_scores' => $dyn]);
                }
            }
        });

        $gradeService->recalculateClass($teachingClass);

        return back()->with('success', "Kolom {$label} dihapus & rata-rata serta Nilai Akhir (NA) telah dihitung ulang.");
    }

    public function updateWeights(
        Request $request,
        TeachingClass $teachingClass,
        GradeCalculationService $gradeService
    ): RedirectResponse {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);

        abort_if(
            $user->isAdmin() && (int) $teachingClass->teacher_id !== (int) $user->id,
            403,
            'Akses ditolak. Administrator/Wakasek tidak memiliki izin untuk mengubah nilai guru lain.'
        );

        $teachingClass->ensureDefaultsInitialized();

        $validated = $request->validate([
            'weight_ph' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_attendance' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_nt' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_sts' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_sas' => ['required', 'numeric', 'min:0', 'max:100'],
            'rounding_precision' => ['required', Rule::in([0, 1, 2, '0', '1', '2'])],
            'attendance_mode' => ['nullable', Rule::in([GradeWeight::MODE_MANUAL, GradeWeight::MODE_AUTO])],
            'sick_leave_tolerance' => ['nullable', 'integer', 'min:0', 'max:100'],
            'agreement_note' => ['nullable', 'string', 'max:200'],
            'copy_to_class_ids' => ['nullable', 'array'],
            'copy_to_class_ids.*' => ['integer', 'exists:teaching_classes,id'],
        ]);

        $total = round(
            (float) $validated['weight_ph']
            + (float) $validated['weight_attendance']
            + (float) $validated['weight_nt']
            + (float) $validated['weight_sts']
            + (float) $validated['weight_sas'],
            2
        );

        if (abs($total - 100.0) > 0.01) {
            return back()->withErrors([
                'weights' => "Total persentase rumus Nilai Akhir harus tepat 100% (saat ini: {$total}%).",
            ]);
        }

        $existingWeight = $teachingClass->gradeWeight()->first();
        $resolvedMode = $validated['attendance_mode'] ?? ($existingWeight?->attendance_mode ?? GradeWeight::MODE_MANUAL);
        $resolvedTolerance = array_key_exists('sick_leave_tolerance', $validated) && $validated['sick_leave_tolerance'] !== null
            ? (int) $validated['sick_leave_tolerance']
            : (int) ($existingWeight?->sick_leave_tolerance ?? 3);

        $teachingClass->gradeWeight()->updateOrCreate(
            ['teaching_class_id' => $teachingClass->id],
            [
                'weight_ph' => (float) $validated['weight_ph'],
                'weight_attendance' => (float) $validated['weight_attendance'],
                'weight_nt' => (float) $validated['weight_nt'],
                'weight_sts' => (float) $validated['weight_sts'],
                'weight_sas' => (float) $validated['weight_sas'],
                'rounding_precision' => (int) $validated['rounding_precision'],
                'attendance_mode' => $resolvedMode,
                'sick_leave_tolerance' => $resolvedTolerance,
                'agreement_note' => $validated['agreement_note'] ?? null,
            ]
        );

        $gradeService->recalculateClass($teachingClass);

        $copiedCount = 0;
        if (! empty($validated['copy_to_class_ids'])) {
            $copiedCount = $gradeService->copyWeightsToClasses($teachingClass, $validated['copy_to_class_ids'], $user);
        }

        $msg = 'Rumus persentase Nilai Akhir (NA) berhasil disimpan & seluruh nilai murid telah dihitung ulang.';
        if ($copiedCount > 0) {
            $msg .= " Rumus juga berhasil disalin ke {$copiedCount} kelas lainnya.";
        }

        return back()->with('success', $msg);
    }

    public function downloadTemplate(
        Request $request,
        TeachingClass $teachingClass,
        ExcelExportImportService $excelService
    ) {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id && ! (bool) $teachingClass->is_grade_shared_with_admin,
            403,
            'Akses ditolak. Guru pengampu belum memberikan izin akses detail nilai kepada Administrator/Wakasek.'
        );

        return $excelService->downloadGradeTemplate($teachingClass);
    }

    public function importExcel(
        Request $request,
        TeachingClass $teachingClass,
        ExcelExportImportService $excelService
    ): RedirectResponse {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        abort_if(
            $request->user()->isAdmin() && (int) $teachingClass->teacher_id !== (int) $request->user()->id,
            403,
            'Akses ditolak. Administrator/Wakasek tidak memiliki izin untuk mengubah nilai guru lain.'
        );

        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $count = $excelService->importGrades($teachingClass, $request->file('excel_file')->getRealPath());

        return back()->with('success', "Berhasil mengimpor nilai untuk {$count} peserta didik & memperbarui Nilai Akhir (NA).");
    }
}
