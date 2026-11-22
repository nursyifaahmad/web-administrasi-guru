<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\ExcelExportImportService;
use App\Services\GradeCalculationService;
use App\Services\SchoolScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ->with(['academicYear', 'homeroomTeacher', 'teachingClasses.teacher', 'teachingClasses.subject'])
            ->withCount(['students', 'activeStudents', 'teachingClasses'])
            ->where('academic_year_id', $academicYear->id);

        if ($gradeFilter) {
            $query->where('grade_level', $gradeFilter);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('homeroom_teacher_name', 'like', "%{$search}%")
                    ->orWhereHas('homeroomTeacher', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $classes = $query->orderBy('grade_level')->orderBy('name')->get();

        $masterTeachers = User::query()
            ->with(['homeroomSchoolClasses' => fn ($q) => $q->where('academic_year_id', $academicYear->id)])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.classes.index', [
            'classes' => $classes,
            'gradeLevels' => $school->gradeLevels(),
            'masterTeachers' => $masterTeachers,
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
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'homeroom_teacher_name' => ['nullable', 'string', 'max:180'],
            'homeroom_teacher_nip' => ['nullable', 'string', 'max:40'],
            'homeroom_teacher_signature_data' => ['nullable', 'string'],
            'homeroom_teacher_signature_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $homeroomTeacher = null;
        if (! empty($validated['homeroom_teacher_id'])) {
            $homeroomTeacher = User::query()->findOrFail((int) $validated['homeroom_teacher_id']);
            $existingHomeroom = SchoolClass::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('homeroom_teacher_id', $homeroomTeacher->id)
                ->first();

            if ($existingHomeroom) {
                throw ValidationException::withMessages([
                    'homeroom_teacher_id' => "Bentrok Wali Kelas: {$homeroomTeacher->name} sudah menjadi Wali Kelas di Rombel {$existingHomeroom->name}.",
                ]);
            }
        }

        $wkSig = null;
        if ($request->hasFile('homeroom_teacher_signature_file')) {
            $wkSig = $this->uploadedImageToDataUri($request->file('homeroom_teacher_signature_file'), 'homeroom_teacher_signature_file');
        } elseif (! empty($validated['homeroom_teacher_signature_data'])) {
            $wkSig = $this->sanitizeRasterImageDataUri($validated['homeroom_teacher_signature_data'], 'homeroom_teacher_signature_data');
        }

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'grade_level' => strtoupper(trim($validated['grade_level'])),
            'name' => trim($validated['name']),
            'homeroom_teacher_id' => $homeroomTeacher?->id,
            'homeroom_teacher_name' => $homeroomTeacher?->name ?: (trim((string) ($validated['homeroom_teacher_name'] ?? '')) ?: '-'),
            'homeroom_teacher_nip' => $homeroomTeacher?->nip ?? ($validated['homeroom_teacher_nip'] ?? null),
            'homeroom_teacher_signature' => $homeroomTeacher?->signature_data ?? $wkSig,
        ]);

        return redirect()
            ->route('admin.classes.show', $schoolClass)
            ->with('success', "Rombel Kelas {$schoolClass->name} berhasil dibuat! Silakan input atau impor daftar murid.");
    }

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $schoolClass->load([
            'academicYear',
            'homeroomTeacher',
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
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'homeroom_teacher_name' => ['nullable', 'string', 'max:180'],
            'homeroom_teacher_nip' => ['nullable', 'string', 'max:40'],
            'homeroom_teacher_signature_data' => ['nullable', 'string'],
            'homeroom_teacher_signature_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'clear_homeroom_signature' => ['nullable', 'boolean'],
        ]);

        $newName = trim($validated['name']);
        $updates = [
            'grade_level' => strtoupper(trim($validated['grade_level'])),
            'name' => $newName,
        ];

        // Jika wali kelas diubah secara eksplisit via homeroom_teacher_id, validasi anti-bentrok dan tarik dari Master Guru
        if ($request->exists('homeroom_teacher_id')) {
            $targetTeacherId = ! empty($validated['homeroom_teacher_id']) ? (int) $validated['homeroom_teacher_id'] : null;
            if ($targetTeacherId !== null) {
                $conflictClass = SchoolClass::query()
                    ->where('academic_year_id', $schoolClass->academic_year_id)
                    ->where('homeroom_teacher_id', $targetTeacherId)
                    ->where('id', '!=', $schoolClass->id)
                    ->first();

                if ($conflictClass) {
                    throw ValidationException::withMessages([
                        'homeroom_teacher_id' => "Bentrok Wali Kelas: guru terpilih sudah menjadi Wali Kelas di Rombel {$conflictClass->name}.",
                    ]);
                }

                $teacher = User::query()->findOrFail($targetTeacherId);
                $updates['homeroom_teacher_id'] = $teacher->id;
                $updates['homeroom_teacher_name'] = $teacher->name;
                $updates['homeroom_teacher_nip'] = $teacher->nip;
                $updates['homeroom_teacher_signature'] = $teacher->signature_data;
            } else {
                $updates['homeroom_teacher_id'] = null;
                $updates['homeroom_teacher_name'] = '-';
                $updates['homeroom_teacher_nip'] = null;
                $updates['homeroom_teacher_signature'] = null;
            }
        } elseif ($schoolClass->homeroomTeacher) {
            // Pastikan snapshot tetap identik dengan Master Guru
            $updates['homeroom_teacher_name'] = $schoolClass->homeroomTeacher->name;
            $updates['homeroom_teacher_nip'] = $schoolClass->homeroomTeacher->nip;
            $updates['homeroom_teacher_signature'] = $schoolClass->homeroomTeacher->signature_data;
        }

        $schoolClass->update($updates);
        $schoolClass->syncToTeachingClasses();

        // Sinkronkan juga class_name pada item jadwal yang tertaut ke rombel ini
        $normClassCode = SchoolScheduleService::normalizeClassName($newName);
        if ($normClassCode !== '') {
            SchoolScheduleItem::query()
                ->where('school_class_id', $schoolClass->id)
                ->update(['class_name' => $normClassCode]);
        }

        return back()->with('success', "Identitas Rombel {$schoolClass->name} berhasil diperbarui dan disinkronkan.");
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

        $student = $schoolClass->students()->create([
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
}
