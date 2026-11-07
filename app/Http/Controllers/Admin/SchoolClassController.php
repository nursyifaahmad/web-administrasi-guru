<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Services\ExcelExportImportService;
use App\Services\GradeCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->resolveAcademicYear($request);
        $school = SchoolSetting::current();

        $search = $request->string('q')->trim()->toString();
        $gradeFilter = $request->string('grade_level')->trim()->toString() ?: null;

        $query = SchoolClass::query()
            ->with(['academicYear', 'teachingClasses.teacher', 'teachingClasses.subject'])
            ->withCount(['students', 'activeStudents', 'teachingClasses'])
            ->where('academic_year_id', $academicYear->id);

        if ($gradeFilter) {
            $query->where('grade_level', $gradeFilter);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('homeroom_teacher_name', 'like', "%{$search}%");
            });
        }

        $classes = $query->orderBy('grade_level')->orderBy('name')->get();

        return view('admin.classes.index', [
            'classes' => $classes,
            'gradeLevels' => $school->gradeLevels(),
            'filters' => [
                'q' => $search,
                'grade_level' => $gradeFilter,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->resolveAcademicYear($request);

        $validated = $request->validate([
            'grade_level' => ['required', 'string', 'max:20'],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('school_classes')->where(fn ($q) => $q->where('academic_year_id', $academicYear->id)),
            ],
            'homeroom_teacher_name' => ['required', 'string', 'max:180'],
            'homeroom_teacher_nip' => ['nullable', 'string', 'max:40'],
            'homeroom_teacher_signature_data' => ['nullable', 'string'],
            'homeroom_teacher_signature_file' => ['nullable', 'image', 'max:2048'],
        ]);

        $wkSig = null;
        if ($request->hasFile('homeroom_teacher_signature_file')) {
            $wkSig = $this->uploadedImageToDataUri($request->file('homeroom_teacher_signature_file'));
        } elseif (! empty($validated['homeroom_teacher_signature_data']) && str_starts_with($validated['homeroom_teacher_signature_data'], 'data:image/')) {
            $wkSig = $validated['homeroom_teacher_signature_data'];
        }

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'grade_level' => strtoupper(trim($validated['grade_level'])),
            'name' => trim($validated['name']),
            'homeroom_teacher_name' => trim($validated['homeroom_teacher_name']),
            'homeroom_teacher_nip' => $validated['homeroom_teacher_nip'] ?? null,
            'homeroom_teacher_signature' => $wkSig,
        ]);

        return redirect()
            ->route('admin.classes.show', $schoolClass)
            ->with('success', "Rombel Kelas {$schoolClass->name} berhasil dibuat! Silakan input atau impor daftar murid.");
    }

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $schoolClass->load([
            'academicYear',
            'students',
            'teachingClasses.teacher',
            'teachingClasses.subject',
        ]);

        $school = SchoolSetting::current();

        // Deteksi ID duplikat berdasarkan nama lengkap atau NIS/NISN yang berulang
        $duplicates = [];
        $seen = [];
        foreach ($schoolClass->students as $st) {
            $nameKey = mb_strtolower(trim($st->full_name));
            $nisnKey = trim((string) $st->nis_nisn);
            $key = $nisnKey !== '' ? "nis:{$nisnKey}" : "name:{$nameKey}";
            if (isset($seen[$key])) {
                $duplicates[] = $st->id;
            } else {
                $seen[$key] = $st->id;
            }
        }

        return view('admin.classes.show', [
            'schoolClass' => $schoolClass,
            'students' => $schoolClass->students,
            'gradeLevels' => $school->gradeLevels(),
            'duplicateStudentIds' => $duplicates,
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validate([
            'grade_level' => ['required', 'string', 'max:20'],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('school_classes')->where(fn ($q) => $q->where('academic_year_id', $schoolClass->academic_year_id))->ignore($schoolClass->id),
            ],
            'homeroom_teacher_name' => ['required', 'string', 'max:180'],
            'homeroom_teacher_nip' => ['nullable', 'string', 'max:40'],
            'homeroom_teacher_signature_data' => ['nullable', 'string'],
            'homeroom_teacher_signature_file' => ['nullable', 'image', 'max:2048'],
            'clear_homeroom_signature' => ['nullable', 'boolean'],
        ]);

        $wkSig = $schoolClass->homeroom_teacher_signature;
        if ($request->boolean('clear_homeroom_signature')) {
            $wkSig = null;
        } elseif ($request->hasFile('homeroom_teacher_signature_file')) {
            $wkSig = $this->uploadedImageToDataUri($request->file('homeroom_teacher_signature_file'));
        } elseif (! empty($validated['homeroom_teacher_signature_data']) && str_starts_with($validated['homeroom_teacher_signature_data'], 'data:image/')) {
            $wkSig = $validated['homeroom_teacher_signature_data'];
        }

        $schoolClass->update([
            'grade_level' => strtoupper(trim($validated['grade_level'])),
            'name' => trim($validated['name']),
            'homeroom_teacher_name' => trim($validated['homeroom_teacher_name']),
            'homeroom_teacher_nip' => $validated['homeroom_teacher_nip'] ?? null,
            'homeroom_teacher_signature' => $wkSig,
        ]);

        $schoolClass->syncToTeachingClasses();

        return back()->with('success', "Identitas Rombel {$schoolClass->name} & Wali Kelas berhasil diperbarui dan disinkronkan ke seluruh guru.");
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $name = $schoolClass->name;
        $schoolClass->delete();

        return redirect()->route('admin.classes.index')->with('success', "Rombel Kelas {$name} berhasil dihapus.");
    }

    public function storeStudent(Request $request, SchoolClass $schoolClass, GradeCalculationService $gradeService): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['nullable', 'integer', 'min:1'],
            'nis_nisn' => ['nullable', 'string', 'max:40'],
            'full_name' => ['required', 'string', 'max:180'],
            'gender' => ['required', Rule::in(['L', 'P'])],
        ]);

        $nextOrder = $validated['order_number'] ?? (((int) $schoolClass->students()->max('order_number')) + 1);
        $firstTcId = $schoolClass->teachingClasses()->value('id');

        $student = $schoolClass->students()->create([
            'teaching_class_id' => $firstTcId,
            'order_number' => $nextOrder,
            'nis_nisn' => $validated['nis_nisn'] ?? null,
            'full_name' => trim($validated['full_name']),
            'gender' => $validated['gender'],
            'is_active' => true,
        ]);

        foreach ($schoolClass->teachingClasses as $tc) {
            $gradeService->recalculateClass($tc);
        }

        return back()->with('success', "Murid {$student->full_name} berhasil ditambahkan ke Rombel {$schoolClass->name}.");
    }

    public function updateStudent(Request $request, SchoolClass $schoolClass, ClassStudent $student): RedirectResponse
    {
        abort_unless((int) $student->school_class_id === (int) $schoolClass->id, 404);

        $validated = $request->validate([
            'order_number' => ['required', 'integer', 'min:1'],
            'nis_nisn' => ['nullable', 'string', 'max:40'],
            'full_name' => ['required', 'string', 'max:180'],
            'gender' => ['required', Rule::in(['L', 'P'])],
        ]);

        $student->update([
            'order_number' => (int) $validated['order_number'],
            'nis_nisn' => $validated['nis_nisn'] ?? null,
            'full_name' => trim($validated['full_name']),
            'gender' => $validated['gender'],
        ]);

        return back()->with('success', "Data murid {$student->full_name} berhasil diperbarui.");
    }

    public function destroyStudent(SchoolClass $schoolClass, ClassStudent $student, GradeCalculationService $gradeService): RedirectResponse
    {
        abort_unless((int) $student->school_class_id === (int) $schoolClass->id, 404);

        $name = $student->full_name;
        $student->delete();

        foreach ($schoolClass->teachingClasses as $tc) {
            $gradeService->recalculateClass($tc);
        }

        return back()->with('success', "Murid {$name} berhasil dihapus dari Rombel {$schoolClass->name}.");
    }

    public function bulkDestroyStudents(Request $request, SchoolClass $schoolClass, GradeCalculationService $gradeService): RedirectResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer'],
        ]);

        $ids = array_map('intval', $validated['student_ids']);

        $deletedCount = ClassStudent::query()
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('id', $ids)
            ->delete();

        foreach ($schoolClass->teachingClasses as $tc) {
            $gradeService->recalculateClass($tc);
        }

        return back()->with('success', "Berhasil menghapus {$deletedCount} peserta didik dari Rombel {$schoolClass->name}.");
    }

    public function sortStudentsAlphabetically(SchoolClass $schoolClass): RedirectResponse
    {
        $students = $schoolClass->students()->get()->sortBy(fn ($s) => mb_strtolower($s->full_name))->values();
        DB::transaction(function () use ($students) {
            foreach ($students as $idx => $student) {
                $student->update(['order_number' => $idx + 1]);
            }
        });

        return back()->with('success', "Nomor urut peserta didik Rombel {$schoolClass->name} berhasil diurutkan sesuai abjad (A - Z).");
    }

    public function downloadStudentTemplate(SchoolClass $schoolClass, ExcelExportImportService $excelService)
    {
        return $excelService->downloadStudentTemplateForSchoolClass($schoolClass);
    }

    public function importStudents(
        Request $request,
        SchoolClass $schoolClass,
        ExcelExportImportService $excelService,
        GradeCalculationService $gradeService
    ): RedirectResponse {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $count = $excelService->importStudentsForSchoolClass(
            $schoolClass,
            $request->file('excel_file')->getRealPath(),
            $request->boolean('replace_existing')
        );

        foreach ($schoolClass->teachingClasses as $tc) {
            $gradeService->recalculateClass($tc);
        }

        return back()->with('success', "Berhasil mengimpor {$count} peserta didik ke Rombel {$schoolClass->name}.");
    }

    protected function uploadedImageToDataUri($file): string
    {
        $mime = $file->getMimeType() ?: 'image/png';
        $contents = file_get_contents($file->getRealPath());

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
