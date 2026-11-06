<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\Subject;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolScheduleBuilderTest extends TestCase
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

        $guru1 = User::query()->create([
            'name' => 'Ahmad Nursyifa',
            'email' => 'ahmad@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $guru2 = User::query()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'start_date' => '2026-07-13',
            'end_date' => '2026-12-19',
        ]);

        $subInformatika = Subject::query()->create([
            'code' => 'INF',
            'name' => 'Informatika',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $subIpa = Subject::query()->create([
            'code' => 'IPA',
            'name' => 'Ilmu Pengetahuan Alam',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        // Buat beberapa kelas
        foreach (['7A', '7B', '8A'] as $c) {
            SchoolClass::query()->create([
                'academic_year_id' => $year->id,
                'name' => $c,
                'grade_level' => substr($c, 0, 1) === '7' ? 'VII' : 'VIII',
                'homeroom_teacher_name' => 'Wali Kelas ' . $c,
            ]);
        }

        return [$admin, $guru1, $guru2, $year, $subInformatika, $subIpa];
    }

    public function test_admin_can_access_builder_page(): void
    {
        [$admin, $guru1, $guru2, $year] = $this->setupEnvironment();

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.builder'));
        $response->assertOk();
        $response->assertSee('Smart Timetable Builder');
        $response->assertSee('1. Model Shift');
        $response->assertSee('2. Waktu Belajar');
        $response->assertSee('3. Non-KBM');
        $response->assertSee('4. Beban Guru');
        $response->assertSee('5. Metode');
        $response->assertSee('6. Konfirmasi');
    }

    public function test_non_admin_cannot_access_builder(): void
    {
        [$admin, $guru1] = $this->setupEnvironment();

        $response = $this->actingAs($guru1)->get(route('admin.schedules.master.builder'));
        $response->assertForbidden();
    }

    public function test_builder_calculate_time_grid_via_ajax(): void
    {
        [$admin] = $this->setupEnvironment();

        $payload = [
            'jp_duration' => 40,
            'start_time' => '07:00',
            'days_count' => 6,
            'daily_jp' => [1 => 7, 2 => 8, 3 => 8, 4 => 8, 5 => 5, 6 => 7],
            'activities' => [
                ['day' => 1, 'name' => 'UPACARA BENDERA', 'position' => 'before_jp_1', 'duration' => 40],
                ['day' => 'all', 'name' => 'ISTIRAHAT', 'position' => 'after_jp_4', 'duration' => 30],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('admin.schedules.master.builder.calculate'), $payload);
        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verifikasi Senin (day 1)
        $senin = $response->json('grid.1');
        $this->assertSame(7, $senin['total_jp']);
        // Slot pertama adalah UPACARA (07.00 - 07.40)
        $this->assertTrue($senin['slots'][0]['is_activity']);
        $this->assertSame('UPACARA BENDERA', $senin['slots'][0]['activity']);
        $this->assertSame('07.00 - 07.40', $senin['slots'][0]['time_range']);
        // JP 1 adalah 07.40 - 08.20
        $this->assertFalse($senin['slots'][1]['is_activity']);
        $this->assertSame(1, $senin['slots'][1]['period_order']);
        $this->assertSame('07.40 - 08.20', $senin['slots'][1]['time_range']);
    }

    public function test_builder_generate_auto_schedule_zero_conflicts(): void
    {
        [$admin, $guru1, $guru2, $year, $subInformatika, $subIpa] = $this->setupEnvironment();

        $payload = [
            'generation_mode' => 'auto',
            'shift_mode' => 'single',
            'selected_classes' => ['7A', '7B'],
            'time_config' => [
                'jp_duration' => 40,
                'start_time' => '07:00',
                'days_count' => 6,
                'daily_jp' => [1 => 7, 2 => 8, 3 => 8, 4 => 8, 5 => 5, 6 => 7],
                'activities' => [
                    ['day' => 1, 'name' => 'UPACARA BENDERA', 'position' => 'before_jp_1', 'duration' => 40],
                    ['day' => 'all', 'name' => 'ISTIRAHAT', 'position' => 'after_jp_4', 'duration' => 30],
                ],
            ],
            'workloads' => [
                [
                    'user_id' => $guru1->id,
                    'teacher_name' => $guru1->name,
                    'teacher_code' => 'T1',
                    'subject_id' => $subInformatika->id,
                    'subject_name' => $subInformatika->name,
                    'class_name' => '7A',
                    'jp_count' => 4,
                ],
                [
                    'user_id' => $guru1->id,
                    'teacher_name' => $guru1->name,
                    'teacher_code' => 'T1',
                    'subject_id' => $subInformatika->id,
                    'subject_name' => $subInformatika->name,
                    'class_name' => '7B',
                    'jp_count' => 4,
                ],
                [
                    'user_id' => $guru2->id,
                    'teacher_name' => $guru2->name,
                    'teacher_code' => 'T2',
                    'subject_id' => $subIpa->id,
                    'subject_name' => $subIpa->name,
                    'class_name' => '7A',
                    'jp_count' => 4,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('admin.schedules.master.builder.generate'), $payload);
        $response->assertRedirect(route('admin.schedules.master.index'));

        // Verifikasi items tersimpan di database
        $items = SchoolScheduleItem::query()->where('academic_year_id', $year->id)->get();
        $this->assertNotEmpty($items);

        // Verifikasi slot non-KBM (Upacara & Istirahat) tersimpan
        $upacara = $items->where('teacher_code', 'UPACARA BENDERA');
        $this->assertNotEmpty($upacara);

        // Verifikasi jadwal guru1 teralokasi
        $t1Slots = $items->where('teacher_code', 'T1');
        $this->assertSame(8, $t1Slots->count()); // 4 JP di 7A + 4 JP di 7B

        // Verifikasi bentrok guru = 0
        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $conflicts = $service->detectConflicts($year);
        $this->assertEmpty($conflicts, 'Jadwal yang digenerate otomatis tidak boleh ada bentrok jam guru');
    }

    public function test_builder_generate_manual_empty_schedule(): void
    {
        [$admin, $guru1, $guru2, $year] = $this->setupEnvironment();

        $payload = [
            'generation_mode' => 'manual',
            'shift_mode' => 'single',
            'selected_classes' => ['7A'],
            'time_config' => [
                'jp_duration' => 40,
                'start_time' => '07:00',
                'days_count' => 6,
                'daily_jp' => [1 => 7, 2 => 8, 3 => 8, 4 => 8, 5 => 5, 6 => 7],
                'activities' => [
                    ['day' => 1, 'name' => 'UPACARA BENDERA', 'position' => 'before_jp_1', 'duration' => 40],
                ],
            ],
            'workloads' => [],
        ];

        $response = $this->actingAs($admin)->post(route('admin.schedules.master.builder.generate'), $payload);
        $response->assertRedirect(route('admin.schedules.master.index'));

        // Verifikasi ada slot pelajaran (JP 1..7) dengan teacher_code null (kosong)
        $emptySlots = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('period_order', '>', 0)
            ->whereNull('teacher_code')
            ->get();
        $this->assertNotEmpty($emptySlots);
    }

    public function test_builder_calculate_time_grid_with_custom_afternoon_start_time(): void
    {
        [$admin] = $this->setupEnvironment();

        $payload = [
            'jp_duration' => 40,
            'start_time' => '07:00',
            'start_time_siang' => '12:30',
            'days_count' => 6,
            'daily_jp' => [1 => 7, 2 => 8, 3 => 8, 4 => 8, 5 => 5, 6 => 7],
            'activities' => [
                ['day' => 1, 'name' => 'UPACARA BENDERA', 'position' => 'before_jp_1', 'duration' => 40],
                ['day' => 'all', 'name' => 'ISTIRAHAT', 'position' => 'after_jp_4', 'duration' => 30],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('admin.schedules.master.builder.calculate'), $payload);
        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verifikasi Senin (day 1)
        $senin = $response->json('grid.1');
        $this->assertArrayHasKey('slots_siang', $senin);

        // JP 1 Siang harus dimulai pada 12:30 dengan durasi 40 menit (12.30 - 13.10)
        $jp1Siang = $senin['slots_siang'][0];
        $this->assertSame(1, $jp1Siang['period_order']);
        $this->assertSame('12.30 - 13.10', $jp1Siang['time_range']);
    }

    public function test_builder_generate_double_shift_with_custom_classes_and_no_conflicts(): void
    {
        [$admin, $guru1, $guru2, $year, $subInformatika, $subIpa] = $this->setupEnvironment();

        $payload = [
            'generation_mode' => 'auto',
            'shift_mode' => 'double',
            'start_time_siang' => '12:15',
            'classes_siang' => ['8A'], // Kelas 8A sebagai shift siang kustom
            'selected_classes' => ['7A', '8A'],
            'time_config' => [
                'jp_duration' => 40,
                'start_time' => '07:00',
                'start_time_siang' => '12:15',
                'days_count' => 6,
                'daily_jp' => [1 => 7, 2 => 8, 3 => 8, 4 => 8, 5 => 5, 6 => 7],
                'activities' => [
                    ['day' => 1, 'name' => 'UPACARA BENDERA', 'position' => 'before_jp_1', 'duration' => 40],
                    ['day' => 'all', 'name' => 'ISTIRAHAT', 'position' => 'after_jp_4', 'duration' => 30],
                ],
            ],
            'workloads' => [
                [
                    'user_id' => $guru1->id,
                    'teacher_name' => $guru1->name,
                    'teacher_code' => 'T1',
                    'subject_id' => $subInformatika->id,
                    'subject_name' => $subInformatika->name,
                    'class_name' => '7A', // Pagi
                    'jp_count' => 4,
                ],
                [
                    'user_id' => $guru1->id,
                    'teacher_name' => $guru1->name,
                    'teacher_code' => 'T1',
                    'subject_id' => $subInformatika->id,
                    'subject_name' => $subInformatika->name,
                    'class_name' => '8A', // Siang
                    'jp_count' => 4,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('admin.schedules.master.builder.generate'), $payload);
        $response->assertRedirect(route('admin.schedules.master.index'));

        // Verifikasi items 7A adalah shift pagi
        $items7A = SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('class_name', '7A')->get();
        $this->assertTrue($items7A->every(fn ($item) => $item->shift === 'pagi'));

        // Verifikasi items 8A adalah shift siang dan memiliki rentang jam siang (dimulai 12.15)
        $items8A = SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('class_name', '8A')->get();
        $this->assertTrue($items8A->every(fn ($item) => $item->shift === 'siang'));
        $kbm8A = $items8A->where('period_order', 1)->first();
        $this->assertNotNull($kbm8A);
        $this->assertSame('12.15 - 12.55', $kbm8A->time_range);

        // Verifikasi guru1 (T1) mengajar total 8 JP tanpa bentrok antara shift pagi dan siang
        $t1Slots = SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('teacher_code', 'T1')->get();
        $this->assertSame(8, $t1Slots->count());

        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $conflicts = $service->detectConflicts($year);
        $this->assertEmpty($conflicts, 'Guru yang mengajar di shift pagi dan siang tidak boleh dihitung bentrok');
    }
}
