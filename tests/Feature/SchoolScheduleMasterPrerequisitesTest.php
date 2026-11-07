<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleTeacher;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolScheduleMasterPrerequisitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setupEnvironment(): array
    {
        $admin = User::query()->create([
            'name' => 'Wakasek Kurikulum',
            'email' => 'wakasek@smpn2lemahabang.sch.id',
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

        return [$admin, $year, $subjectMtk, $subjectIpa];
    }

    public function test_prerequisites_check_detects_empty_classes_and_incomplete_teachers(): void
    {
        [$admin, $year] = $this->setupEnvironment();
        $service = app(SchoolScheduleService::class);

        // Buat guru tanpa mapel & kode
        User::query()->create([
            'name' => 'Guru Belum Lengkap',
            'email' => 'incomplete@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        $check = $service->checkMasterPrerequisites($year);

        $this->assertFalse($check['is_ready']);
        $this->assertFalse($check['teachers']['is_ready']);
        $this->assertFalse($check['classes']['is_ready']);
        $this->assertSame(0, $check['classes']['total']);
        $this->assertSame(1, $check['teachers']['incomplete_count']);
    }

    public function test_prerequisites_check_passes_when_both_teachers_and_classes_are_complete(): void
    {
        [$admin, $year, $subjectMtk] = $this->setupEnvironment();
        $service = app(SchoolScheduleService::class);

        // 1. Buat guru lengkap dengan mapel dan kode
        $guru = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        TeacherSubject::query()->create([
            'user_id' => $guru->id,
            'subject_id' => $subjectMtk->id,
            'teaching_code' => '7',
        ]);

        // 2. Buat kelas di Master Rombel
        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali VII-A',
        ]);

        $check = $service->checkMasterPrerequisites($year);

        $this->assertTrue($check['is_ready']);
        $this->assertTrue($check['teachers']['is_ready']);
        $this->assertTrue($check['classes']['is_ready']);
        $this->assertSame(1, $check['classes']['total']);
        $this->assertSame(0, $check['teachers']['incomplete_count']);
    }

    public function test_builder_page_locks_classes_strictly_from_school_classes_table(): void
    {
        [$admin, $year, $subjectMtk] = $this->setupEnvironment();

        // Daftarkan 2 kelas di Master Rombel
        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali VII-A',
        ]);

        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VIII-B',
            'grade_level' => 'VIII',
            'homeroom_teacher_name' => 'Wali VIII-B',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.builder'));
        $response->assertOk();

        // Pastikan view menerima lockedClasses yang bersumber dari SchoolClass
        $lockedClasses = $response->viewData('lockedClasses');
        $this->assertCount(2, $lockedClasses);
        $this->assertSame(['7A', '8B'], $lockedClasses->pluck('code')->all());
    }

    public function test_sync_schedule_teachers_from_master_populates_schedule_teachers(): void
    {
        [$admin, $year, $subjectMtk, $subjectIpa] = $this->setupEnvironment();
        $service = app(SchoolScheduleService::class);

        $guru = User::query()->create([
            'name' => 'Ahmad Guru Multi Mapel',
            'email' => 'ahmad@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        TeacherSubject::query()->create([
            'user_id' => $guru->id,
            'subject_id' => $subjectMtk->id,
            'teaching_code' => '14A',
        ]);

        TeacherSubject::query()->create([
            'user_id' => $guru->id,
            'subject_id' => $subjectIpa->id,
            'teaching_code' => '14B',
        ]);

        $synced = $service->syncScheduleTeachersFromMaster($year);
        $this->assertSame(2, $synced);

        $st14A = SchoolScheduleTeacher::query()->where('academic_year_id', $year->id)->where('code', '14A')->first();
        $this->assertNotNull($st14A);
        $this->assertSame('Ahmad Guru Multi Mapel', $st14A->name);
        $this->assertSame('Matematika', $st14A->subject);
        $this->assertSame($guru->id, $st14A->user_id);

        $st14B = SchoolScheduleTeacher::query()->where('academic_year_id', $year->id)->where('code', '14B')->first();
        $this->assertNotNull($st14B);
        $this->assertSame('Ilmu Pengetahuan Alam', $st14B->subject);
        $this->assertSame($guru->id, $st14B->user_id);
    }
}
