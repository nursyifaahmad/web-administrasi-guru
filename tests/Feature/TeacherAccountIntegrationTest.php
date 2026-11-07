<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAccountIntegrationTest extends TestCase
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
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'start_date' => '2026-07-13',
            'end_date' => '2026-12-19',
        ]);

        $subPai = Subject::query()->create([
            'code' => 'PAI',
            'name' => 'Pendidikan Agama Islam',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $subDaerah = Subject::query()->create([
            'code' => 'SND',
            'name' => 'Bahasa Daerah',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $class7A = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-A, S.Pd.',
        ]);

        $class8B = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VIII-B',
            'grade_level' => 'VIII',
            'homeroom_teacher_name' => 'Wali Kelas VIII-B, S.Pd.',
        ]);

        return [$admin, $year, $subPai, $subDaerah, $class7A, $class8B];
    }

    public function test_admin_teachers_index_displays_master_teachers_with_subjects(): void
    {
        [$admin, $year, $subPai, $subDaerah] = $this->createEnvironment();

        $teacher = User::query()->create([
            'name' => 'ISMAIL, S.Pd. I',
            'email' => 'ismail@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'nip' => '198712032014021002',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        \App\Models\TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subPai->id,
            'teaching_code' => '14A',
        ]);

        \App\Models\TeacherSubject::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subDaerah->id,
            'teaching_code' => '14B',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.teachers.index'));
        $response->assertOk();

        // Pastikan halaman memuat data master guru dan mapel/kode mengajar
        $response->assertSee('ISMAIL, S.Pd. I');
        $response->assertSee('PAI');
        $response->assertSee('SND');
        $response->assertSee('14A');
        $response->assertSee('14B');
    }

    public function test_admin_creates_multi_subject_teacher_and_auto_syncs_teaching_classes(): void
    {
        [$admin, $year, $subPai, $subDaerah, $class7A, $class8B] = $this->createEnvironment();

        // 1. Siapkan master guru jadwal awal (sebelumnya ada atau belum)
        SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $year->id,
            'code' => '14A',
            'name' => 'ISMAIL, S.Pd. I',
            'subject' => 'Pendidikan Agama Islam',
            'homeroom_class' => '7G',
            'piket_day' => 'Selasa',
        ]);

        SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $year->id,
            'code' => '14B',
            'name' => 'ISMAIL, S.Pd. I',
            'subject' => 'Bahasa Daerah',
        ]);

        // 2. Siapkan slot jadwal KBM di Master Jadwal:
        // 14A mengajar di VII-A (PAI)
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.40 - 08.10',
            'class_name' => '7A',
            'school_class_id' => $class7A->id,
            'teacher_code' => '14A',
            'teacher_name' => 'ISMAIL, S.Pd. I',
            'subject_name' => 'Pendidikan Agama Islam',
            'subject_id' => $subPai->id,
        ]);

        // 14B mengajar di VIII-B (Bahasa Daerah)
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 2,
            'shift' => 'siang',
            'period_order' => 3,
            'time_range' => '13.00 - 13.30',
            'class_name' => '8B',
            'school_class_id' => $class8B->id,
            'teacher_code' => '14B',
            'teacher_name' => 'ISMAIL, S.Pd. I',
            'subject_name' => 'Bahasa Daerah',
            'subject_id' => $subDaerah->id,
        ]);

        // 3. Admin membuat akun guru Pak Ismail melalui form Master Guru dengan teacher_subjects
        $response = $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'name' => 'ISMAIL, S.Pd. I',
            'email' => 'ismail@smpn2lemahabang.sch.id',
            'nip' => '198712032014021002',
            'role' => 'guru',
            'phone' => '081234567890',
            'password' => 'guru123',
            'teacher_subjects' => [
                ['subject_id' => $subPai->id, 'teaching_code' => '14A'],
                ['subject_id' => $subDaerah->id, 'teaching_code' => '14B'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 4. Verifikasi akun User terbuat
        $ismailUser = User::query()->where('email', 'ismail@smpn2lemahabang.sch.id')->firstOrFail();
        $this->assertSame('ISMAIL, S.Pd. I', $ismailUser->name);
        $this->assertSame('guru', $ismailUser->role);

        // 5. Verifikasi SchoolScheduleTeacher otomatis terhubung ke akun Pak Ismail
        $this->assertDatabaseHas('school_schedule_teachers', [
            'academic_year_id' => $year->id,
            'code' => '14A',
            'user_id' => $ismailUser->id,
        ]);
        $this->assertDatabaseHas('school_schedule_teachers', [
            'academic_year_id' => $year->id,
            'code' => '14B',
            'user_id' => $ismailUser->id,
        ]);

        // 6. Verifikasi SchoolScheduleItem otomatis terhubung ke user_id
        $this->assertDatabaseHas('school_schedule_items', [
            'teacher_code' => '14A',
            'user_id' => $ismailUser->id,
        ]);
        $this->assertDatabaseHas('school_schedule_items', [
            'teacher_code' => '14B',
            'user_id' => $ismailUser->id,
        ]);

        // 7. Verifikasi Buku Kelas Administrasi (TeachingClass) otomatis terbentuk:
        // Pak Ismail harus memiliki DUA buku kelas terpisah: VII-A (PAI) dan VIII-B (Bahasa Daerah)
        $classes = TeachingClass::query()->where('teacher_id', $ismailUser->id)->get();
        $this->assertCount(2, $classes);

        $paiClass = $classes->firstWhere('subject_id', $subPai->id);
        $this->assertNotNull($paiClass);
        $this->assertSame('VII-A', $paiClass->class_name);
        $this->assertTrue($paiClass->only_teaching_days);

        $daerahClass = $classes->firstWhere('subject_id', $subDaerah->id);
        $this->assertNotNull($daerahClass);
        $this->assertSame('VIII-B', $daerahClass->class_name);
        $this->assertTrue($daerahClass->only_teaching_days);
    }

    public function test_admin_creates_manual_teacher_without_schedule(): void
    {
        [$admin] = $this->createEnvironment();

        $response = $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'name' => 'Guru Pengganti, S.Pd.',
            'email' => 'pengganti@smpn2lemahabang.sch.id',
            'nip' => '199501012022011005',
            'role' => 'guru',
            'phone' => '0899999999',
            'default_subject' => 'Matematika',
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'pengganti@smpn2lemahabang.sch.id',
            'name' => 'Guru Pengganti, S.Pd.',
            'role' => 'guru',
        ]);
    }
}
