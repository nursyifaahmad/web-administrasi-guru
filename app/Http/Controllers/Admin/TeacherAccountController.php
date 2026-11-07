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
        $sort = $request->string('sort')->trim()->toString();
        $dir = strtolower($request->string('dir')->trim()->toString()) === 'desc' ? 'desc' : 'asc';

        $query = User::query()
            ->with(['teacherSubjects.subject'])
            ->withCount([
                'teachingClasses as total_classes_count',
                'teachingClasses as semester_classes_count' => fn ($q) => $q->where('academic_year_id', $academicYear->id),
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('default_subject', 'like', "%{$search}%")
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
                    $nA = trim((string)$a->nip);
                    $nB = trim((string)$b->nip);
                    if ($nA === '' && $nB === '') return 0;
                    if ($nA === '') return 1;
                    if ($nB === '') return -1;
                    return $dir === 'desc' ? strnatcasecmp($nB, $nA) : strnatcasecmp($nA, $nB);
                })->values();
                break;
            case 'phone':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $pA = trim((string)$a->phone);
                    $pB = trim((string)$b->phone);
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
            case 'ttd':
            case 'signature':
                $users = $users->sort(function ($a, $b) use ($dir) {
                    $hasA = !empty($a->signature_data) ? 1 : 0;
                    $hasB = !empty($b->signature_data) ? 1 : 0;
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

        return view('admin.teachers', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
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
        $scheduleService->syncScheduleTeachersFromMaster($academicYear);
        $scheduleService->syncToTeachingClasses($academicYear);

        return back()->with('success', "Data Master Guru {$user->name} berhasil disimpan.");
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

        $academicYear = $this->resolveAcademicYear($request);
        $scheduleService = app(SchoolScheduleService::class);
        $scheduleService->syncScheduleTeachersFromMaster($academicYear);
        $scheduleService->syncToTeachingClasses($academicYear);

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
