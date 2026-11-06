<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\SchoolScheduleService;
use App\Support\SignatureImageHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherAccountController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->resolveAcademicYear($request);
        $search = $request->string('q')->trim()->toString();

        $query = User::query()
            ->with(['teacherSubjects.subject'])
            ->withCount([
                'teachingClasses as total_classes_count',
                'teachingClasses as semester_classes_count' => fn ($q) => $q->where('academic_year_id', $academicYear->id),
            ])
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('default_subject', 'like', "%{$search}%");
            });
        }

        $allUsers = User::query()->where('is_active', true)->get();
        $usersByClean = [];
        foreach ($allUsers as $u) {
            $c = SchoolScheduleService::cleanAlphaString($u->name);
            $p = SchoolScheduleService::cleanAlphaString(explode(',', $u->name)[0]);
            if ($c !== '') {
                $usersByClean[$c] = $u;
            }
            if ($p !== '' && strlen($p) >= 4) {
                $usersByClean[$p] = $u;
            }
        }

        // Ambil guru dari Master Jadwal dan konsolidasikan jika memiliki beberapa kode/mapel (misal 14A & 14B)
        $scheduleTeachersRaw = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('code')
            ->get();

        $grouped = [];
        foreach ($scheduleTeachersRaw as $st) {
            $name = trim($st->name);
            if ($name === '') {
                continue;
            }

            $cName = SchoolScheduleService::cleanAlphaString($name);
            $pName = SchoolScheduleService::cleanAlphaString(explode(',', $name)[0]);
            $groupKey = (strlen($pName) >= 4) ? $pName : $cName;

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'name' => $name,
                    'codes' => [],
                    'subjects' => [],
                    'homerooms' => [],
                    'pikets' => [],
                    'user_id' => $st->user_id,
                ];
            }

            if (! in_array($st->code, $grouped[$groupKey]['codes'])) {
                $grouped[$groupKey]['codes'][] = $st->code;
            }

            $rawSubj = trim(preg_replace('/^[a-z]\.\s*/i', '', $st->subject ?: ''));
            if ($rawSubj !== '' && ! in_array($rawSubj, $grouped[$groupKey]['subjects'])) {
                $grouped[$groupKey]['subjects'][] = $rawSubj;
            }

            if ($st->homeroom_class && ! in_array($st->homeroom_class, $grouped[$groupKey]['homerooms'])) {
                $grouped[$groupKey]['homerooms'][] = $st->homeroom_class;
            }

            if ($st->piket_day && ! in_array($st->piket_day, $grouped[$groupKey]['pikets'])) {
                $grouped[$groupKey]['pikets'][] = $st->piket_day;
            }

            if ($st->user_id) {
                $grouped[$groupKey]['user_id'] = $st->user_id;
            }
        }

        // Hitung total jam KBM di jadwal per kode guru
        $itemHoursByCode = SchoolScheduleItem::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereNotNull('teacher_code')
            ->selectRaw('teacher_code, COUNT(*) as hours_count')
            ->groupBy('teacher_code')
            ->pluck('hours_count', 'teacher_code');

        $scheduleTeachers = [];
        foreach ($grouped as $g) {
            $codesLabel = implode(', ', $g['codes']);
            $subjectsLabel = implode(', ', $g['subjects']);
            $homeroomLabel = implode(', ', $g['homerooms']);
            $piketLabel = implode(', ', $g['pikets']);

            $totalHours = 0;
            foreach ($g['codes'] as $cd) {
                $totalHours += ($itemHoursByCode[$cd] ?? 0);
            }

            $hasAccount = false;
            $matchedUser = null;
            if ($g['user_id']) {
                $matchedUser = $allUsers->firstWhere('id', $g['user_id']);
                $hasAccount = (bool) $matchedUser;
            }
            if (! $hasAccount) {
                $c = SchoolScheduleService::cleanAlphaString($g['name']);
                $p = SchoolScheduleService::cleanAlphaString(explode(',', $g['name'])[0]);
                $matchedUser = $usersByClean[$c] ?? ($usersByClean[$p] ?? null);
                $hasAccount = (bool) $matchedUser;
            }

            // Saran email otomatis
            $nameParts = explode(' ', trim(explode(',', $g['name'])[0]));
            $first = strtolower(preg_replace('/[^a-z0-9]/', '', $nameParts[0] ?? 'guru'));
            $last = isset($nameParts[1]) ? strtolower(preg_replace('/[^a-z0-9]/', '', $nameParts[1])) : '';
            $suggestedEmail = $last ? "{$first}.{$last}@smpn2lemahabang.sch.id" : "{$first}@smpn2lemahabang.sch.id";

            $scheduleTeachers[] = [
                'name' => $g['name'],
                'codes' => $g['codes'],
                'codes_label' => $codesLabel,
                'subjects' => $g['subjects'],
                'subjects_label' => $subjectsLabel ?: '-',
                'homeroom_label' => $homeroomLabel,
                'piket_label' => $piketLabel,
                'total_hours' => $totalHours,
                'has_account' => $hasAccount,
                'user_id' => $matchedUser?->id,
                'user_name' => $matchedUser?->name,
                'suggested_email' => $suggestedEmail,
            ];
        }

        // Urutkan: guru jadwal yang belum punya akun ditaruh di paling atas
        usort($scheduleTeachers, function ($a, $b) {
            if ($a['has_account'] === $b['has_account']) {
                return strnatcasecmp($a['codes'][0] ?? '', $b['codes'][0] ?? '');
            }
            return $a['has_account'] ? 1 : -1;
        });

        return view('admin.teachers', [
            'users' => $query->get(),
            'search' => $search,
            'scheduleTeachers' => $scheduleTeachers,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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
            'signature_file' => ['nullable', 'image', 'max:2048'],
            'teacher_subjects' => ['nullable', 'array'],
            'teacher_subjects.*.subject_id' => ['required_with:teacher_subjects', 'exists:subjects,id'],
            'teacher_subjects.*.teaching_code' => ['nullable', 'string', 'max:30'],
        ]);

        $sigData = null;
        if ($request->hasFile('signature_file')) {
            $file = $request->file('signature_file');
            $mime = $file->getMimeType() ?: 'image/png';
            $rawBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
            $normalized = SignatureImageHelper::normalizeInk($rawBase64);
            $sigData = $normalized['data_uri'] ?? $rawBase64;
        } elseif (! empty($validated['signature_data'])) {
            $normalized = SignatureImageHelper::normalizeInk($validated['signature_data']);
            $sigData = $normalized['data_uri'] ?? $validated['signature_data'];
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'] ?? null,
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'default_subject' => $validated['default_subject'] ?? null,
            'signature_data' => $sigData,
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        // Simpan penugasan mapel & kode mengajar
        $assignedSubjectNames = [];
        $extractedCodes = [];
        if (! empty($validated['teacher_subjects']) && is_array($validated['teacher_subjects'])) {
            foreach ($validated['teacher_subjects'] as $row) {
                if (! empty($row['subject_id'])) {
                    $tCode = ! empty($row['teaching_code']) ? trim($row['teaching_code']) : null;
                    $ts = TeacherSubject::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'subject_id' => $row['subject_id'],
                        ],
                        [
                            'teaching_code' => $tCode,
                        ]
                    );
                    if ($ts->subject) {
                        $assignedSubjectNames[] = $ts->subject->name;
                    }
                    if ($tCode) {
                        $extractedCodes[] = $tCode;
                    }
                }
            }
        } elseif (! empty($validated['default_subject'])) {
            $assignedSubjectNames[] = $validated['default_subject'];
        }

        if (! empty($assignedSubjectNames)) {
            $user->update(['default_subject' => implode(', ', array_unique($assignedSubjectNames))]);
        }

        $academicYear = $this->resolveAcademicYear($request);
        $scheduleService = app(SchoolScheduleService::class);

        // Ekstrak kode jadwal jika dipilih dari form
        $scheduleCodes = [];
        if (! empty($validated['schedule_codes'])) {
            $scheduleCodes = array_filter(array_map('trim', explode(',', $validated['schedule_codes'])));
        }

        // Gabungkan juga kode dari penugasan mapel jika ada
        if (! empty($extractedCodes)) {
            $scheduleCodes = array_unique(array_merge($scheduleCodes, $extractedCodes));
        }

        // Jika tidak ada schedule_codes eksplisit, coba cocokkan berdasarkan nama
        if (empty($scheduleCodes) && $user->role === 'guru') {
            $cleanName = SchoolScheduleService::cleanAlphaString($user->name);
            $pureName = SchoolScheduleService::cleanAlphaString(explode(',', $user->name)[0]);

            $matchingTeachers = SchoolScheduleTeacher::query()
                ->where('academic_year_id', $academicYear->id)
                ->get()
                ->filter(function ($t) use ($cleanName, $pureName) {
                    $cleanT = SchoolScheduleService::cleanAlphaString($t->name);
                    $pureT = SchoolScheduleService::cleanAlphaString(explode(',', $t->name)[0]);
                    return $cleanT === $cleanName || $pureT === $pureName;
                });

            if ($matchingTeachers->isNotEmpty()) {
                $scheduleCodes = $matchingTeachers->pluck('code')->all();
            }
        }

        $integratedMsg = '';
        if (! empty($scheduleCodes) && $user->role === 'guru') {
            // Tautkan kode guru di Master Jadwal
            SchoolScheduleTeacher::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereIn('code', $scheduleCodes)
                ->update(['user_id' => $user->id]);

            SchoolScheduleItem::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereIn('teacher_code', $scheduleCodes)
                ->update(['user_id' => $user->id]);

            // Sinkronkan ke TeachingClass
            $syncResult = $scheduleService->syncToTeachingClasses($academicYear);
            $codesStr = implode(', ', $scheduleCodes);
            $createdClassCount = $syncResult['created'] ?? 0;
            $integratedMsg = " dan otomatis terintegrasi ke Master Jadwal (Kode {$codesStr} • {$createdClassCount} buku kelas administrasi otomatis disiapkan).";
        }

        return back()->with('success', "Data Master Guru {$user->name} berhasil disimpan{$integratedMsg}");
    }

    public function update(Request $request, User $teacher): RedirectResponse
    {
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
            'signature_file' => ['nullable', 'image', 'max:2048'],
            'remove_signature' => ['nullable', 'boolean'],
            'teacher_subjects' => ['nullable', 'array'],
            'teacher_subjects.*.subject_id' => ['required_with:teacher_subjects', 'exists:subjects,id'],
            'teacher_subjects.*.teaching_code' => ['nullable', 'string', 'max:30'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'] ?? null,
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'default_subject' => $validated['default_subject'] ?? null,
        ];

        if ($request->boolean('remove_signature')) {
            $payload['signature_data'] = null;
        } elseif ($request->hasFile('signature_file')) {
            $file = $request->file('signature_file');
            $mime = $file->getMimeType() ?: 'image/png';
            $rawBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
            $normalized = SignatureImageHelper::normalizeInk($rawBase64);
            $payload['signature_data'] = $normalized['data_uri'] ?? $rawBase64;
        } elseif (! empty($validated['signature_data'])) {
            $normalized = SignatureImageHelper::normalizeInk($validated['signature_data']);
            $payload['signature_data'] = $normalized['data_uri'] ?? $validated['signature_data'];
        }

        if ($teacher->id !== $request->user()->id && $request->has('is_active')) {
            $payload['is_active'] = $request->boolean('is_active');
        }

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        // Sinkronkan penugasan mapel & kode mengajar jika dikirim
        if ($request->has('teacher_subjects')) {
            TeacherSubject::where('user_id', $teacher->id)->delete();
            $assignedSubjectNames = [];
            if (! empty($validated['teacher_subjects']) && is_array($validated['teacher_subjects'])) {
                foreach ($validated['teacher_subjects'] as $row) {
                    if (! empty($row['subject_id'])) {
                        $ts = TeacherSubject::query()->create([
                            'user_id' => $teacher->id,
                            'subject_id' => $row['subject_id'],
                            'teaching_code' => ! empty($row['teaching_code']) ? trim($row['teaching_code']) : null,
                        ]);
                        if ($ts->subject) {
                            $assignedSubjectNames[] = $ts->subject->name;
                        }
                    }
                }
            }
            if (! empty($assignedSubjectNames)) {
                $payload['default_subject'] = implode(', ', array_unique($assignedSubjectNames));
            }
        }

        $teacher->update($payload);

        return back()->with('success', "Data Master Guru {$teacher->name} berhasil diperbarui.");
    }

    public function updateSignature(Request $request, User $teacher): RedirectResponse
    {
        if ($request->boolean('remove_signature')) {
            $teacher->update(['signature_data' => null]);
            return back()->with('success', "Tanda tangan digital {$teacher->name} berhasil dihapus.");
        }

        $sigData = null;
        if ($request->hasFile('signature_file')) {
            $request->validate([
                'signature_file' => ['required', 'image', 'max:2048'],
            ]);
            $file = $request->file('signature_file');
            $mime = $file->getMimeType() ?: 'image/png';
            $rawBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
            $normalized = SignatureImageHelper::normalizeInk($rawBase64);
            $sigData = $normalized['data_uri'] ?? $rawBase64;
        } elseif ($request->filled('signature_data')) {
            $raw = $request->input('signature_data');
            $normalized = SignatureImageHelper::normalizeInk($raw);
            $sigData = $normalized['data_uri'] ?? $raw;
        }

        if ($sigData) {
            $teacher->update(['signature_data' => $sigData]);
            return back()->with('success', "Tanda tangan digital {$teacher->name} berhasil diperbarui.");
        }

        return back()->with('error', 'Tidak ada data tanda tangan yang diunggah.');
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
        $teacher->delete();

        return back()->with('success', "Akun {$name} berhasil dihapus.");
    }
}
