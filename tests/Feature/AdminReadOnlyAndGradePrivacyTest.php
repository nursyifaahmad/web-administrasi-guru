<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\GradeColumn;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminReadOnlyAndGradePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guruPengampu;
    private User $guruLain;
    private TeachingClass $teachingClass;
    private ClassStudent $student;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::current()->update([
            'school_name' => 'SMP NEGERI 2 LEMAHABANG',
            'default_city' => 'Cirebon',
            'headmaster_name' => 'Drs. H. Suwandi, M.Pd.',
            'headmaster_nip' => '19680512 199412 1 002',
        ]);

        $this->admin = User::query()->create([
            'name' => 'Wakasek Kurikulum / Admin',
            'email' => 'admin@smpn2lemahabang.sch.id',
            'nip' => '19800101 200501 1 005',
            'role' => 'admin',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->guruPengampu = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi@smpn2lemahabang.sch.id',
            'nip' => '19850215 201001 1 018',
            'role' => 'guru',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->guruLain = User::query()->create([
            'name' => 'Siti Aminah, S.Pd.',
            'email' => 'siti@smpn2lemahabang.sch.id',
            'nip' => '19890420 201402 2 003',
            'role' => 'guru',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'name' => 'Matematika',
            'code' => 'MTK',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => '7',
            'name' => '7A',
            'homeroom_teacher_name' => 'Dra. Hj. Nurjanah',
            'homeroom_teacher_nip' => '19750312 200003 2 001',
            'is_active' => true,
        ]);

        $this->teachingClass = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'school_class_id' => $schoolClass->id,
            'teacher_id' => $this->guruPengampu->id,
            'subject_id' => $subject->id,
            'class_name' => '7A',
            'grade_level' => '7',
            'homeroom_teacher_name' => 'Dra. Hj. Nurjanah',
            'homeroom_teacher_nip' => '19750312 200003 2 001',
            'is_grade_shared_with_admin' => false,
        ]);

        $this->student = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'full_name' => 'Ahmad Fathoni',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $this->teachingClass->ensureDefaultsInitialized();
    }

    public function test_admin_can_view_attendance_in_monitoring_mode_but_cannot_bulk_save_or_import_and_gets_403(): void
    {
        // 1. Admin melihat menu Absensi -> 200 OK dengan banner supervisi
        $response = $this->actingAs($this->admin)->get(route('attendances.index', $this->teachingClass));
        $response->assertOk();
        $response->assertSee('Mode Monitoring Supervisi (Hanya Lihat)');
        $response->assertDontSee('Simpan Absensi (Ctrl+S)');
        $response->assertDontSee('Import Excel');

        // 2. Admin mencoba POST bulk-save absensi -> ditolak HTTP 403 Forbidden
        $postSave = $this->actingAs($this->admin)->postJson(route('attendances.bulk-save', $this->teachingClass), [
            'year' => 2026,
            'month' => 8,
            'cells' => [
                (string) $this->student->id => [
                    1 => '.',
                    2 => '.',
                ],
            ],
        ]);
        $postSave->assertForbidden();
        $this->assertStringContainsString('Akses ditolak', $postSave->json('message'));
    }

    public function test_admin_can_view_journal_in_monitoring_mode_but_cannot_store_update_sync_or_destroy_and_gets_403(): void
    {
        // Buat satu jurnal contoh oleh guru
        $journal = DailyJournal::query()->create([
            'teaching_class_id' => $this->teachingClass->id,
            'journal_date' => '2026-08-10',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Operasi Bilangan Bulat',
            'present_count' => 1,
            'absent_count' => 0,
        ]);

        // 1. Admin melihat jurnal -> 200 OK dengan banner supervisi
        $response = $this->actingAs($this->admin)->get(route('journals.index', $this->teachingClass));
        $response->assertOk();
        $response->assertSee('Mode Monitoring Supervisi (Hanya Lihat)');
        $response->assertDontSee('+ Tambah Jurnal Harian');
        $response->assertDontSee('Sync Absen');

        // 2. Admin mencoba POST store jurnal -> ditolak 403 Forbidden
        $storeRes = $this->actingAs($this->admin)->post(route('journals.store', $this->teachingClass), [
            'journal_date' => '2026-08-11',
            'lesson_hours' => '3 - 4',
            'activity_material' => 'Materi Baru oleh Admin',
        ]);
        $storeRes->assertForbidden();

        // 3. Admin mencoba PUT update jurnal -> ditolak 403 Forbidden
        $updateRes = $this->actingAs($this->admin)->put(route('journals.update', [$this->teachingClass, $journal]), [
            'journal_date' => '2026-08-10',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Materi Diubah oleh Admin',
        ]);
        $updateRes->assertForbidden();

        // 4. Admin mencoba POST resync absensi jurnal -> ditolak 403 Forbidden
        $resyncRes = $this->actingAs($this->admin)->post(route('journals.resync', [$this->teachingClass, $journal]));
        $resyncRes->assertForbidden();

        // 5. Admin mencoba DELETE destroy jurnal -> ditolak 403 Forbidden
        $destroyRes = $this->actingAs($this->admin)->delete(route('journals.destroy', [$this->teachingClass, $journal]));
        $destroyRes->assertForbidden();
    }

    public function test_admin_cannot_view_grade_detail_by_default_and_gets_locked_card(): void
    {
        // Masukkan nilai siswa oleh guru
        StudentGrade::query()->create([
            'teaching_class_id' => $this->teachingClass->id,
            'class_student_id' => $this->student->id,
            'attendance_score' => 95.0,
            'sts_score' => 88.0,
            'sas_score' => 90.0,
            'final_score' => 89.5,
        ]);

        $this->assertFalse((bool) $this->teachingClass->is_grade_shared_with_admin);

        // 1. Admin membuka halaman nilai -> kartu gembok proteksi muncul, rincian murid dikosongkan
        $response = $this->actingAs($this->admin)->get(route('grades.index', $this->teachingClass));
        $response->assertOk();
        $response->assertSee('Buku Nilai Detail Terkunci');
        $response->assertSee('Memerlukan Izin Guru Pengampu');
        $response->assertViewHas('isGradeLockedForAdmin', true);
        $this->assertEmpty($response->viewData('alpineRows'));

        // 2. Admin mencoba export PDF nilai tanpa izin -> ditolak 403
        $pdfRes = $this->actingAs($this->admin)->get(route('exports.gradebook.pdf', $this->teachingClass));
        $pdfRes->assertForbidden();

        // 3. Admin mencoba export Excel nilai tanpa izin -> ditolak 403
        $excelRes = $this->actingAs($this->admin)->get(route('exports.gradebook.excel', $this->teachingClass));
        $excelRes->assertForbidden();

        // 4. Admin mencoba export Bundel tanpa izin nilai -> ditolak 403
        $bundlePdf = $this->actingAs($this->admin)->get(route('exports.bundle.pdf', $this->teachingClass));
        $bundlePdf->assertForbidden();

        $bundleExcel = $this->actingAs($this->admin)->get(route('exports.bundle.excel', $this->teachingClass));
        $bundleExcel->assertForbidden();

        // 5. Admin mencoba download template nilai saat terkunci -> ditolak 403
        $templateRes = $this->actingAs($this->admin)->get(route('grades.template', $this->teachingClass));
        $templateRes->assertForbidden();
    }

    public function test_owner_teacher_can_toggle_admin_access_and_other_users_cannot(): void
    {
        // 1. Guru Pengampu membuka izin untuk Admin
        $res1 = $this->actingAs($this->guruPengampu)->patch(route('grades.toggle-admin-access', $this->teachingClass));
        $res1->assertRedirect();
        $this->teachingClass->refresh();
        $this->assertTrue((bool) $this->teachingClass->is_grade_shared_with_admin);

        // 2. Guru Pengampu mengunci kembali izin untuk Admin
        $res2 = $this->actingAs($this->guruPengampu)->patch(route('grades.toggle-admin-access', $this->teachingClass));
        $res2->assertRedirect();
        $this->teachingClass->refresh();
        $this->assertFalse((bool) $this->teachingClass->is_grade_shared_with_admin);

        // 3. Guru lain yang bukan pengampu mencoba toggle -> ditolak 403 Forbidden
        $resGuruLain = $this->actingAs($this->guruLain)->patch(route('grades.toggle-admin-access', $this->teachingClass));
        $resGuruLain->assertForbidden();

        // 4. Admin mencoba mengubah izin dirinya sendiri -> ditolak 403 Forbidden
        $resAdmin = $this->actingAs($this->admin)->patch(route('grades.toggle-admin-access', $this->teachingClass));
        $resAdmin->assertForbidden();
    }

    public function test_admin_can_view_grade_detail_in_readonly_mode_when_permitted_by_teacher(): void
    {
        // Guru memberikan izin
        $this->teachingClass->update(['is_grade_shared_with_admin' => true]);

        StudentGrade::query()->create([
            'teaching_class_id' => $this->teachingClass->id,
            'class_student_id' => $this->student->id,
            'attendance_score' => 95.0,
            'sts_score' => 88.0,
            'sas_score' => 90.0,
            'final_score' => 89.5,
        ]);

        // 1. Admin membuka halaman nilai -> tabel tampil dengan banner supervisi hijau
        $response = $this->actingAs($this->admin)->get(route('grades.index', $this->teachingClass));
        $response->assertOk();
        $response->assertSee('Mode Supervisi Nilai (Hanya Lihat');
        $response->assertDontSee('Buku Nilai Detail Terkunci');
        $response->assertDontSee('Simpan Nilai');
        $response->assertDontSee('+ Tambah Kolom PH');
        $response->assertDontSee('Atur Persentase Rumus NA');
        $response->assertViewHas('isGradeLockedForAdmin', false);
        $response->assertViewHas('isAdminReadOnly', true);
        $this->assertNotEmpty($response->viewData('alpineRows'));

        // 2. Admin dapat mengunduh export PDF dan Excel nilai ketika diizinkan
        $pdfRes = $this->actingAs($this->admin)->get(route('exports.gradebook.pdf', $this->teachingClass));
        $pdfRes->assertOk();

        $excelRes = $this->actingAs($this->admin)->get(route('exports.gradebook.excel', $this->teachingClass));
        $excelRes->assertOk();
    }

    public function test_admin_still_cannot_modify_grades_even_when_permitted(): void
    {
        // Guru memberikan izin lihat
        $this->teachingClass->update(['is_grade_shared_with_admin' => true]);

        // 1. POST bulk save scores -> ditolak 403
        $bulkRes = $this->actingAs($this->admin)->postJson(route('grades.bulk-save', $this->teachingClass), [
            'rows' => [
                [
                    'student_id' => $this->student->id,
                    'dynamic_scores' => [],
                    'attendance_score' => 100,
                    'sts_score' => 100,
                    'sas_score' => 100,
                ],
            ],
        ]);
        $bulkRes->assertForbidden();

        // 2. POST tambah kolom nilai -> ditolak 403
        $addColRes = $this->actingAs($this->admin)->post(route('grades.columns.store', $this->teachingClass), [
            'type' => 'PH',
            'label' => 'PH 1',
        ]);
        $addColRes->assertForbidden();

        // Buat kolom oleh guru
        $col = GradeColumn::query()->create([
            'teaching_class_id' => $this->teachingClass->id,
            'type' => 'PH',
            'label' => 'PH 1',
            'sort_order' => 1,
        ]);

        // 3. PUT update kolom nilai -> ditolak 403
        $updColRes = $this->actingAs($this->admin)->put(route('grades.columns.update', [$this->teachingClass, $col]), [
            'label' => 'PH 1 Ganti',
        ]);
        $updColRes->assertForbidden();

        // 4. DELETE destroy kolom nilai -> ditolak 403
        $delColRes = $this->actingAs($this->admin)->delete(route('grades.columns.destroy', [$this->teachingClass, $col]));
        $delColRes->assertForbidden();

        // 5. PUT update weights -> ditolak 403
        $weightRes = $this->actingAs($this->admin)->put(route('grades.weights.update', $this->teachingClass), [
            'weight_ph' => 20,
            'weight_attendance' => 20,
            'weight_nt' => 20,
            'weight_sts' => 20,
            'weight_sas' => 20,
            'rounding_precision' => 2,
        ]);
        $weightRes->assertForbidden();

        // 6. POST import excel nilai -> ditolak 403
        $fakeFile = UploadedFile::fake()->create('nilai.xlsx', 50);
        $importRes = $this->actingAs($this->admin)->post(route('grades.import', $this->teachingClass), [
            'excel_file' => $fakeFile,
        ]);
        $importRes->assertForbidden();
    }
}
