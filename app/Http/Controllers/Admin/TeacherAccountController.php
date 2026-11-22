<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\SchoolScheduleService;
use App\Support\SignatureImageHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherAccountController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->resolveAcademicYear($request);
        $search = $request->string('q')->trim()->toString();
        $sort = $request->string('sort')->trim()->toString();
        $dir = strtolower($request->string('dir')->trim()->toString()) === 'desc' ? 'desc' : 'asc';

        $query = User::query()
            ->with([
                'teacherSubjects.subject',
                'homeroomSchoolClasses' => fn ($q) => $q->where('academic_year_id', $academicYear->id),
            ])
            ->withCount([
                'teachingClasses as total_classes_count',
                'teachingClasses as semester_classes_count' => fn ($q) => $q->where('academic_year_id', $academicYear->id),
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search, $academicYear) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('default_subject', 'like', "%{$search}%")
                    ->orWhere('piket_days', 'like', "%{$search}%")
                    ->orWhereHas('homeroomSchoolClasses', fn ($hcQ) => $hcQ
                        ->where('academic_year_id', $academicYear->id)
                        ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacherSubjects', function ($tsQ) use ($search) {
                        $tsQ->where('teaching_code', 'like', "%{$search}%")
                            ->orWhereHas('subject', fn ($sQ) => $sQ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                    });
            });
        }

        $users = $query->get();

        switch ($sort) {
            case 'name':
                $users = $dir === 'desc'
                    ? $users->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE)->values()
                    : $users->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
                break;
            case 'nip':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $nA = trim((string) $a->nip);
                    $nB = trim((string) $b->nip);
                    if ($nA === '' && $nB === '') return 0;
                    if ($nA === '') return 1;
                    if ($nB === '') return -1;
                    return $dir === 'desc' ? strnatcasecmp($nB, $nA) : strnatcasecmp($nA, $nB);
                })->values();
                break;
            case 'phone':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $pA = trim((string) $a->phone);
                    $pB = trim((string) $b->phone);
                    if ($pA === '' && $pB === '') return 0;
                    if ($pA === '') return 1;
                    if ($pB === '') return -1;
                    return $dir === 'desc' ? strnatcasecmp($pB, $pA) : strnatcasecmp($pA, $pB);
                })->values();
                break;
            case 'mapel':
            case 'subject':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $sA = $a->subject_codes_string ?: ($a->default_subject ?: '');
                    $sB = $b->subject_codes_string ?: ($b->default_subject ?: '');
                    if ($sA === '' && $sB === '') return 0;
                    if ($sA === '') return 1;
                    if ($sB === '') return -1;
                    return $dir === 'desc' ? strnatcasecmp($sB, $sA) : strnatcasecmp($sA, $sB);
                })->values();
                break;
            case 'teaching_code':
            case 'kode':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $tA = $a->teaching_codes_string ?: '';
                    $tB = $b->teaching_codes_string ?: '';
                    if ($tA === '' && $tB === '') return 0;
                    if ($tA === '') return 1;
                    if ($tB === '') return -1;
                    return $dir === 'desc' ? strnatcasecmp($tB, $tA) : strnatcasecmp($tA, $tB);
                })->values();
                break;
            case 'homeroom':
            case 'wali_kelas':
                $users = $users->sort(function ($a, $b) use ($dir, $academicYear) {
                    $wA = $a->homeroomClassForYear($academicYear->id)?->name ?: '';
                    $wB = $b->homeroomClassForYear($academicYear->id)?->name ?: '';
                    if ($wA === '' && $wB === '') return 0;
                    if ($wA === '') return 1;
                    if ($wB === '') return -1;
                    return $dir === 'desc' ? strnatcasecmp($wB, $wA) : strnatcasecmp($wA, $wB);
                })->values();
                break;
            case 'piket':
                $orderMap = array_flip(User::PIKET_DAYS);
                $users = $users->sort(function ($a, $b) use ($dir, $orderMap) {
                    $dA = $a->piket_days_list[0] ?? null;
                    $dB = $b->piket_days_list[0] ?? null;
                    if ($dA === null && $dB === null) return 0;
                    if ($dA === null) return 1;
                    if ($dB === null) return -1;
                    $oA = $orderMap[$dA] ?? 99;
                    $oB = $orderMap[$dB] ?? 99;
                    return $dir === 'desc' ? ($oB <=> $oA) : ($oA <=> $oB);
                })->values();
                break;
            case 'ttd':
            case 'signature':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $hasA = ! empty($a->signature_data) ? 1 : 0;
                    $hasB = ! empty($b->signature_data) ? 1 : 0;
                    if ($hasA !== $hasB) {
                        return $dir === 'desc' ? ($hasA - $hasB) : ($hasB - $hasA);
                    }
                    return strnatcasecmp($a->name, $b->name);
                })->values();
                break;
            case 'status':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $stA = $a->is_active ? 1 : 0;
                    $stB = $b->is_active ? 1 : 0;
                    if ($stA !== $stB) {
                        return $dir === 'desc' ? ($stA - $stB) : ($stB - $stA);
                    }
                    return strnatcasecmp($a->name, $b->name);
                })->values();
                break;
            case 'role':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    if ($a->role !== $b->role) {
                        return $dir === 'desc' ? strcmp($b->role, $a->role) : strcmp($a->role, $b->role);
                    }
                    return strnatcasecmp($a->name, $b->name);
                })->values();
                break;
            default:
                // Default: Admin paling atas, lalu urut nama A-Z
                $users = $users->sort(function ($a, $b) {
                    $adminA = $a->isAdmin() ? 0 : 1;
                    $adminB = $b->isAdmin() ? 0 : 1;
                    if ($adminA !== $adminB) {
                        return $adminA - $adminB;
                    }
                    return strnatcasecmp($a->name, $b->name);
                })->values();
                break;
        }

        $schoolClasses = SchoolClass::query()
            ->with('homeroomTeacher')
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $usedCodesMap = TeacherSubject::query()
            ->with('user')
            ->whereNotNull('teaching_code_normalized')
            ->get()
            ->mapWithKeys(fn (TeacherSubject $ts) => [
                $ts->teaching_code_normalized => [
                    'user_id' => $ts->user_id,
                    'user_name' => $ts->user?->name ?? '-',
                ],
            ])
            ->all();

        $scheduleService = app(SchoolScheduleService::class);

        return view('admin.teachers', [
            'academicYear' => $academicYear,
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'schoolClasses' => $schoolClasses,
            'piketDays' => User::PIKET_DAYS,
            'piketGrouped' => $scheduleService->getPiketGroupedByDay(),
            'usedCodesMap' => $usedCodesMap,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->resolveAcademicYear($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'nip' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['guru', 'admin'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'default_subject' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:6'],
            'schedule_codes' => ['nullable', 'string'],
            'signature_data' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'teacher_subjects' => ['nullable', 'array'],
            'teacher_subjects.*.subject_id' => ['nullable', 'exists:subjects,id'],
            'teacher_subjects.*.role_label' => ['nullable', 'string', 'max:120'],
            'teacher_subjects.*.teaching_code' => ['nullable', 'string', 'max:30'],
            'homeroom_school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'piket_days' => ['nullable', 'array'],
            'piket_days.*' => ['nullable', 'string', 'max:20'],
        ]);

        $subjectRows = $this->validateTeachingCodeConflicts($validated['teacher_subjects'] ?? []);
        $homeroomClassId = ! empty($validated['homeroom_school_class_id']) ? (int) $validated['homeroom_school_class_id'] : null;
        $this->validateHomeroomConflict($homeroomClassId, $academicYear->id);

        $piketDays = User::normalizePiketDays($request->input('piket_days', []));

        $sigData = $this->resolveTeacherSignatureInput($request);

        $user = DB::transaction(function () use ($validated, $sigData, $piketDays, $subjectRows, $homeroomClassId, $academicYear) {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => trim($validated['email']),
                'nip' => $validated['nip'] ?? null,
                'role' => $validated['role'],
                'phone' => $validated['phone'] ?? null,
                'default_subject' => $validated['default_subject'] ?? null,
                'piket_days' => ! empty($piketDays) ? $piketDays : null,
                'signature_data' => $sigData,
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            $assignedSubjectNames = [];
            foreach ($subjectRows as $row) {
                if ($row['subject_id'] !== null) {
                    $ts = TeacherSubject::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'subject_id' => $row['subject_id'],
                        ],
                        [
                            'role_label' => null,
                            'teaching_code' => $row['teaching_code'],
                        ]
                    );
                } else {
                    $ts = TeacherSubject::query()->create([
                        'user_id' => $user->id,
                        'subject_id' => null,
                        'role_label' => $row['role_label'],
                        'teaching_code' => $row['teaching_code'],
                    ]);
                }
                $label = $ts->subject?->name ?: $ts->role_label;
                if ($label) {
                    $assignedSubjectNames[] = $label;
                }
            }

            if (! empty($assignedSubjectNames)) {
                $user->update(['default_subject' => implode(', ', array_unique($assignedSubjectNames))]);
            }

            if ($homeroomClassId) {
                $sc = SchoolClass::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->findOrFail($homeroomClassId);
                $sc->applyHomeroomTeacher($user);
            }

            return $user;
        });

        $scheduleService = app(SchoolScheduleService::class);
        $scheduleService->syncScheduleTeachersFromMaster($academicYear);
        $scheduleService->syncToTeachingClasses($academicYear);

        return back()->with('success', "Data Master Guru {$user->name} berhasil disimpan.");
    }

    public function update(Request $request, User $teacher): RedirectResponse
    {
        $academicYear = $this->resolveAcademicYear($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher->id)],
            'nip' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['guru', 'admin'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'default_subject' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
            'signature_data' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_signature' => ['nullable', 'boolean'],
            'teacher_subjects' => ['nullable', 'array'],
            'teacher_subjects.*.subject_id' => ['nullable', 'exists:subjects,id'],
            'teacher_subjects.*.role_label' => ['nullable', 'string', 'max:120'],
            'teacher_subjects.*.teaching_code' => ['nullable', 'string', 'max:30'],
            'homeroom_school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'piket_days' => ['nullable', 'array'],
            'piket_days.*' => ['nullable', 'string', 'max:20'],
        ]);

        $hasSubjectsInput = $request->has('teacher_subjects');
        $subjectRows = $hasSubjectsInput ? $this->validateTeachingCodeConflicts($validated['teacher_subjects'] ?? [], $teacher->id) : [];

        $hasHomeroomInput = $request->exists('homeroom_school_class_id');
        $homeroomClassId = ! empty($validated['homeroom_school_class_id']) ? (int) $validated['homeroom_school_class_id'] : null;
        if ($hasHomeroomInput) {
            $this->validateHomeroomConflict($homeroomClassId, $academicYear->id, $teacher->id);
        }

        $payload = [
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'nip' => $validated['nip'] ?? null,
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'default_subject' => $validated['default_subject'] ?? null,
        ];

        if ($request->boolean('has_piket_input') || $request->has('piket_days')) {
            $normDays = User::normalizePiketDays($request->input('piket_days', []));
            $payload['piket_days'] = ! empty($normDays) ? $normDays : null;
        }

        if ($request->boolean('remove_signature')) {
            $payload['signature_data'] = null;
        } elseif (($resolvedSig = $this->resolveTeacherSignatureInput($request)) !== null) {
            $payload['signature_data'] = $resolvedSig;
        }

        if ($teacher->id !== $request->user()->id && $request->has('is_active')) {
            $payload['is_active'] = $request->boolean('is_active');
        }

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        DB::transaction(function () use (
            $teacher,
            $academicYear,
            $hasSubjectsInput,
            $subjectRows,
            $hasHomeroomInput,
            $homeroomClassId,
            &$payload
        ) {
            if ($hasSubjectsInput) {
                TeacherSubject::where('user_id', $teacher->id)->delete();
                $assignedSubjectNames = [];
                foreach ($subjectRows as $row) {
                    $ts = TeacherSubject::query()->create([
                        'user_id' => $teacher->id,
                        'subject_id' => $row['subject_id'],
                        'role_label' => $row['subject_id'] !== null ? null : $row['role_label'],
                        'teaching_code' => $row['teaching_code'],
                    ]);
                    $label = $ts->subject?->name ?: $ts->role_label;
                    if ($label) {
                        $assignedSubjectNames[] = $label;
                    }
                }
                if (! empty($assignedSubjectNames)) {
                    $payload['default_subject'] = implode(', ', array_unique($assignedSubjectNames));
                }
            }

            $teacher->update($payload);
            $teacher->refresh();

            if ($hasHomeroomInput) {
                SchoolClass::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('homeroom_teacher_id', $teacher->id)
                    ->when($homeroomClassId, fn ($q) => $q->where('id', '!=', $homeroomClassId))
                    ->get()
                    ->each(fn (SchoolClass $oldClass) => $oldClass->applyHomeroomTeacher(null));

                if ($homeroomClassId) {
                    $newClass = SchoolClass::query()
                        ->where('academic_year_id', $academicYear->id)
                        ->findOrFail($homeroomClassId);
                    $newClass->applyHomeroomTeacher($teacher);
                }
            }

            // Sinkronkan identitas terbaru (nama, NIP, TTD) ke rombel wali kelas yang sedang diampu
            $teacher->homeroomSchoolClasses()
                ->get()
                ->each(fn (SchoolClass $sc) => $sc->applyHomeroomTeacher($teacher));
        });

        $scheduleService = app(SchoolScheduleService::class);
        $scheduleService->syncScheduleTeachersFromMaster($academicYear);
        $scheduleService->syncToTeachingClasses($academicYear);

        return back()->with('success', "Data Master Guru {$teacher->name} berhasil diperbarui.");
    }

    public function updateSignature(Request $request, User $teacher): RedirectResponse
    {
        if ($request->boolean('remove_signature')) {
            $teacher->update(['signature_data' => null]);
            $teacher->homeroomSchoolClasses()->get()->each(fn (SchoolClass $sc) => $sc->applyHomeroomTeacher($teacher));

            return back()->with('success', "Tanda tangan digital {$teacher->name} berhasil dihapus.");
        }

        if ($request->hasFile('signature_file')) {
            $request->validate([
                'signature_file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            ]);
        }

        $sigData = $this->resolveTeacherSignatureInput($request);

        if ($sigData) {
            $teacher->update(['signature_data' => $sigData]);
            $teacher->homeroomSchoolClasses()->get()->each(fn (SchoolClass $sc) => $sc->applyHomeroomTeacher($teacher));

            return back()->with('success', "Tanda tangan digital {$teacher->name} berhasil diperbarui.");
        }

        return back()->with('error', 'Tidak ada data tanda tangan yang diunggah.');
    }

    /**
     * Resolusi & sanitasi tanda tangan raster (PNG/JPEG/WEBP) dari file upload maupun kanvas Base64.
     */
    protected function resolveTeacherSignatureInput(Request $request): ?string
    {
        $rawDataUri = null;

        if ($request->hasFile('signature_file')) {
            $rawDataUri = $this->uploadedImageToDataUri($request->file('signature_file'), 'signature_file');
        } elseif ($request->filled('signature_data')) {
            $rawDataUri = $this->sanitizeRasterImageDataUri($request->input('signature_data'), 'signature_data');
        }

        if (! $rawDataUri) {
            return null;
        }

        $normalized = SignatureImageHelper::normalizeInk($rawDataUri);

        return $normalized['data_uri'] ?? $rawDataUri;
    }

    public function toggleStatus(Request $request, User $teacher): RedirectResponse
    {
        if ($teacher->id === $request->user()->id) {
            return back()->withErrors(['account' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
        }

        $teacher->update(['is_active' => ! $teacher->is_active]);
        $state = $teacher->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun {$teacher->name} berhasil {$state}.");
    }

    public function destroy(Request $request, User $teacher): RedirectResponse
    {
        if ($teacher->id === $request->user()->id) {
            return back()->withErrors(['account' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        $name = $teacher->name;

        DB::transaction(function () use ($teacher) {
            $teacher->homeroomSchoolClasses()->get()->each(fn (SchoolClass $sc) => $sc->applyHomeroomTeacher(null));
            $teacher->delete();
        });

        return back()->with('success', "Akun {$name} berhasil dihapus.");
    }

    /**
     * Validasi anti-bentrok kode mengajar antar mapel maupun antar guru.
     *
     * @return list<array{subject_id: ?int, role_label: ?string, teaching_code: ?string}>
     */
    protected function validateTeachingCodeConflicts(array $rows, ?int $ignoreUserId = null): array
    {
        $cleanRows = [];
        $seenInRequest = [];
        $codesToCheck = [];

        foreach ($rows as $row) {
            $subjectId = ! empty($row['subject_id']) ? (int) $row['subject_id'] : null;
            $roleLabel = trim((string) ($row['role_label'] ?? ''));
            $normCode = TeacherSubject::normalizeCode($row['teaching_code'] ?? null);

            if ($subjectId === null && $roleLabel === '' && $normCode === null) {
                continue;
            }
            if ($subjectId === null && $roleLabel === '') {
                $roleLabel = 'Tugas Non-Mapel';
            }

            if ($normCode !== null) {
                if (isset($seenInRequest[$normCode])) {
                    throw ValidationException::withMessages([
                        'teacher_subjects' => "Bentrok Kode Mengajar '{$normCode}': kode yang sama dimasukkan lebih dari satu kali pada formulir ini.",
                    ]);
                }
                $seenInRequest[$normCode] = true;
                $codesToCheck[] = $normCode;
            }

            $cleanRows[] = [
                'subject_id' => $subjectId,
                'role_label' => $subjectId !== null ? null : $roleLabel,
                'teaching_code' => $normCode,
            ];
        }

        if (! empty($codesToCheck)) {
            $conflict = TeacherSubject::query()
                ->with(['user', 'subject'])
                ->whereIn('teaching_code_normalized', $codesToCheck)
                ->when($ignoreUserId, fn ($q) => $q->where('user_id', '!=', $ignoreUserId))
                ->first();

            if ($conflict) {
                $ownerName = $conflict->user?->name ?? 'Guru lain';
                $subjName = $conflict->subject?->name ?: ($conflict->role_label ?: '-');
                throw ValidationException::withMessages([
                    'teacher_subjects' => "Bentrok Kode Mengajar '{$conflict->teaching_code_normalized}': sudah digunakan oleh {$ownerName} ({$subjName}). Satu kode mengajar hanya boleh dimiliki satu guru.",
                ]);
            }
        }

        return $cleanRows;
    }

    /**
     * Validasi anti-bentrok penugasan Wali Kelas pada tahun ajaran aktif.
     */
    protected function validateHomeroomConflict(?int $schoolClassId, int $academicYearId, ?int $teacherId = null): void
    {
        if (! $schoolClassId) {
            return;
        }

        $sc = SchoolClass::query()
            ->with('homeroomTeacher')
            ->where('academic_year_id', $academicYearId)
            ->find($schoolClassId);

        if (! $sc) {
            throw ValidationException::withMessages([
                'homeroom_school_class_id' => 'Rombel kelas yang dipilih tidak termasuk dalam tahun ajaran aktif.',
            ]);
        }

        if ($sc->homeroom_teacher_id !== null && (int) $sc->homeroom_teacher_id !== (int) $teacherId) {
            $owner = $sc->homeroomTeacher?->name ?: ($sc->homeroom_teacher_name ?: 'Guru lain');
            throw ValidationException::withMessages([
                'homeroom_school_class_id' => "Bentrok Wali Kelas: Rombel {$sc->name} sudah diampu oleh {$owner}. Lepas tugas wali kelas pada guru tersebut terlebih dahulu.",
            ]);
        }
    }
}
