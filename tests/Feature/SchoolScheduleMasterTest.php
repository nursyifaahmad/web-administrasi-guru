<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolScheduleMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function createEnvironment(): array
    {
        $admin = User::query()->create([
            'name' => 'Wakasek Kurikulum',
            'email' => 'wakasek@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'nip' => '197501012000031001',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Rudi Indrawan',
            'email' => 'rudi@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'nip' => '198001012005011003',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'start_date' => '2026-07-13',
            'end_date' => '2026-12-19',
        ]);

        return [$admin, $guru, $year];
    }

    public function test_admin_can_view_master_schedule_page(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.index'));
        $response->assertOk();
        $response->assertSee('Master Jadwal Pelajaran Sekolah');
        $response->assertSee('Buat Jadwal Baru dari Awal');
    }

    public function test_guru_cannot_access_admin_schedule_management(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $response = $this->actingAs($guru)->get(route('admin.schedules.master.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_initialize_schedule_from_scratch_via_wizard(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $payload = [
            'academic_year_id' => $year->id,
            'shift_mode' => 'double',
            'days_count' => 6,
            'source_mode' => 'empty',
        ];

        $response = $this->actingAs($admin)->post(route('admin.schedules.master.scratch'), $payload);
        $response->assertRedirect(route('admin.schedules.master.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('school_schedule_items', [
            'academic_year_id' => $year->id,
            'class_name' => '7A',
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
        ]);
        $this->assertDatabaseHas('school_schedule_items', [
            'academic_year_id' => $year->id,
            'class_name' => '8A',
            'day_of_week' => 1,
            'shift' => 'siang',
            'period_order' => 1,
        ]);
    }

    public function test_admin_can_load_wakasek_template_and_detect_conflicts(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $response = $this->actingAs($admin)->post(route('admin.schedules.master.load-wakasek'), [
            'academic_year_id' => $year->id,
        ]);

        $response->assertRedirect(route('admin.schedules.master.index'));
        $response->assertSessionHas('status');

        // Check that 21 classes and teachers exist
        $this->assertDatabaseCount('school_schedule_teachers', 36);
        $conflictCount = SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('is_conflict', true)->count();
        $this->assertGreaterThan(0, $conflictCount);
    }

    public function test_admin_can_update_cell_and_swap_cells(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Find an item
        $item1 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('day_of_week', 2)
            ->where('class_name', '7A')
            ->where('period_order', 1)
            ->firstOrFail();

        $item2 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('day_of_week', 2)
            ->where('class_name', '7A')
            ->where('period_order', 5)
            ->firstOrFail();

        $code1 = $item1->teacher_code;
        $code2 = $item2->teacher_code;

        // Test swap
        $response = $this->actingAs($admin)->post(route('admin.schedules.master.swap'), [
            'item_id_1' => $item1->id,
            'item_id_2' => $item2->id,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertEquals($code2, $item1->fresh()->teacher_code);
        $this->assertEquals($code1, $item2->fresh()->teacher_code);

        // Test direct cell update
        $responseUpdate = $this->actingAs($admin)->put(route('admin.schedules.master.update-cell', $item1), [
            'teacher_code' => '9', // Linlin Suherlina
        ]);
        $responseUpdate->assertRedirect();
        $this->assertEquals('9', $item1->fresh()->teacher_code);
    }

    public function test_admin_can_sync_schedule_to_teaching_classes(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $subject = Subject::query()->create([
            'name' => 'Penjasorkes',
            'code' => 'PJOK',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Linlin Suherlina, SE',
        ]);

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'class_name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Linlin Suherlina, SE',
            'is_active' => true,
        ]);

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Associate Rudi Indrawan (code 3) with guru user
        SchoolScheduleTeacher::query()
            ->where('academic_year_id', $year->id)
            ->where('code', '3')
            ->update(['user_id' => $guru->id]);

        $response = $this->actingAs($admin)->post(route('admin.schedules.master.sync-classes'), [
            'academic_year_id' => $year->id,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $teachingClass->refresh();
        $this->assertNotEmpty($teachingClass->schedule_info);
        $this->assertIsArray($teachingClass->teaching_days);
    }

    public function test_guru_can_access_jadwal_utama(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Associate teacher Rudi Indrawan (code 3) with guru
        SchoolScheduleTeacher::query()
            ->where('academic_year_id', $year->id)
            ->where('code', '3')
            ->update(['user_id' => $guru->id]);

        $response = $this->actingAs($guru)->get(route('schedules.main.index'));
        $response->assertOk();
        $response->assertSee('Jadwal Utama Sekolah');
        $response->assertSee('Jadwal Mengajar Saya');
        $response->assertSee('Matriks 21 Rombel');
        $response->assertSee('Guru Piket & Wali Kelas');
    }

    public function test_ahmad_nursyifa_auto_matched_to_code_32_and_views_schedule(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        // Buat akun Ahmad Nursyifa, S.Kom.
        $ahmad = User::query()->create([
            'name' => 'Ahmad Nursyifa, S.Kom.',
            'email' => 'ahmad@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Akses Jadwal Utama oleh Ahmad Nursyifa
        $response = $this->actingAs($ahmad)->get(route('schedules.main.index'));
        $response->assertOk();
        $response->assertSee('Ahmad Nursyifa, S.Kom.');
        $response->assertSee('Kode Guru: 32');
        $response->assertDontSee('Total: 0 Jam Pelajaran');

        // Pastikan record SchoolScheduleTeacher otomatis terhubung ke akunnya
        $this->assertDatabaseHas('school_schedule_teachers', [
            'academic_year_id' => $year->id,
            'code' => '32',
            'user_id' => $ahmad->id,
        ]);
    }

    public function test_admin_can_export_master_schedule(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.export', [
            'academic_year_id' => $year->id,
            'day' => 1,
            'shift' => 'pagi',
        ]));

        $response->assertOk();
        $response->assertSee('SMP NEGERI 2 LEMAHABANG');
        $response->assertSee('Jadwal Pelajaran Sekolah');
    }
}
