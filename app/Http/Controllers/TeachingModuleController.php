<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\TeachingModule;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeachingModuleController extends Controller
{
    /**
     * Tampilkan daftar Modul Ajar (Guru: Modul Saya, Admin: Tab Supervisi Seluruh Guru & Modul Saya)
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $academicYear = $this->resolveAcademicYear($request);

        $search = $request->string('q')->trim()->toString();
        $gradeFilter = $request->string('grade_level')->trim()->toString() ?: null;
        $subjectFilter = $request->integer('subject_id') ?: null;
        $statusFilter = $request->string('status')->trim()->toString() ?: null;
        $teacherFilter = $request->integer('teacher_id') ?: null;
        $viewMode = $request->string('view')->trim()->toString();

        // Hitung statistik untuk Guru
        $myTotal = TeachingModule::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->count();
        $mySubmitted = TeachingModule::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->where('status', TeachingModule::STATUS_SUBMITTED)
            ->count();
        $myApproved = TeachingModule::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->where('status', TeachingModule::STATUS_APPROVED)
            ->count();
        $myRevision = TeachingModule::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->where('status', TeachingModule::STATUS_REVISION)
            ->count();
        $myDraft = TeachingModule::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->where('status', TeachingModule::STATUS_DRAFT)
            ->count();

        // Hitung statistik global untuk Admin / Wakasek
        $allTotal = 0;
        $allSubmitted = 0;
        $allApproved = 0;
        $allRevision = 0;
        if ($user->isAdmin()) {
            $allTotal = TeachingModule::query()
                ->where('academic_year_id', $academicYear->id)
                ->count();
            $allSubmitted = TeachingModule::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', TeachingModule::STATUS_SUBMITTED)
                ->count();
            $allApproved = TeachingModule::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', TeachingModule::STATUS_APPROVED)
                ->count();
            $allRevision = TeachingModule::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', TeachingModule::STATUS_REVISION)
                ->count();
        }

        // Tentukan mode tampilan
        $effectiveView = 'my';
        if (! $user->isAdmin()) {
            $effectiveView = 'my';
        } else {
            if ($teacherFilter) {
                $effectiveView = 'supervision';
            } elseif ($viewMode === 'my') {
                $effectiveView = 'my';
            } else {
                // Default tampilan Admin/Wakasek: Supervisi seluruh guru jika ada kiriman, atau jika ditentukan
                $effectiveView = $viewMode === 'supervision' || ($myTotal === 0 && $allTotal > 0) ? 'supervision' : ($viewMode ?: 'supervision');
            }
        }

        $query = TeachingModule::query()
            ->with(['teacher', 'subject', 'schoolClass', 'approver', 'academicYear'])
            ->where('academic_year_id', $academicYear->id);

        if ($effectiveView === 'my') {
            $query->where('teacher_id', $user->id);
        } elseif ($teacherFilter) {
            $query->where('teacher_id', $teacherFilter);
        }

        if ($gradeFilter) {
            $query->where('grade_level', $gradeFilter);
        }

        if ($subjectFilter) {
            $query->where('subject_id', $subjectFilter);
        }

        if ($statusFilter && in_array($statusFilter, [
            TeachingModule::STATUS_DRAFT,
            TeachingModule::STATUS_SUBMITTED,
            TeachingModule::STATUS_APPROVED,
            TeachingModule::STATUS_REVISION,
        ], true)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('topic', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhereHas('teacher', fn ($tQ) => $tQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($sQ) => $sQ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $modules = $query->orderByDesc('updated_at')->paginate(12)->withQueryString();

        $subjects = Subject::query()->where('is_active', true)->orderBy('name')->get();
        $schoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->orderBy('grade_level')->orderBy('name')->get();
        $teachers = $user->isAdmin() ? User::query()->where('is_active', true)->orderBy('name')->get() : collect();
        $gradeLevels = ['VII', 'VIII', 'IX'];

        // KBM spesifik yang secara resmi diampu oleh guru yang sedang login
        $teacherKbm = $this->getTeacherAssignedKbm($user, $academicYear);
        $teacherSubjects = $teacherKbm['subjects'];
        $teacherSchoolClasses = $teacherKbm['schoolClasses'];
        $teacherGradeLevels = $teacherKbm['gradeLevels'];

        // Filter mapel & tingkat pada toolbar halaman
        $filterSubjects = ($user->isAdmin() && $effectiveView === 'supervision')
            ? $subjects
            : $teacherSubjects;

        $filterGradeLevels = ($user->isAdmin() && $effectiveView === 'supervision')
            ? $gradeLevels
            : $teacherGradeLevels;

        return view('workspace.modules.index', [
            'modules' => $modules,
            'academicYear' => $academicYear,
            'subjects' => $subjects,
            'schoolClasses' => $schoolClasses,
            'teachers' => $teachers,
            'gradeLevels' => $gradeLevels,
            'teacherSubjects' => $teacherSubjects,
            'teacherSchoolClasses' => $teacherSchoolClasses,
            'teacherGradeLevels' => $teacherGradeLevels,
            'filterSubjects' => $filterSubjects,
            'filterGradeLevels' => $filterGradeLevels,
            'effectiveView' => $effectiveView,
            'myStats' => [
                'total' => $myTotal,
                'submitted' => $mySubmitted,
                'approved' => $myApproved,
                'revision' => $myRevision,
                'draft' => $myDraft,
            ],
            'adminStats' => [
                'total' => $allTotal,
                'submitted' => $allSubmitted,
                'approved' => $allApproved,
                'revision' => $allRevision,
            ],
            'filters' => [
                'q' => $search,
                'grade_level' => $gradeFilter,
                'subject_id' => $subjectFilter,
                'status' => $statusFilter,
                'teacher_id' => $teacherFilter,
                'view' => $viewMode,
            ],
        ]);
    }

    /**
     * Ambil data Mata Pelajaran, Rombongan Belajar, dan Tingkat Kelas
     * yang secara resmi diampu oleh guru pada tahun ajaran aktif.
     */
    public function getTeacherAssignedKbm(User $user, AcademicYear $academicYear): array
    {
        $scheduleService = app(SchoolScheduleService::class);

        // 1. Dari TeachingClass (Buku Administrasi Kelas) tahun aktif
        $teachingClasses = TeachingClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $user->id)
            ->with(['subject', 'schoolClass'])
            ->get();

        $subjectIds = $teachingClasses->pluck('subject_id')->filter()->all();
        $schoolClassIds = $teachingClasses->pluck('school_class_id')->filter()->all();

        // 2. Dari Master Jadwal Sekolah (SchoolScheduleService)
        $allocations = $scheduleService->getTeacherClassAllocations($academicYear, $user);
        foreach ($allocations as $alloc) {
            if (! empty($alloc['subject_id'])) {
                $subjectIds[] = (int) $alloc['subject_id'];
            }
            if (! empty($alloc['school_class_id'])) {
                $schoolClassIds[] = (int) $alloc['school_class_id'];
            }
        }

        // 3. Dari Penugasan Mapel Guru (teacher_subjects)
        $userSubjectIds = $user->subjects()->pluck('subjects.id')->all();
        $subjectIds = array_values(array_unique(array_filter(array_merge($subjectIds, $userSubjectIds))));
        $schoolClassIds = array_values(array_unique(array_filter($schoolClassIds)));

        $mySubjects = Subject::query()
            ->where('is_active', true)
            ->whereIn('id', $subjectIds)
            ->orderBy('name')
            ->get();

        $mySchoolClasses = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('id', $schoolClassIds)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        // Fallback untuk admin murni yang tidak memiliki jadwal KBM sama sekali
        if ($user->isAdmin() && $mySubjects->isEmpty()) {
            $mySubjects = Subject::query()->where('is_active', true)->orderBy('name')->get();
            $mySchoolClasses = SchoolClass::query()->where('academic_year_id', $academicYear->id)->orderBy('grade_level')->orderBy('name')->get();
        }

        $gradeLevels = $mySchoolClasses->pluck('grade_level')->filter()->unique()->values()->all();
        if (empty($gradeLevels)) {
            $gradeLevels = $mySubjects->isNotEmpty() ? ['VII', 'VIII', 'IX'] : [];
        }

        return [
            'subjects' => $mySubjects,
            'schoolClasses' => $mySchoolClasses,
            'gradeLevels' => $gradeLevels,
        ];
    }

    /**
     * Simpan Modul Ajar baru (Upload Berkas PDF)
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $academicYear = $this->resolveAcademicYear($request);

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'exists:users,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade_level' => ['required', 'in:VII,VIII,IX'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'semester' => ['nullable', 'in:Ganjil,Genap'],
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['nullable', 'string', 'max:255'],
            'target_meetings' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['nullable', 'in:draft,submitted'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'], // Maksimum 20 MB PDF
        ], [
            'file.required' => 'Berkas PDF Modul Ajar wajib diunggah.',
            'file.mimes' => 'Format berkas harus berupa dokumen PDF (.pdf).',
            'file.max' => 'Ukuran berkas PDF tidak boleh melebihi 20 MB.',
        ]);

        $ownerId = ($user->isAdmin() && ! empty($validated['teacher_id']))
            ? (int) $validated['teacher_id']
            : $user->id;
        $targetTeacher = ($ownerId === $user->id) ? $user : (User::find($ownerId) ?? $user);

        // Validasi: pastikan mapel dan rombel termasuk yang diampu oleh guru tersebut
        $kbm = $this->getTeacherAssignedKbm($targetTeacher, $academicYear);
        if ($kbm['subjects']->isNotEmpty() && ! $kbm['subjects']->pluck('id')->contains((int) $validated['subject_id'])) {
            throw ValidationException::withMessages([
                'subject_id' => 'Mata pelajaran yang dipilih tidak termasuk dalam mata pelajaran yang Anda ampu.',
            ]);
        }

        if (! empty($validated['school_class_id']) && $kbm['schoolClasses']->isNotEmpty() && ! $kbm['schoolClasses']->pluck('id')->contains((int) $validated['school_class_id'])) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Rombongan belajar yang dipilih tidak termasuk dalam kelas yang Anda ampu.',
            ]);
        }

        $file = $request->file('file');
        $originalFileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $storedPath = $file->store("modul-ajar/{$academicYear->id}", 'public');

        $status = ($validated['status'] ?? 'submitted') === 'draft'
            ? TeachingModule::STATUS_DRAFT
            : TeachingModule::STATUS_SUBMITTED;

        $module = TeachingModule::query()->create([
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $ownerId,
            'subject_id' => (int) $validated['subject_id'],
            'school_class_id' => ! empty($validated['school_class_id']) ? (int) $validated['school_class_id'] : null,
            'grade_level' => $validated['grade_level'],
            'semester' => $validated['semester'] ?? $academicYear->semester,
            'title' => trim($validated['title']),
            'topic' => ! empty($validated['topic']) ? trim($validated['topic']) : null,
            'target_meetings' => ! empty($validated['target_meetings']) ? trim($validated['target_meetings']) : null,
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'file_path' => $storedPath,
            'file_name' => $originalFileName,
            'file_size' => $fileSize,
            'status' => $status,
        ]);

        $msg = $status === TeachingModule::STATUS_SUBMITTED
            ? "Modul Ajar '{$module->title}' berhasil diunggah dan diajukan untuk telaah Wakasek."
            : "Modul Ajar '{$module->title}' berhasil disimpan sebagai draf.";

        return redirect()->route('teaching-modules.index')->with('success', $msg);
    }

    /**
     * Perbarui data Modul Ajar & ganti file PDF (opsional)
     */
    public function update(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $request->user();
        if (! $module->canBeEditedBy($user)) {
            abort(403, 'Anda tidak memiliki hak untuk mengubah modul ajar ini.');
        }

        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade_level' => ['required', 'in:VII,VIII,IX'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'semester' => ['nullable', 'in:Ganjil,Genap'],
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['nullable', 'string', 'max:255'],
            'target_meetings' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['nullable', 'in:draft,submitted'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'file.mimes' => 'Format berkas harus berupa dokumen PDF (.pdf).',
            'file.max' => 'Ukuran berkas PDF tidak boleh melebihi 20 MB.',
        ]);

        $academicYear = $module->academicYear ?: $this->resolveAcademicYear($request);
        $kbm = $this->getTeacherAssignedKbm($module->teacher ?: $user, $academicYear);
        if ($kbm['subjects']->isNotEmpty() && ! $kbm['subjects']->pluck('id')->contains((int) $validated['subject_id'])) {
            throw ValidationException::withMessages([
                'subject_id' => 'Mata pelajaran yang dipilih tidak termasuk dalam mata pelajaran yang Anda ampu.',
            ]);
        }

        if (! empty($validated['school_class_id']) && $kbm['schoolClasses']->isNotEmpty() && ! $kbm['schoolClasses']->pluck('id')->contains((int) $validated['school_class_id'])) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Rombongan belajar yang dipilih tidak termasuk dalam kelas yang Anda ampu.',
            ]);
        }

        $payload = [
            'subject_id' => (int) $validated['subject_id'],
            'school_class_id' => ! empty($validated['school_class_id']) ? (int) $validated['school_class_id'] : null,
            'grade_level' => $validated['grade_level'],
            'semester' => $validated['semester'] ?? $module->semester,
            'title' => trim($validated['title']),
            'topic' => ! empty($validated['topic']) ? trim($validated['topic']) : null,
            'target_meetings' => ! empty($validated['target_meetings']) ? trim($validated['target_meetings']) : null,
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
        ];

        // Jika ada unggahan berkas baru, ganti berkas lama
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if ($module->file_path && Storage::disk('public')->exists($module->file_path)) {
                Storage::disk('public')->delete($module->file_path);
            }
            $payload['file_path'] = $file->store("modul-ajar/{$module->academic_year_id}", 'public');
            $payload['file_name'] = $file->getClientOriginalName();
            $payload['file_size'] = $file->getSize();
        }

        // Jika modul sebelumnya berstatus revisi atau draft dan guru mengirimkan ulang
        if ($request->input('status') === 'submitted' || $module->status === TeachingModule::STATUS_REVISION) {
            $payload['status'] = TeachingModule::STATUS_SUBMITTED;
            $payload['approved_at'] = null;
        } elseif ($request->input('status') === 'draft') {
            $payload['status'] = TeachingModule::STATUS_DRAFT;
        }

        $module->update($payload);

        return redirect()->route('teaching-modules.index')->with('success', "Modul Ajar '{$module->title}' berhasil diperbarui.");
    }

    /**
     * Hapus Modul Ajar beserta berkas PDF fisiknya
     */
    public function destroy(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $request->user();
        if (! $module->canBeDeletedBy($user)) {
            abort(403, 'Anda tidak memiliki hak untuk menghapus modul ajar ini.');
        }

        $title = $module->title;
        if ($module->file_path && Storage::disk('public')->exists($module->file_path)) {
            Storage::disk('public')->delete($module->file_path);
        }

        $module->delete();

        return redirect()->route('teaching-modules.index')->with('success', "Modul Ajar '{$title}' berhasil dihapus.");
    }

    /**
     * Tampilkan pratinjau berkas PDF langsung di peramban (inline view)
     */
    public function viewPdf(Request $request, TeachingModule $module): BinaryFileResponse
    {
        $user = $request->user();
        if (! $module->canBeEditedBy($user) && ! $user->isAdmin()) {
            // Guru lain di sekolah yang sama boleh membaca modul jika sudah approved
            if ($module->status !== TeachingModule::STATUS_APPROVED) {
                abort(403, 'Modul Ajar ini masih dalam tahap draf/telaah dan belum dapat diakses publik.');
            }
        }

        if (! Storage::disk('public')->exists($module->file_path)) {
            abort(404, 'Berkas PDF fisik tidak ditemukan pada server.');
        }

        $fullPath = Storage::disk('public')->path($module->file_path);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($module->file_name) . '"',
        ]);
    }

    /**
     * Unduh berkas PDF fisik
     */
    public function download(Request $request, TeachingModule $module): BinaryFileResponse
    {
        $user = $request->user();
        if (! $module->canBeEditedBy($user) && ! $user->isAdmin()) {
            if ($module->status !== TeachingModule::STATUS_APPROVED) {
                abort(403, 'Modul Ajar ini belum disetujui.');
            }
        }

        if (! Storage::disk('public')->exists($module->file_path)) {
            abort(404, 'Berkas PDF fisik tidak ditemukan.');
        }

        $fullPath = Storage::disk('public')->path($module->file_path);

        return response()->download($fullPath, $module->file_name);
    }

    /**
     * Guru mengajukan draf modul ajar untuk ditelaah Wakasek
     */
    public function submitForReview(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $request->user();
        if (! $module->canBeEditedBy($user)) {
            abort(403, 'Anda tidak berwenang mengajukan modul ini.');
        }

        $module->update([
            'status' => TeachingModule::STATUS_SUBMITTED,
            'approved_at' => null,
        ]);

        return redirect()->route('teaching-modules.index')->with('success', "Modul Ajar '{$module->title}' berhasil diajukan ke antrean telaah Wakasek.");
    }

    /**
     * Aksi Persetujuan Khusus Admin / Wakasek
     */
    public function approve(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Hanya Administrator atau Wakasek yang berwenang menyetujui modul ajar.');
        }

        $validated = $request->validate([
            'feedback_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $module->update([
            'status' => TeachingModule::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'feedback_notes' => ! empty($validated['feedback_notes']) ? trim($validated['feedback_notes']) : null,
        ]);

        return redirect()->back()->with('success', "Modul Ajar '{$module->title}' dari {$module->teacher->name} resmi disetujui & disahkan.");
    }

    /**
     * Aksi Permintaan Revisi Khusus Admin / Wakasek
     */
    public function reject(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Hanya Administrator atau Wakasek yang berwenang menelaah modul ajar.');
        }

        $validated = $request->validate([
            'feedback_notes' => ['required', 'string', 'max:1000'],
        ], [
            'feedback_notes.required' => 'Catatan revisi wajib diisi agar guru mengetahui bagian yang perlu diperbaiki.',
        ]);

        $module->update([
            'status' => TeachingModule::STATUS_REVISION,
            'approved_by' => $user->id,
            'approved_at' => null,
            'feedback_notes' => trim($validated['feedback_notes']),
        ]);

        return redirect()->back()->with('warning', "Modul Ajar '{$module->title}' dikembalikan ke {$module->teacher->name} dengan catatan perbaikan.");
    }

    /**
     * Kembalikan status modul ajar ke antrean telaah (Reset status)
     */
    public function resetStatus(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Hanya Administrator yang dapat mereset status telaah modul.');
        }

        $module->update([
            'status' => TeachingModule::STATUS_SUBMITTED,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return redirect()->back()->with('success', "Status Modul Ajar '{$module->title}' berhasil dikembalikan ke antrean telaah.");
    }
}
