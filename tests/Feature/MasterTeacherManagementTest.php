<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterTeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createEnvironment(): array
    {
        $admin = User::query()->create([
            'name' => 'Administrator Kurikulum',
            'email' => 'admin@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'nip' => '197501012000031001',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $teacher = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'nip' => '198002022005011002',
            'phone' => '082198765432',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'start_date' => '2026-07-13',
            'end_date' => '2026-12-19',
        ]);

        $subjectMtk = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $subjectIpa = Subject::query()->create([
            'code' => 'IPA',
            'name' => 'Ilmu Pengetahuan Alam',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        return [$admin, $teacher, $year, $subjectMtk, $subjectIpa];
    }

    public function test_non_admin_cannot_access_master_teacher_page(): void
    {
        [, $teacher] = $this->createEnvironment();

        $response = $this->actingAs($teacher)->get(route('admin.teachers.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_access_master_teacher_page_and_see_required_columns(): void
    {
        [$admin, $teacher, , $subjectMtk, $subjectIpa] = $this->createEnvironment();

        // Hubungkan guru dengan mapel MTK (kode mengajar 7) dan IPA (kode mengajar 12)
        TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subjectMtk->id,
            'teaching_code' => '7',
        ]);

        TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subjectIpa->id,
            'teaching_code' => '12',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.teachers.index'));
        $response->assertOk();

        // Cek judul halaman & kolom tabel master guru
        $response->assertSee('Data Master Guru &amp; Tenaga Pendidik', false);
        $response->assertSee('Nama Lengkap');
        $response->assertSee('NIP');
        $response->assertSee('Nomor Telepon');
        $response->assertSee('Kode Mapel');
        $response->assertSee('Kode Mengajar');
        $response->assertSee('TTD Digital');

        // Cek bahwa data guru muncul dengan badge kode mapel dan kode mengajar
        $response->assertSee('Budi Santoso, S.Pd.');
        $response->assertSee('198002022005011002');
        $response->assertSee('082198765432');
        $response->assertSee('MTK');
        $response->assertSee('IPA');
        $response->assertSee('7');
        $response->assertSee('12');
    }

    public function test_admin_can_store_teacher_with_multiple_subjects_and_teaching_codes(): void
    {
        [$admin, , , $subjectMtk, $subjectIpa] = $this->createEnvironment();

        $dummySig = 'data:image/png;base64,' . base64_encode('fake-png-signature-data');

        $response = $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'name' => 'Siti Nurhaliza, M.Pd.',
            'email' => 'siti@smpn2lemahabang.sch.id',
            'nip' => '198505052010012003',
            'phone' => '085211223344',
            'role' => 'guru',
            'password' => 'guru123',
            'signature_data' => $dummySig,
            'teacher_subjects' => [
                [
                    'subject_id' => $subjectMtk->id,
                    'teaching_code' => '14A',
                ],
                [
                    'subject_id' => $subjectIpa->id,
                    'teaching_code' => '14B',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $created = User::query()->where('email', 'siti@smpn2lemahabang.sch.id')->first();
        $this->assertNotNull($created);
        $this->assertSame('Siti Nurhaliza, M.Pd.', $created->name);
        $this->assertSame('198505052010012003', $created->nip);
        $this->assertSame('085211223344', $created->phone);
        $this->assertNotEmpty($created->signature_data);

        $this->assertCount(2, $created->teacherSubjects);
        $this->assertSame(['MTK', 'IPA'], $created->subject_codes_list);
        $this->assertSame('MTK, IPA', $created->subject_codes_string);
        $this->assertSame(['14A', '14B'], $created->teaching_codes_list);
        $this->assertSame('14A, 14B', $created->teaching_codes_string);
    }

    public function test_admin_can_update_teacher_details_subjects_and_teaching_codes(): void
    {
        [$admin, $teacher, , $subjectMtk, $subjectIpa] = $this->createEnvironment();

        // Awalnya punya MTK
        TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subjectMtk->id,
            'teaching_code' => '7',
        ]);

        // Perbarui menjadi hanya IPA dengan kode 15
        $response = $this->actingAs($admin)->put(route('admin.teachers.update', $teacher), [
            'name' => 'Budi Santoso, M.Pd.',
            'email' => 'budi.santoso@smpn2lemahabang.sch.id',
            'nip' => '198002022005011002',
            'phone' => '08999888777',
            'role' => 'guru',
            'teacher_subjects' => [
                [
                    'subject_id' => $subjectIpa->id,
                    'teaching_code' => '15',
                ],
            ],
        ]);

        $response->assertRedirect();
        $teacher->refresh();

        $this->assertSame('Budi Santoso, M.Pd.', $teacher->name);
        $this->assertSame('budi.santoso@smpn2lemahabang.sch.id', $teacher->email);
        $this->assertSame('08999888777', $teacher->phone);
        $this->assertCount(1, $teacher->teacherSubjects);
        $this->assertSame(['IPA'], $teacher->subject_codes_list);
        $this->assertSame('IPA', $teacher->subject_codes_string);
        $this->assertSame(['15'], $teacher->teaching_codes_list);
        $this->assertSame('15', $teacher->teaching_codes_string);
    }

    public function test_admin_can_update_and_remove_signature_shortcut(): void
    {
        [$admin, $teacher] = $this->createEnvironment();

        $dummySig = 'data:image/png;base64,' . base64_encode('new-signature-data');

        // Update signature
        $updateResp = $this->actingAs($admin)->patch(route('admin.teachers.signature.update', $teacher), [
            'signature_data' => $dummySig,
        ]);
        $updateResp->assertRedirect();
        $teacher->refresh();
        $this->assertNotEmpty($teacher->signature_data);

        // Remove signature
        $removeResp = $this->actingAs($admin)->patch(route('admin.teachers.signature.update', $teacher), [
            'remove_signature' => 1,
        ]);
        $removeResp->assertRedirect();
        $teacher->refresh();
        $this->assertNull($teacher->signature_data);
    }

    public function test_admin_can_sort_teachers_by_name(): void
    {
        [$admin, $budi] = $this->createEnvironment();

        $andi = User::query()->create([
            'name' => 'Andi Wijaya, S.Pd.',
            'email' => 'andi@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        $zaki = User::query()->create([
            'name' => 'Zaki Mubarok, S.Pd.',
            'email' => 'zaki@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        // Sort Name ASC: Andi must appear before Zaki
        $responseAsc = $this->actingAs($admin)->get(route('admin.teachers.index', ['sort' => 'name', 'dir' => 'asc']));
        $responseAsc->assertOk();
        $usersAsc = $responseAsc->viewData('users');
        $namesAsc = $usersAsc->pluck('name')->all();
        $this->assertTrue(array_search('Andi Wijaya, S.Pd.', $namesAsc) < array_search('Zaki Mubarok, S.Pd.', $namesAsc));

        // Sort Name DESC: Zaki must appear before Andi
        $responseDesc = $this->actingAs($admin)->get(route('admin.teachers.index', ['sort' => 'name', 'dir' => 'desc']));
        $responseDesc->assertOk();
        $usersDesc = $responseDesc->viewData('users');
        $namesDesc = $usersDesc->pluck('name')->all();
        $this->assertTrue(array_search('Zaki Mubarok, S.Pd.', $namesDesc) < array_search('Andi Wijaya, S.Pd.', $namesDesc));
    }

    public function test_admin_can_sort_teachers_by_teaching_code(): void
    {
        [$admin, $teacher, , $subjectMtk, $subjectIpa] = $this->createEnvironment();

        TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subjectMtk->id,
            'teaching_code' => '25',
        ]);

        $guruDua = User::query()->create([
            'name' => 'Guru Kode Kecil',
            'email' => 'kecil@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        TeacherSubject::query()->create([
            'user_id' => $guruDua->id,
            'subject_id' => $subjectIpa->id,
            'teaching_code' => '3',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.teachers.index', ['sort' => 'teaching_code', 'dir' => 'asc']));
        $response->assertOk();
        $users = $response->viewData('users');
        $names = $users->pluck('name')->all();

        // Kode '3' harus mendahului kode '25'
        $this->assertTrue(array_search('Guru Kode Kecil', $names) < array_search('Budi Santoso, S.Pd.', $names));
    }

    public function test_admin_cannot_assign_duplicate_teaching_code_across_teachers(): void
    {
        [$admin, $teacher, , $subjectMtk, $subjectIpa] = $this->createEnvironment();

        TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subjectMtk->id,
            'teaching_code' => '14A',
        ]);

        // Tambah guru baru dengan kode sama ('14 a' yang ternormalisasi ke '14A') -> harus ditolak
        $storeConflict = $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'name' => 'Guru Duplikat Kode',
            'email' => 'duplikat@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => 'guru123',
            'teacher_subjects' => [
                ['subject_id' => $subjectIpa->id, 'teaching_code' => '14 a'],
            ],
        ]);
        $storeConflict->assertSessionHasErrors('teacher_subjects');
        $this->assertDatabaseMissing('users', ['email' => 'duplikat@smpn2lemahabang.sch.id']);

        // Update guru lain ke kode '14A' -> harus ditolak juga
        $guruLain = User::query()->create([
            'name' => 'Guru Lain, S.Pd.',
            'email' => 'lain@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        $updateConflict = $this->actingAs($admin)->put(route('admin.teachers.update', $guruLain), [
            'name' => $guruLain->name,
            'email' => $guruLain->email,
            'role' => 'guru',
            'teacher_subjects' => [
                ['subject_id' => $subjectIpa->id, 'teaching_code' => '14A'],
            ],
        ]);
        $updateConflict->assertSessionHasErrors('teacher_subjects');
    }

    public function test_admin_can_manage_homeroom_and_multi_piket_from_master_teacher_with_conflict_prevention_and_sync(): void
    {
        [$admin, $teacher, $year, $subjectMtk, $subjectIpa] = $this->createEnvironment();

        $sc7A = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => '-',
        ]);

        $otherGuru = User::query()->create([
            'name' => 'Siti Aminah, S.Pd.',
            'email' => 'siti.wali@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'nip' => '199101012019032001',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        $tc = TeachingClass::query()->create([
            'teacher_id' => $otherGuru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $sc7A->id,
            'subject_id' => $subjectIpa->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => '-',
        ]);

        // 1. Admin menetapkan Wali Kelas VII-A dan multi-hari Piket untuk Budi langsung dari Master Guru
        $resp = $this->actingAs($admin)->put(route('admin.teachers.update', $teacher), [
            'name' => 'Budi Santoso, M.Pd.',
            'email' => $teacher->email,
            'nip' => '198002022005011999',
            'role' => 'guru',
            'homeroom_school_class_id' => $sc7A->id,
            'has_piket_input' => '1',
            'piket_days' => ['SENIN', 'KAMIS'],
            'teacher_subjects' => [
                ['subject_id' => $subjectMtk->id, 'teaching_code' => '7'],
            ],
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $sc7A->refresh();
        $tc->refresh();
        $teacher->refresh();

        $this->assertSame($teacher->id, $sc7A->homeroom_teacher_id);
        $this->assertSame('Budi Santoso, M.Pd.', $sc7A->homeroom_teacher_name);
        $this->assertSame('198002022005011999', $sc7A->homeroom_teacher_nip);
        $this->assertSame('Budi Santoso, M.Pd.', $tc->homeroom_teacher_name);
        $this->assertSame('198002022005011999', $tc->homeroom_teacher_nip);
        $this->assertSame(['SENIN', 'KAMIS'], $teacher->piket_days_list);

        // 2. Guru lain tidak bisa mengambil alih VII-A selagi masih diampu Budi (anti-bentrok)
        $conflictWk = $this->actingAs($admin)->put(route('admin.teachers.update', $otherGuru), [
            'name' => $otherGuru->name,
            'email' => $otherGuru->email,
            'role' => 'guru',
            'homeroom_school_class_id' => $sc7A->id,
        ]);
        $conflictWk->assertSessionHasErrors('homeroom_school_class_id');

        // 3. Guru lain BOLEH piket di hari SENIN juga (>= 1 guru piket per hari)
        $okPiket = $this->actingAs($admin)->put(route('admin.teachers.update', $otherGuru), [
            'name' => $otherGuru->name,
            'email' => $otherGuru->email,
            'role' => 'guru',
            'has_piket_input' => '1',
            'piket_days' => ['SENIN'],
        ]);
        $okPiket->assertRedirect();

        // 4. Verifikasi Tab Guru Piket & Wali Kelas di Jadwal Utama membaca dinamis tanpa hardcode
        $jadwalUtama = $this->actingAs($otherGuru)->get(route('schedules.main.index', ['tab' => 'piket']));
        $jadwalUtama->assertOk();
        $jadwalUtama->assertSee('Budi Santoso, M.Pd.');
        $jadwalUtama->assertSee('Siti Aminah, S.Pd.');
        $jadwalUtama->assertSee('Kelas 7A');
    }
}
