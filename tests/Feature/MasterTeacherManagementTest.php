<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\TeacherSubject;
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
            'password' => 'password123',
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
}
