<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\ExcelExportImportService;
use App\Services\GradeCalculationService;
use App\Services\SchoolScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeachingClassController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $academicYear = $this->resolveAcademicYear($request);
        $school = SchoolSetting::current();

        $search = $request->string('q')->trim()->toString();
        $gradeFilter = $request->string('grade_level')->trim()->toString() ?: null;
        $viewMode = $request->string('view')->trim()->toString();
        $teacherFilter = $request->integer('teacher_id') ?: null;

        $myClassesCount = TeachingClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->count();

        $allClassesCount = TeachingClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->count();

        $query = TeachingClass::query()
            ->with(['subject.kktps', 'teacher', 'academicYear', 'studentGrades'])
            ->withCount([
                'activeStudents as students_count',
                'dailyJournals as journals_count',
            ])
            ->where('academic_year_id', $academicYear->id);

        $effectiveView = 'my';
        if (! $user->isAdmin()) {
            $query->where('teacher_id', $user->id);
            $effectiveView = 'my';
        } else {
            // Mode untuk akun administrator/wakasek:
            if ($teacherFilter) {
                $query->where('teacher_id', $teacherFilter);
                $effectiveView = 'all';
            } elseif ($viewMode === 'all') {
                // Tampilkan seluruh kelas dewan guru (mode supervisi)
                $effectiveView = 'all';
            } else {
                // Default tampilan admin: hanya kelas milik admin sendiri
                $query->where('teacher_id', $user->id);
                $effectiveView = 'my';
            }
        }

        if ($gradeFilter) {
            $query->where('grade_level', $gradeFilter);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('class_name', 'like', "%{$search}%")
                    ->orWhere('homeroom_teacher_name', 'like', "%{$search}%")
                    ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $classes = $query->orderBy('grade_level')->orderBy('class_name')->get();

        $schoolClasses = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->withCount('activeStudents')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $attendanceDaysByClass = Attendance::query()
            ->whereIn('teaching_class_id', $classes->pluck('id'))
            ->selectRaw('teaching_class_id, COUNT(DISTINCT attendance_date) as days_count')
            ->groupBy('teaching_class_id')
            ->pluck('days_count', 'teaching_class_id');

        // Daftar kelas lain milik guru (termasuk semester sebelumnya) untuk fitur salin daftar murid cepat
        $copyableClassesQuery = TeachingClass::query()
            ->with(['academicYear', 'subject'])
            ->withCount('activeStudents as students_count')
            ->orderByDesc('academic_year_id')
            ->orderBy('class_name');

        if (! $user->isAdmin() || $effectiveView === 'my') {
            $copyableClassesQuery->where('teacher_id', $user->id);
        }

        $teachers = $user->isAdmin()
            ? User::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $scheduleService = app(SchoolScheduleService::class);
        $scheduleAllocations = $scheduleService->getTeacherClassAllocations($academicYear, $user);

        return view('workspace.classes.index', [
            'classes' => $classes,
            'schoolClasses' => $schoolClasses,
            'attendanceDaysByClass' => $attendanceDaysByClass,
            'subjects' => Subject::query()->where('is_active', true)->with('kktps')->orderBy('name')->get(),
            'gradeLevels' => $school->gradeLevels(),
            'teachers' => $teachers,
            'copyableClasses' => $copyableClassesQuery->get(),
            'effectiveView' => $effectiveView,
            'myClassesCount' => $myClassesCount,
            'allClassesCount' => $allClassesCount,
            'scheduleAllocations' => $scheduleAllocations,
            'filters' => [
                'q' => $search,
                'grade_level' => $gradeFilter,
                'teacher_id' => $teacherFilter,
                'view' => $viewMode,
            ],
        ]);
    }

    public function scheduleAllocations(Request $request, SchoolScheduleService $scheduleService): JsonResponse
    {
        $user = $request->user();
        $targetUser = $user;
        if ($user->isAdmin() && $request->filled('teacher_id')) {
            $targetUser = User::query()->find($request->integer('teacher_id')) ?? $user;
        }

        $academicYear = $this->resolveAcademicYear($request);
        $allocations = $scheduleService->getTeacherClassAllocations($academicYear, $targetUser);

        return response()->json([
            'status' => 'success',
            'teacher_id' => $targetUser->id,
            'teacher_name' => $targetUser->name,
            'total_allocations' => $allocations->count(),
            'pending_count' => $allocations->where('already_created', false)->count(),
            'created_count' => $allocations->where('already_created', true)->count(),
            'allocations' => $allocations->values(),
        ]);
    }

    public function syncFromSchedule(Request $request, SchoolScheduleService $scheduleService): RedirectResponse
    {
        $user = $request->user();
        $targetUser = $user;
        if ($user->isAdmin() && $request->filled('teacher_id')) {
            $targetUser = User::query()->find($request->integer('teacher_id')) ?? $user;
        }

        $academicYear = $this->resolveAcademicYear($request);
        $result = $scheduleService->syncTeacherClasses($academicYear, $targetUser);

        $msg = "Berhasil memproses buku kelas dari Master Jadwal Sekolah: {$result['created']} kelas baru dibuat, {$result['updated']} kelas diperbarui datanya.";
        if ($result['created'] === 0 && $result['updated'] === 0) {
            $msg = "Seluruh kelas mengajar ({$result['total']} kelas) sesuai Master Jadwal sudah ada dan up-to-date.";
        }

        return redirect()->route('classes.index')->with('success', $msg);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $academicYear = $this->resolveAcademicYear($request);

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'exists:users,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade_level' => ['required_without:school_class_id', 'nullable', 'string', 'max:20'],
            'class_name' => ['required_without:school_class_id', 'nullable', 'string', 'max:60'],
            'schedule_info' => ['nullable', 'string', 'max:180'],
            'teaching_days' => ['nullable'],
            'homeroom_teacher_name' => ['required_without:school_class_id', 'nullable', 'string', 'max:180'],
            'homeroom_teacher_nip' => ['nullable', 'string', 'max:40'],
            'copy_students_from_class_id' => ['nullable', 'exists:teaching_classes,id'],
        ]);

        $ownerId = ($user->isAdmin() && ! empty($validated['teacher_id']))
            ? (int) $validated['teacher_id']
            : $user->id;

        $schoolClass = ! empty($validated['school_class_id'])
            ? SchoolClass::query()->find($validated['school_class_id'])
            : null;

        $className = $schoolClass ? $schoolClass->name : trim($validated['class_name'] ?? '');

        // Cegah pembuatan buku kelas duplikat untuk rombel dan mapel yang sama oleh guru yang sama
        $duplicateCheck = TeachingClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $ownerId)
            ->where('subject_id', (int) $validated['subject_id'])
            ->where(function ($q) use ($schoolClass, $className) {
                if ($schoolClass) {
                    $q->where('school_class_id', $schoolClass->id)
                      ->orWhere('class_name', $schoolClass->name);
                } else {
                    $q->where('class_name', $className);
                }
            })
            ->first();

        if ($duplicateCheck) {
            return redirect()
                ->route('classes.index')
                ->with('warning', "Buku kelas untuk {$duplicateCheck->class_name} ({$duplicateCheck->subject->name}) sudah pernah dibuat sebelumnya. Anda tidak dapat membuat buku kelas duplikat.");
        }

        $createdClass = DB::transaction(function () use ($validated, $ownerId, $academicYear, $user, $schoolClass, $className) {
            $gradeLevel = $schoolClass ? $schoolClass->grade_level : strtoupper(trim($validated['grade_level'] ?? 'VII'));
            $wkName = $schoolClass ? $schoolClass->homeroom_teacher_name : trim($validated['homeroom_teacher_name'] ?? '-');
            $wkNip = $schoolClass ? $schoolClass->homeroom_teacher_nip : ($validated['homeroom_teacher_nip'] ?? null);
            $wkSig = $schoolClass ? $schoolClass->homeroom_teacher_signature : null;

            if (! $schoolClass && $className !== '') {
                $schoolClass = SchoolClass::query()->firstOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'name' => $className,
                    ],
                    [
                        'grade_level' => $gradeLevel,
                        'homeroom_teacher_name' => $wkName,
                        'homeroom_teacher_nip' => $wkNip,
                    ]
                );
            }

            $rawDays = $validated['teaching_days'] ?? null;
            $teachingDays = is_array($rawDays) ? $rawDays : (is_string($rawDays) && str_starts_with($rawDays, '[') ? json_decode($rawDays, true) : null);

            $cls = TeachingClass::query()->create([
                'teacher_id' => $ownerId,
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClass?->id,
                'subject_id' => (int) $validated['subject_id'],
                'grade_level' => $gradeLevel,
                'class_name' => $className,
                'schedule_info' => $validated['schedule_info'] ?? null,
                'teaching_days' => $teachingDays,
                'only_teaching_days' => ! empty($teachingDays),
                'homeroom_teacher_name' => $wkName,
                'homeroom_teacher_nip' => $wkNip,
                'homeroom_teacher_signature' => $wkSig,
            ]);

            $cls->ensureDefaultsInitialized();

            if (! empty($validated['copy_students_from_class_id']) && $cls->activeStudents()->count() === 0) {
                $source = TeachingClass::query()->find($validated['copy_students_from_class_id']);
                if ($source && ($user->isAdmin() || (int) $source->teacher_id === (int) $user->id)) {
                    foreach ($source->activeStudents()->get() as $idx => $st) {
                        $cls->students()->create([
                            'order_number' => $idx + 1,
                            'nis_nisn' => $st->nis_nisn,
                            'full_name' => $st->full_name,
                            'gender' => $st->gender,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            return $cls;
        });

        return redirect()
            ->route('classes.index')
            ->with('success', "Buku Kelas Administrasi {$createdClass->class_name} ({$createdClass->subject->name}) berhasil dibuat!");
    }

    public function update(Request $request, TeachingClass $teachingClass): RedirectResponse
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'schedule_info' => ['nullable', 'string', 'max:180'],
            'teaching_days' => ['nullable', 'array'],
            'teaching_days.*' => ['integer', 'min:0', 'max:6'],
            'only_teaching_days' => ['nullable', 'boolean'],
            'grade_level' => ['nullable', 'string', 'max:20'],
            'class_name' => ['nullable', 'string', 'max:60'],
            'homeroom_teacher_name' => ['nullable', 'string', 'max:180'],
            'homeroom_teacher_nip' => ['nullable', 'string', 'max:40'],
            'homeroom_teacher_signature_data' => ['nullable', 'string'],
            'homeroom_teacher_signature_file' => ['nullable', 'image', 'max:2048'],
            'clear_homeroom_signature' => ['nullable', 'boolean'],
        ]);

        $wkSig = $teachingClass->homeroom_teacher_signature;
        if ($request->boolean('clear_homeroom_signature')) {
            $wkSig = null;
        } elseif ($request->hasFile('homeroom_teacher_signature_file')) {
            $wkSig = $this->uploadedImageToDataUri($request->file('homeroom_teacher_signature_file'));
        } elseif (! empty($validated['homeroom_teacher_signature_data']) && str_starts_with($validated['homeroom_teacher_signature_data'], 'data:image/')) {
            $wkSig = $validated['homeroom_teacher_signature_data'];
        }

        $onlyTeachingDays = $request->boolean('only_teaching_days');
        $teachingDays = $request->has('teaching_days')
            ? array_values(array_unique(array_map('intval', (array) $request->input('teaching_days', []))))
            : null;

        $sc = $teachingClass->schoolClass;
        $gradeLevel = ! empty($validated['grade_level']) ? strtoupper(trim($validated['grade_level'])) : ($sc ? $sc->grade_level : $teachingClass->grade_level);
        $className = ! empty($validated['class_name']) ? trim($validated['class_name']) : ($sc ? $sc->name : $teachingClass->class_name);
        $wkName = ! empty($validated['homeroom_teacher_name']) ? trim($validated['homeroom_teacher_name']) : ($sc ? $sc->homeroom_teacher_name : $teachingClass->homeroom_teacher_name);
        $wkNip = array_key_exists('homeroom_teacher_nip', $validated) ? $validated['homeroom_teacher_nip'] : ($sc ? $sc->homeroom_teacher_nip : $teachingClass->homeroom_teacher_nip);

        if (! $request->user()->isAdmin() && $sc) {
            $teachingClass->update([
                'subject_id' => (int) $validated['subject_id'],
                'schedule_info' => $validated['schedule_info'] ?? null,
                'teaching_days' => $teachingDays,
                'only_teaching_days' => $onlyTeachingDays,
                'grade_level' => $sc->grade_level,
                'class_name' => $sc->name,
                'homeroom_teacher_name' => $sc->homeroom_teacher_name,
                'homeroom_teacher_nip' => $sc->homeroom_teacher_nip,
                'homeroom_teacher_signature' => $sc->homeroom_teacher_signature,
            ]);
        } else {
            $teachingClass->update([
                'subject_id' => (int) $validated['subject_id'],
                'grade_level' => $gradeLevel,
                'class_name' => $className,
                'schedule_info' => $validated['schedule_info'] ?? null,
                'teaching_days' => $teachingDays,
                'only_teaching_days' => $onlyTeachingDays,
                'homeroom_teacher_name' => $wkName,
                'homeroom_teacher_nip' => $wkNip,
                'homeroom_teacher_signature' => $wkSig,
            ]);

            if ($request->user()->isAdmin() && $sc) {
                $sc->update([
                    'grade_level' => $gradeLevel,
                    'name' => $className,
                    'homeroom_teacher_name' => $wkName,
                    'homeroom_teacher_nip' => $wkNip,
                    'homeroom_teacher_signature' => $wkSig,
                ]);
                $sc->syncToTeachingClasses();
            }
        }

        return back()->with('success', "Identitas Kelas {$teachingClass->class_name} & Wali Kelas berhasil diperbarui.");
    }

    public function destroy(Request $request, TeachingClass $teachingClass): RedirectResponse
    {
        $user = $request->user();

        // 1. Larang keras Admin menghapus Buku Administrasi Guru
        abort_if(
            $user->isAdmin(),
            403,
            'Akses ditolak. Administrator tidak memiliki wewenang untuk menghapus Buku Administrasi Guru. Hanya guru pengampu bersangkutan yang berhak menghapus buku kelas ini.'
        );

        // 2. Pastikan hanya guru pemilik sah kelas ini yang berhak menghapus
        abort_unless(
            (int) $teachingClass->teacher_id === (int) $user->id,
            403,
            'Anda tidak memiliki izin untuk menghapus buku administrasi guru ini.'
        );

        $name = $teachingClass->class_name;
        $subjectName = $teachingClass->subject?->name ?? '';
        $teachingClass->delete();

        return redirect()->route('classes.index')->with('success', "Buku Administrasi Kelas {$name} ({$subjectName}) berhasil dihapus.");
    }

    public function students(Request $request, TeachingClass $teachingClass): View
    {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);

        $teachingClass->load(['subject.kktps', 'teacher', 'academicYear']);
        $students = $teachingClass->activeStudents()->get();
        $school = SchoolSetting::current();

        $otherClassesQuery = TeachingClass::query()
            ->with(['academicYear', 'subject'])
            ->withCount('activeStudents as students_count')
            ->where('id', '!=', $teachingClass->id)
            ->orderByDesc('academic_year_id')
            ->orderBy('class_name');

        if (! $user->isAdmin()) {
            $otherClassesQuery->where('teacher_id', $user->id);
        }

        return view('workspace.classes.students', [
            'teachingClass' => $teachingClass,
            'students' => $students,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'gradeLevels' => $school->gradeLevels(),
            'otherClasses' => $otherClassesQuery->get(),
        ]);
    }

    public function storeStudent(Request $request, TeachingClass $teachingClass, GradeCalculationService $gradeService): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);
        abort_unless($user->isAdmin(), 403, 'Akses ditolak. Pengelolaan data peserta didik dikontrol secara terpusat oleh Administrator Sekolah.');

        $validated = $request->validate([
            'order_number' => ['nullable', 'integer', 'min:1'],
            'nis_nisn' => ['nullable', 'string', 'max:40'],
            'full_name' => ['required', 'string', 'max:180'],
            'gender' => ['required', Rule::in(['L', 'P'])],
        ]);

        $nextOrder = $validated['order_number'] ?? (((int) $teachingClass->students()->max('order_number')) + 1);

        $student = $teachingClass->students()->create([
            'school_class_id' => $teachingClass->school_class_id,
            'teaching_class_id' => $teachingClass->id,
            'order_number' => $nextOrder,
            'nis_nisn' => $validated['nis_nisn'] ?? null,
            'full_name' => trim($validated['full_name']),
            'gender' => $validated['gender'],
            'is_active' => true,
        ]);

        if ($teachingClass->school_class_id) {
            foreach (TeachingClass::where('school_class_id', $teachingClass->school_class_id)->get() as $tc) {
                $gradeService->recalculateClass($tc);
            }
        } else {
            $gradeService->recalculateClass($teachingClass);
        }

        return back()->with('success', "Murid {$student->full_name} berhasil ditambahkan.");
    }

    public function updateStudent(Request $request, TeachingClass $teachingClass, ClassStudent $student): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);
        abort_unless($user->isAdmin(), 403, 'Akses ditolak. Pengelolaan data peserta didik dikontrol secara terpusat oleh Administrator Sekolah.');

        $isMatch = ($teachingClass->school_class_id && (int) $student->school_class_id === (int) $teachingClass->school_class_id)
            || ((int) $student->teaching_class_id === (int) $teachingClass->id);
        abort_unless($isMatch, 404);

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

    public function destroyStudent(Request $request, TeachingClass $teachingClass, ClassStudent $student, GradeCalculationService $gradeService): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);
        abort_unless($user->isAdmin(), 403, 'Akses ditolak. Pengelolaan data peserta didik dikontrol secara terpusat oleh Administrator Sekolah.');

        $isMatch = ($teachingClass->school_class_id && (int) $student->school_class_id === (int) $teachingClass->school_class_id)
            || ((int) $student->teaching_class_id === (int) $teachingClass->id);
        abort_unless($isMatch, 404);

        $name = $student->full_name;
        $student->delete();

        if ($teachingClass->school_class_id) {
            foreach (TeachingClass::where('school_class_id', $teachingClass->school_class_id)->get() as $tc) {
                $gradeService->recalculateClass($tc);
            }
        } else {
            $gradeService->recalculateClass($teachingClass);
        }

        return back()->with('success', "Murid {$name} dihapus dari kelas ini.");
    }

    public function sortStudentsAlphabetically(Request $request, TeachingClass $teachingClass): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);
        abort_unless($user->isAdmin(), 403, 'Akses ditolak. Pengelolaan data peserta didik dikontrol secara terpusat oleh Administrator Sekolah.');

        $students = $teachingClass->activeStudents()->get()->sortBy(fn ($s) => mb_strtolower($s->full_name))->values();
        DB::transaction(function () use ($students) {
            foreach ($students as $idx => $student) {
                $student->update(['order_number' => $idx + 1]);
            }
        });

        return back()->with('success', 'Nomor urut peserta didik berhasil diurutkan sesuai abjad (A - Z).');
    }

    public function copyStudentsFromClass(Request $request, TeachingClass $teachingClass): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);
        abort_unless($user->isAdmin(), 403, 'Akses ditolak. Pengelolaan data peserta didik dikontrol secara terpusat oleh Administrator Sekolah.');

        $validated = $request->validate([
            'source_class_id' => ['required', 'exists:teaching_classes,id'],
            'copy_homeroom' => ['nullable', 'boolean'],
        ]);

        $source = TeachingClass::query()->findOrFail($validated['source_class_id']);
        $this->authorizeClassAccess($user, $source);

        $sourceStudents = $source->activeStudents()->get();
        if ($sourceStudents->isEmpty()) {
            return back()->withErrors(['source_class_id' => 'Kelas sumber belum memiliki daftar murid.']);
        }

        $added = 0;
        DB::transaction(function () use ($teachingClass, $source, $sourceStudents, $request, &$added) {
            $maxOrder = (int) ($teachingClass->students()->max('order_number') ?? 0);
            $existingNames = $teachingClass->activeStudents()
                ->pluck('full_name')
                ->map(fn ($n) => mb_strtolower(trim($n)))
                ->flip();

            foreach ($sourceStudents as $st) {
                $key = mb_strtolower(trim($st->full_name));
                if ($existingNames->has($key)) {
                    continue;
                }
                $teachingClass->students()->create([
                    'school_class_id' => $teachingClass->school_class_id,
                    'teaching_class_id' => $teachingClass->id,
                    'order_number' => ++$maxOrder,
                    'nis_nisn' => $st->nis_nisn,
                    'full_name' => $st->full_name,
                    'gender' => $st->gender,
                    'is_active' => true,
                ]);
                $added++;
            }

            if ($request->boolean('copy_homeroom')) {
                $teachingClass->update([
                    'homeroom_teacher_name' => $source->homeroom_teacher_name,
                    'homeroom_teacher_nip' => $source->homeroom_teacher_nip,
                    'homeroom_teacher_signature' => $source->homeroom_teacher_signature,
                ]);
                if ($teachingClass->schoolClass) {
                    $teachingClass->schoolClass->update([
                        'homeroom_teacher_name' => $source->homeroom_teacher_name,
                        'homeroom_teacher_nip' => $source->homeroom_teacher_nip,
                        'homeroom_teacher_signature' => $source->homeroom_teacher_signature,
                    ]);
                }
            }
        });

        return back()->with('success', "Berhasil menyalin {$added} murid dari kelas {$source->class_name}.");
    }

    public function downloadStudentTemplate(Request $request, TeachingClass $teachingClass, ExcelExportImportService $excelService)
    {
        $this->authorizeClassAccess($request->user(), $teachingClass);

        return $excelService->downloadStudentTemplate($teachingClass);
    }

    public function importStudents(
        Request $request,
        TeachingClass $teachingClass,
        ExcelExportImportService $excelService,
        GradeCalculationService $gradeService
    ): RedirectResponse {
        $user = $request->user();
        $this->authorizeClassAccess($user, $teachingClass);
        abort_unless($user->isAdmin(), 403, 'Akses ditolak. Pengelolaan data peserta didik dikontrol secara terpusat oleh Administrator Sekolah.');

        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $count = $excelService->importStudents(
            $teachingClass,
            $request->file('excel_file')->getRealPath(),
            $request->boolean('replace_existing')
        );

        if ($teachingClass->school_class_id) {
            foreach (TeachingClass::where('school_class_id', $teachingClass->school_class_id)->get() as $tc) {
                $gradeService->recalculateClass($tc);
            }
        } else {
            $gradeService->recalculateClass($teachingClass);
        }

        return back()->with('success', "Berhasil mengimpor {$count} data peserta didik dari file Excel.");
    }
}
