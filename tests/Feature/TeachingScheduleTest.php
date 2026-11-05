<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setupScheduleEnvironment(): array
    {
        $guru = User::query()->create([
            'name' => 'Guru Informatika & IPA',
            'email' => 'guru.jadwal@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'nip' => '198505052010011002',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'name' => 'Administrator Sekolah',
            'email' => 'admin.jadwal@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'nip' => '197501012000031001',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subInf = Subject::query()->create([
            'name' => 'Informatika',
            'code' => 'INF',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $subIpa = Subject::query()->create([
            'name' => 'Ilmu Pengetahuan Alam',
            'code' => 'IPA',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $sc7e = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-E',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-E',
        ]);

        $sc8a = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VIII-A',
            'grade_level' => 'VIII',
            'homeroom_teacher_name' => 'Wali Kelas VIII-A',
        ]);

        $sc9c = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'IX-C',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali Kelas IX-C',
        ]);

        // Kelas 1: Informatika VII-E (Senin & Kamis)
        $cls1 = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $sc7e->id,
            'subject_id' => $subInf->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-E',
            'schedule_info' => 'Senin jam 6-7, Kamis jam 2',
            'teaching_days' => [1, 4], // Senin & Kamis
            'only_teaching_days' => true,
            'homeroom_teacher_name' => 'Wali Kelas VII-E',
        ]);
        $cls1->ensureDefaultsInitialized();

        // Kelas 2: IPA VIII-A (Selasa & Jumat)
        $cls2 = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $sc8a->id,
            'subject_id' => $subIpa->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-A',
            'schedule_info' => 'Selasa jam 1-3, Jumat jam 1-2',
            'teaching_days' => [2, 5], // Selasa & Jumat
            'only_teaching_days' => true,
            'homeroom_teacher_name' => 'Wali Kelas VIII-A',
        ]);
        $cls2->ensureDefaultsInitialized();

        // Kelas 3: Informatika IX-C (Belum diatur hari mengajarnya)
        $cls3 = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $sc9c->id,
            'subject_id' => $subInf->id,
            'grade_level' => 'IX',
            'class_name' => 'IX-C',
            'schedule_info' => null,
            'teaching_days' => null, // Unscheduled
            'only_teaching_days' => true,
            'homeroom_teacher_name' => 'Wali Kelas IX-C',
        ]);
        $cls3->ensureDefaultsInitialized();

        // Tambahkan murid sampel
        ClassStudent::query()->create([
            'school_class_id' => $sc7e->id,
            'teaching_class_id' => $cls1->id,
            'order_number' => 1,
            'nis_nisn' => '7001',
            'full_name' => 'Ahmad Santoso',
            'gender' => 'L',
            'is_active' => true,
        ]);

        return compact('guru', 'admin', 'year', 'cls1', 'cls2', 'cls3');
    }

    public function test_teacher_can_view_weekly_teaching_schedule(): void
    {
        $env = $this->setupScheduleEnvironment();
        $guru = $env['guru'];

        $res = $this->actingAs($guru)->get(route('schedules.index'));

        $res->assertOk();
        $res->assertSee('Jadwal Mengajar Mingguan (Senin–Sabtu)');
        $res->assertSee($guru->name);
        $res->assertSee('SENIN');
        $res->assertSee('SELASA');
        $res->assertSee('RABU');
        $res->assertSee('KAMIS');
        $res->assertSee('JUMAT');
        $res->assertSee('SABTU');
        $res->assertSee('Kelas VII-E');
        $res->assertSee('Kelas VIII-A');
        $res->assertSee('Cetak Jadwal Mingguan');
    }

    public function test_classes_are_mapped_to_correct_days_and_unscheduled_detected(): void
    {
        $env = $this->setupScheduleEnvironment();
        $guru = $env['guru'];
        $cls1 = $env['cls1'];
        $cls2 = $env['cls2'];
        $cls3 = $env['cls3'];

        $res = $this->actingAs($guru)->get(route('schedules.index'));

        $res->assertOk();

        // Ambil data view
        $scheduledByDay = $res->viewData('scheduledByDay');
        $unscheduled = $res->viewData('unscheduledClasses');

        // Hari 1 (Senin): berisi VII-E (cls1)
        $this->assertTrue($scheduledByDay[1]->contains('id', $cls1->id));
        $this->assertFalse($scheduledByDay[1]->contains('id', $cls2->id));

        // Hari 2 (Selasa): berisi VIII-A (cls2)
        $this->assertTrue($scheduledByDay[2]->contains('id', $cls2->id));
        $this->assertFalse($scheduledByDay[2]->contains('id', $cls1->id));

        // Hari 3 (Rabu): kosong
        $this->assertTrue($scheduledByDay[3]->isEmpty());

        // Hari 4 (Kamis): berisi VII-E (cls1)
        $this->assertTrue($scheduledByDay[4]->contains('id', $cls1->id));

        // Hari 5 (Jumat): berisi VIII-A (cls2)
        $this->assertTrue($scheduledByDay[5]->contains('id', $cls2->id));

        // Hari 6 (Sabtu): kosong
        $this->assertTrue($scheduledByDay[6]->isEmpty());

        // Kelas yang belum diatur jadwalnya: berisi IX-C (cls3)
        $this->assertTrue($unscheduled->contains('id', $cls3->id));
    }

    public function test_admin_can_view_any_teacher_schedule(): void
    {
        $env = $this->setupScheduleEnvironment();
        $admin = $env['admin'];
        $guru = $env['guru'];

        $res = $this->actingAs($admin)->get(route('schedules.index', [
            'teacher_id' => $guru->id,
        ]));

        $res->assertOk();
        $res->assertSee($guru->name);
        $res->assertSee('Kelas VII-E');
    }

    public function test_sidebar_includes_jadwal_hari_ini_menu(): void
    {
        $env = $this->setupScheduleEnvironment();
        $guru = $env['guru'];

        $res = $this->actingAs($guru)->get(route('classes.index'));

        $res->assertOk();
        $res->assertSee(route('schedules.index'));
        $res->assertSee('Jadwal Hari Ini');
    }

    public function test_teaching_schedule_and_classes_list_eager_load_students_added_via_admin_rombel(): void
    {
        $env = $this->setupScheduleEnvironment();
        $guru = $env['guru'];
        $year = $env['year'];

        // Buat Rombel VII-D dari portal Admin
        $sc7d = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-D',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-D',
        ]);

        // Masukkan 38 murid ke Rombel VII-D dengan teaching_class_id = null (simulasi input via Admin)
        for ($i = 1; $i <= 38; $i++) {
            ClassStudent::query()->create([
                'school_class_id' => $sc7d->id,
                'teaching_class_id' => null,
                'order_number' => $i,
                'nis_nisn' => '7D' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'full_name' => "Murid VII-D {$i}",
                'gender' => $i % 2 === 0 ? 'P' : 'L',
                'is_active' => true,
            ]);
        }

        // Guru mengajar di kelas VII-D
        $cls7d = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $sc7d->id,
            'subject_id' => Subject::first()->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-D',
            'schedule_info' => 'Rabu jam 1-3',
            'teaching_days' => [3], // Rabu
            'only_teaching_days' => true,
            'homeroom_teacher_name' => 'Wali Kelas VII-D',
        ]);
        $cls7d->ensureDefaultsInitialized();

        // 1. Uji tampilan Jadwal Mengajar: Eager loading activeStudents harus menghasilkan 38, bukan 0
        $scheduleRes = $this->actingAs($guru)->get(route('schedules.index'));
        $scheduleRes->assertOk();
        $scheduleRes->assertSee('Kelas VII-D');
        $scheduleRes->assertSee('Murid: <span class="font-bold text-slate-700">38</span> orang', false);

        // 2. Uji tampilan Daftar Kelas Guru: withCount activeStudents harus bernilai 38
        $classesRes = $this->actingAs($guru)->get(route('classes.index'));
        $classesRes->assertOk();
        $classesRes->assertSee('VII-D');
        $classesData = $classesRes->viewData('classes');
        $matchedClass = $classesData->firstWhere('id', $cls7d->id);
        $this->assertNotNull($matchedClass);
        $this->assertEquals(38, $matchedClass->students_count);
    }
}
