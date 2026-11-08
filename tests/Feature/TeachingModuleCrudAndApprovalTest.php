<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\TeachingModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeachingModuleCrudAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function setupEnvironment(): array
    {
        Storage::fake('public');

        $admin = User::query()->create([
            'name' => 'Muhammad Irfan, S.Pd.',
            'email' => 'irfan.admin@sekolah.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Siti Aminah, S.Pd.',
            'email' => 'siti.guru@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $guruLain = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi.guru@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'code' => 'BIG-01',
            'name' => 'Bahasa Inggris',
            'order_number' => 1,
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);

        // Berikan penugasan KBM resmi untuk guru
        TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);

        // Hubungkan admin ke subject Bahasa Inggris
        $admin->subjects()->attach($subject->id, ['teaching_code' => '12']);

        return [$admin, $guru, $guruLain, $year, $subject, $schoolClass];
    }

    public function test_guest_cannot_access_teaching_modules(): void
    {
        $response = $this->get(route('teaching-modules.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_teacher_can_view_teaching_modules_index(): void
    {
        [, $guru] = $this->setupEnvironment();

        $response = $this->actingAs($guru)->get(route('teaching-modules.index'));
        $response->assertOk();
        $response->assertSee('Modul Ajar');
        $response->assertSee('Unggah Modul Ajar');
    }

    public function test_teacher_can_upload_pdf_teaching_module(): void
    {
        [, $guru, , $year, $subject, $schoolClass] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('modul-descriptive-text.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($guru)->post(route('teaching-modules.store'), [
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Ajar Descriptive Text VII-A',
            'topic' => 'Describing People and Animals',
            'target_meetings' => '3 Pertemuan (6 JP)',
            'description' => 'Materi pengayaan dan asesmen formatif',
            'status' => 'submitted',
            'file' => $fakePdf,
        ]);

        $response->assertRedirect(route('teaching-modules.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teaching_modules', [
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'grade_level' => 'VII',
            'title' => 'Modul Ajar Descriptive Text VII-A',
            'status' => TeachingModule::STATUS_SUBMITTED,
            'file_name' => 'modul-descriptive-text.pdf',
        ]);

        $created = TeachingModule::query()->where('title', 'Modul Ajar Descriptive Text VII-A')->first();
        $this->assertNotNull($created);
        Storage::disk('public')->assertExists($created->file_path);
    }

    public function test_validation_rejects_non_pdf_and_oversized_files(): void
    {
        [, $guru, , , $subject] = $this->setupEnvironment();

        // Bukan file PDF (DOCX)
        $fakeDoc = UploadedFile::fake()->create('modul.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response = $this->actingAs($guru)->post(route('teaching-modules.store'), [
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'title' => 'Modul Format Salah',
            'file' => $fakeDoc,
        ]);
        $response->assertSessionHasErrors(['file']);

        // File melebihi batas 20MB (25MB)
        $oversizedPdf = UploadedFile::fake()->create('modul-raksasa.pdf', 25600, 'application/pdf');
        $response2 = $this->actingAs($guru)->post(route('teaching-modules.store'), [
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'title' => 'Modul Terlalu Besar',
            'file' => $oversizedPdf,
        ]);
        $response2->assertSessionHasErrors(['file']);
    }

    public function test_teacher_can_update_metadata_and_replace_pdf(): void
    {
        [, $guru, , $year, $subject] = $this->setupEnvironment();

        $initialFile = UploadedFile::fake()->create('versi-1.pdf', 500, 'application/pdf');
        $path1 = $initialFile->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Versi 1',
            'file_path' => $path1,
            'file_name' => 'versi-1.pdf',
            'file_size' => 500 * 1024,
            'status' => TeachingModule::STATUS_DRAFT,
        ]);

        Storage::disk('public')->assertExists($path1);

        $newFile = UploadedFile::fake()->create('versi-2-revisi.pdf', 800, 'application/pdf');

        $response = $this->actingAs($guru)->put(route('teaching-modules.update', $module), [
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'title' => 'Modul Versi 2 Diperbarui',
            'topic' => 'Topik Baru',
            'status' => 'submitted',
            'file' => $newFile,
        ]);

        $response->assertRedirect(route('teaching-modules.index'));
        $module->refresh();

        $this->assertEquals('Modul Versi 2 Diperbarui', $module->title);
        $this->assertEquals('versi-2-revisi.pdf', $module->file_name);
        $this->assertEquals(TeachingModule::STATUS_SUBMITTED, $module->status);

        // Pastikan file lama terhapus dan file baru tersimpan
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists($module->file_path);
    }

    public function test_teacher_can_view_and_download_pdf(): void
    {
        [, $guru, , $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('dokumen-kbm.pdf', 200, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Dokumen KBM VII',
            'file_path' => $filePath,
            'file_name' => 'dokumen-kbm.pdf',
            'file_size' => 200 * 1024,
            'status' => TeachingModule::STATUS_SUBMITTED,
        ]);

        // Pratinjau PDF inline
        $viewResponse = $this->actingAs($guru)->get(route('teaching-modules.view-pdf', $module));
        $viewResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $viewResponse->headers->get('content-type'));

        // Unduh PDF
        $downloadResponse = $this->actingAs($guru)->get(route('teaching-modules.download', $module));
        $downloadResponse->assertOk();
    }

    public function test_teacher_can_delete_own_teaching_module(): void
    {
        [, $guru, , $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('hapus-saya.pdf', 100, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Mau Dihapus',
            'file_path' => $filePath,
            'file_name' => 'hapus-saya.pdf',
            'file_size' => 100 * 1024,
            'status' => TeachingModule::STATUS_DRAFT,
        ]);

        Storage::disk('public')->assertExists($filePath);

        $response = $this->actingAs($guru)->delete(route('teaching-modules.destroy', $module));
        $response->assertRedirect(route('teaching-modules.index'));

        $this->assertDatabaseMissing('teaching_modules', ['id' => $module->id]);
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_teacher_cannot_edit_or_delete_other_teachers_module(): void
    {
        [, $guru, $guruLain, $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('privat.pdf', 100, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Milik Siti',
            'file_path' => $filePath,
            'file_name' => 'privat.pdf',
            'file_size' => 100 * 1024,
            'status' => TeachingModule::STATUS_DRAFT,
        ]);

        // Guru lain mencoba mengedit
        $editResponse = $this->actingAs($guruLain)->put(route('teaching-modules.update', $module), [
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'title' => 'Coba Bajak Modul',
        ]);
        $editResponse->assertForbidden();

        // Guru lain mencoba menghapus
        $deleteResponse = $this->actingAs($guruLain)->delete(route('teaching-modules.destroy', $module));
        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('teaching_modules', ['id' => $module->id]);
    }

    public function test_teacher_can_submit_draft_for_review(): void
    {
        [, $guru, , $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('draft.pdf', 100, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Draf Awal',
            'file_path' => $filePath,
            'file_name' => 'draft.pdf',
            'file_size' => 100 * 1024,
            'status' => TeachingModule::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($guru)->patch(route('teaching-modules.submit', $module));
        $response->assertRedirect(route('teaching-modules.index'));

        $module->refresh();
        $this->assertEquals(TeachingModule::STATUS_SUBMITTED, $module->status);
    }

    public function test_admin_can_approve_teaching_module(): void
    {
        [$admin, $guru, , $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('siap-telaah.pdf', 100, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Menunggu Verifikasi',
            'file_path' => $filePath,
            'file_name' => 'siap-telaah.pdf',
            'file_size' => 100 * 1024,
            'status' => TeachingModule::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.teaching-modules.approve', $module), [
            'feedback_notes' => 'Modul ajar lengkap dan sesuai alur tujuan pembelajaran.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $module->refresh();
        $this->assertEquals(TeachingModule::STATUS_APPROVED, $module->status);
        $this->assertEquals($admin->id, $module->approved_by);
        $this->assertNotNull($module->approved_at);
        $this->assertEquals('Modul ajar lengkap dan sesuai alur tujuan pembelajaran.', $module->feedback_notes);
    }

    public function test_admin_can_reject_teaching_module_with_mandatory_feedback(): void
    {
        [$admin, $guru, , $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('revisi-perlu.pdf', 100, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Perlu Dicek',
            'file_path' => $filePath,
            'file_name' => 'revisi-perlu.pdf',
            'file_size' => 100 * 1024,
            'status' => TeachingModule::STATUS_SUBMITTED,
        ]);

        // Feedback kosong harus gagal validasi
        $emptyResponse = $this->actingAs($admin)->patch(route('admin.teaching-modules.reject', $module), [
            'feedback_notes' => '',
        ]);
        $emptyResponse->assertSessionHasErrors(['feedback_notes']);

        // Feedback terisi sukses mengubah status menjadi revision
        $response = $this->actingAs($admin)->patch(route('admin.teaching-modules.reject', $module), [
            'feedback_notes' => 'Tolong perbaiki rubrik penilaian sumatif di bagian akhir dokumen.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');

        $module->refresh();
        $this->assertEquals(TeachingModule::STATUS_REVISION, $module->status);
        $this->assertEquals('Tolong perbaiki rubrik penilaian sumatif di bagian akhir dokumen.', $module->feedback_notes);
        $this->assertNull($module->approved_at);
    }

    public function test_non_admin_cannot_approve_or_reject_module(): void
    {
        [, $guru, $guruLain, $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('siap.pdf', 100, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $module = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Guru',
            'file_path' => $filePath,
            'file_name' => 'siap.pdf',
            'file_size' => 100 * 1024,
            'status' => TeachingModule::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($guruLain)->patch(route('admin.teaching-modules.approve', $module));
        $this->assertTrue(in_array($response->getStatusCode(), [302, 403]));

        $module->refresh();
        $this->assertEquals(TeachingModule::STATUS_SUBMITTED, $module->status);
    }

    public function test_admin_who_teaches_can_manage_own_modules_and_supervise_others(): void
    {
        [$admin, $guru, , $year, $subject] = $this->setupEnvironment();

        // 1. Admin membuat modul ajarnya sendiri
        $fakePdf = UploadedFile::fake()->create('modul-irfan-english.pdf', 300, 'application/pdf');
        $createResponse = $this->actingAs($admin)->post(route('teaching-modules.store'), [
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'semester' => 'Ganjil',
            'title' => 'Modul Bahasa Inggris Kelas VIII - Muhammad Irfan',
            'file' => $fakePdf,
        ]);
        $createResponse->assertRedirect(route('teaching-modules.index'));

        $this->assertDatabaseHas('teaching_modules', [
            'teacher_id' => $admin->id,
            'title' => 'Modul Bahasa Inggris Kelas VIII - Muhammad Irfan',
        ]);

        // 2. Admin membuka halaman tab Modul Saya
        $myViewResponse = $this->actingAs($admin)->get(route('teaching-modules.index', ['view' => 'my']));
        $myViewResponse->assertOk();
        $myViewResponse->assertSee('Modul Bahasa Inggris Kelas VIII - Muhammad Irfan');

        // 3. Admin membuka halaman tab Supervisi
        $supervisionResponse = $this->actingAs($admin)->get(route('teaching-modules.index', ['view' => 'supervision']));
        $supervisionResponse->assertOk();
        $supervisionResponse->assertSee('Supervisi &amp; Verifikasi Seluruh Guru', false);

        // 4. Admin dapat menghapus modul miliknya
        $adminMod = TeachingModule::query()->where('teacher_id', $admin->id)->first();
        $deleteResponse = $this->actingAs($admin)->delete(route('teaching-modules.destroy', $adminMod));
        $deleteResponse->assertRedirect(route('teaching-modules.index'));
        $this->assertDatabaseMissing('teaching_modules', ['id' => $adminMod->id]);
    }

    public function test_teacher_only_sees_assigned_subjects_and_classes_in_module_view(): void
    {
        [, $guru, , $year, $subject, $schoolClass] = $this->setupEnvironment();

        // Buat mapel dan kelas lain yang TIDAK diampu oleh guru
        $mapelLain = Subject::query()->create([
            'code' => 'MTK-01',
            'name' => 'Matematika',
            'order_number' => 2,
            'is_active' => true,
        ]);

        $kelasLain = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VIII-B',
            'grade_level' => 'VIII',
            'homeroom_teacher_name' => 'Wali Kelas VIII-B',
        ]);

        $response = $this->actingAs($guru)->get(route('teaching-modules.index'));
        $response->assertOk();

        // Variabel view harus hanya memuat mapel & rombel yang diampu guru
        $teacherSubjects = $response->viewData('teacherSubjects');
        $teacherSchoolClasses = $response->viewData('teacherSchoolClasses');

        $this->assertTrue($teacherSubjects->pluck('id')->contains($subject->id));
        $this->assertFalse($teacherSubjects->pluck('id')->contains($mapelLain->id));

        $this->assertTrue($teacherSchoolClasses->pluck('id')->contains($schoolClass->id));
        $this->assertFalse($teacherSchoolClasses->pluck('id')->contains($kelasLain->id));
    }

    public function test_teacher_cannot_upload_module_for_unassigned_subject_or_class(): void
    {
        [, $guru, , $year, $subject, $schoolClass] = $this->setupEnvironment();

        $mapelLain = Subject::query()->create([
            'code' => 'IPA-01',
            'name' => 'Ilmu Pengetahuan Alam',
            'order_number' => 3,
            'is_active' => true,
        ]);

        $kelasLain = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'IX-C',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali Kelas IX-C',
        ]);

        $fakePdf = UploadedFile::fake()->create('modul-ilegal.pdf', 500, 'application/pdf');

        // Coba upload dengan mapel yang bukan diampunya
        $invalidSubjectResponse = $this->actingAs($guru)->post(route('teaching-modules.store'), [
            'subject_id' => $mapelLain->id,
            'grade_level' => 'VII',
            'title' => 'Modul IPA Ilegal',
            'file' => $fakePdf,
        ]);
        $invalidSubjectResponse->assertSessionHasErrors(['subject_id']);

        // Coba upload dengan rombel yang bukan diampunya
        $invalidClassResponse = $this->actingAs($guru)->post(route('teaching-modules.store'), [
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'school_class_id' => $kelasLain->id,
            'title' => 'Modul Kelas Ilegal',
            'file' => $fakePdf,
        ]);
        $invalidClassResponse->assertSessionHasErrors(['school_class_id']);
    }

    public function test_admin_who_teaches_is_restricted_to_assigned_kbm(): void
    {
        [$admin, , , $year, $subject] = $this->setupEnvironment();

        $mapelLain = Subject::query()->create([
            'code' => 'SBK-01',
            'name' => 'Seni Budaya',
            'order_number' => 4,
            'is_active' => true,
        ]);

        $fakePdf = UploadedFile::fake()->create('modul-admin-sbk.pdf', 300, 'application/pdf');

        // Admin (Muhammad Irfan) hanya memiliki mapel Bahasa Inggris. Jika upload Seni Budaya, harus ditolak.
        $response = $this->actingAs($admin)->post(route('teaching-modules.store'), [
            'subject_id' => $mapelLain->id,
            'grade_level' => 'VII',
            'title' => 'Modul Seni Budaya oleh Irfan',
            'file' => $fakePdf,
        ]);
        $response->assertSessionHasErrors(['subject_id']);
    }

    public function test_admin_cannot_edit_or_delete_other_teachers_module(): void
    {
        [$admin, $guru, , $year, $subject] = $this->setupEnvironment();

        $fakePdf = UploadedFile::fake()->create('modul-guru.pdf', 300, 'application/pdf');
        $filePath = $fakePdf->store("modul-ajar/{$year->id}", 'public');

        $guruModule = TeachingModule::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'semester' => 'Ganjil',
            'title' => 'Modul Asli Milik Guru',
            'file_path' => $filePath,
            'file_name' => 'modul-guru.pdf',
            'file_size' => 300 * 1024,
            'status' => TeachingModule::STATUS_SUBMITTED,
        ]);

        // 1. Admin mencoba mengedit modul milik guru lain -> Harus 403 Forbidden
        $editResponse = $this->actingAs($admin)->put(route('teaching-modules.update', $guruModule), [
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'title' => 'Modul Diedit Admin Tanpa Izin',
        ]);
        $editResponse->assertForbidden();

        // 2. Admin mencoba menghapus modul milik guru lain -> Harus 403 Forbidden
        $deleteResponse = $this->actingAs($admin)->delete(route('teaching-modules.destroy', $guruModule));
        $deleteResponse->assertForbidden();

        // 3. Tombol Edit & Hapus tidak boleh muncul pada tampilan supervisi admin untuk modul guru lain
        $supervisionResponse = $this->actingAs($admin)->get(route('teaching-modules.index', ['view' => 'supervision']));
        $supervisionResponse->assertOk();
        $this->assertFalse($guruModule->canBeEditedBy($admin));
        $this->assertFalse($guruModule->canBeDeletedBy($admin));

        // 4. Namun Admin tetap bisa melihat/pratinjau PDF, mengunduh, dan melakukan verifikasi
        $viewResponse = $this->actingAs($admin)->get(route('teaching-modules.view-pdf', $guruModule));
        $viewResponse->assertOk();

        $downloadResponse = $this->actingAs($admin)->get(route('teaching-modules.download', $guruModule));
        $downloadResponse->assertOk();
    }
}
