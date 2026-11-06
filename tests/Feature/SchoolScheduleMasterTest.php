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

    public function test_admin_schedule_shows_zero_personal_hours_and_defaults_to_matrix_tab(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        // Admin bernama "Administrator Sekolah"
        $admin->update(['name' => 'Administrator Sekolah']);

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Akses Jadwal Utama oleh Admin
        $response = $this->actingAs($admin)->get(route('schedules.main.index'));
        $response->assertOk();

        // 1. Tab default untuk admin harus Matriks 21 Rombel
        $response->assertSee("tab: 'school_matrix'", false);

        // 2. Total jam pribadi admin HARUS 0 Jam Pelajaran, BUKAN 738 jam!
        $response->assertSee('Total: <strong>0 Jam Pelajaran</strong>', false);
        $response->assertDontSee('Total: <strong>738 Jam Pelajaran</strong>', false);

        // 3. Admin tidak boleh otomatis ditautkan ke Kode Guru 1
        $this->assertDatabaseMissing('school_schedule_teachers', [
            'academic_year_id' => $year->id,
            'code' => '1',
            'user_id' => $admin->id,
        ]);
    }

    public function test_master_matrix_is_ordered_chronologically_by_time(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        $matrix = $service->getMasterMatrix($year, 1); // Senin
        $pagiSlots = $matrix['matrix_by_day'][1]['pagi_slots'];

        // Ambil daftar time_range terurut
        $timeRanges = $pagiSlots->pluck('time_range')->values()->all();

        // 1. Baris pertama harus upacara bendera (07.00 - 07.40)
        $this->assertEquals('07.00 - 07.40', $timeRanges[0]);

        // 2. Cari index Jam 4 (09.10 - 09.40), Istirahat (09.40 - 10.10), dan Jam 5 (10.10 - 10.40)
        $idxJam4 = array_search('09.10 - 09.40', $timeRanges);
        $idxIstirahat = array_search('09.40 - 10.10', $timeRanges);
        $idxJam5 = array_search('10.10 - 10.40', $timeRanges);

        $this->assertNotFalse($idxJam4);
        $this->assertNotFalse($idxIstirahat);
        $this->assertNotFalse($idxJam5);

        // Istirahat HARUS berada di tengah antara Jam 4 dan Jam 5, BUKAN di paling atas
        $this->assertGreaterThan($idxJam4, $idxIstirahat, 'Istirahat harus berada setelah Jam 4');
        $this->assertLessThan($idxJam5, $idxIstirahat, 'Istirahat harus berada sebelum Jam 5');
    }

    public function test_matrix_view_contains_teacher_detail_interactive_attributes(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        $response = $this->actingAs($guru)->get(route('schedules.main.index', [
            'tab' => 'school_matrix',
            'day' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('openTeacherDetail');
        $response->assertSee('KODE GURU');
        $response->assertSee('detailModalOpen');
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

    public function test_sidebar_highlights_only_active_schedule_menu_without_green_collision(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        // 1. Saat membuka Jadwal Utama (schedules.main.index):
        $responseMain = $this->actingAs($guru)->get(route('schedules.main.index'));
        $responseMain->assertOk();
        $contentMain = $responseMain->getContent();

        // Jadwal Utama harus aktif (bg-emerald-600)
        $this->assertMatchesRegularExpression('/href="[^"]*jadwal-utama"[^>]*class="[^"]*bg-emerald-600/', $contentMain);
        // Jadwal Hari Ini TIDAK BOLEH aktif (harus text-slate-300, tidak boleh bg-emerald-600)
        $this->assertMatchesRegularExpression('/href="[^"]*jadwal-mengajar"[^>]*class="[^"]*text-slate-300/', $contentMain);

        // 2. Saat membuka Jadwal Hari Ini (schedules.index):
        $responseToday = $this->actingAs($guru)->get(route('schedules.index'));
        $responseToday->assertOk();
        $contentToday = $responseToday->getContent();

        // Jadwal Hari Ini harus aktif (bg-emerald-600)
        $this->assertMatchesRegularExpression('/href="[^"]*jadwal-mengajar"[^>]*class="[^"]*bg-emerald-600/', $contentToday);
        // Jadwal Utama TIDAK BOLEH aktif (harus text-slate-300, tidak boleh bg-emerald-600)
        $this->assertMatchesRegularExpression('/href="[^"]*jadwal-utama"[^>]*class="[^"]*text-slate-300/', $contentToday);
    }

    public function test_admin_sidebar_does_not_render_other_teachers_classes_and_shows_supervision_card(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $subject = \App\Models\Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
        ]);

        $classGuru = \App\Models\TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A, S.Pd.',
        ]);

        // 1. Admin mengakses portal:
        // Admin dengan 0 kelas mengajar tidak boleh melihat daftar kelas guru di sidebar
        $responseAdmin = $this->actingAs($admin)->get(route('admin.dashboard'));
        $responseAdmin->assertOk();

        // Admin melihat pintasan supervisi dan TIDAK melihat daftar akses cepat guru lain di sidebar
        $responseAdmin->assertSee('Filter Buku Guru');
        $responseAdmin->assertDontSee('Akses Cepat Kelas (Ganjil):');

        // 2. Guru mengakses portal:
        // Guru melihat akses cepat kelas miliknya sendiri
        $responseGuru = $this->actingAs($guru)->get(route('classes.index'));
        $responseGuru->assertOk();

        $responseGuru->assertSee('Akses Cepat Kelas (Ganjil):');
        $responseGuru->assertSee('VII-A');

        // 3. Admin yang merangkap guru (memiliki kelas sendiri):
        $classAdmin = \App\Models\TeachingClass::query()->create([
            'teacher_id' => $admin->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-B',
            'homeroom_teacher_name' => 'Wali Kelas VIII-B, S.Pd.',
        ]);

        $responseAdminTeaching = $this->actingAs($admin)->get(route('classes.index'));
        $responseAdminTeaching->assertOk();

        // Admin yang mengajar hanya melihat kelas miliknya (VIII-B) di akses cepat
        $responseAdminTeaching->assertSee('Akses Cepat Kelas (Ganjil):');
        $responseAdminTeaching->assertSee('VIII-B');
    }

    public function test_homeroom_list_is_sorted_by_class_and_does_not_contain_piket_day_as_class(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // 1. Verifikasi database: Bu Teti bukan wali kelas, melainkan guru piket hari Rabu
        $tetiList = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('code', ['24a', '24b'])
            ->get();
        $this->assertNotEmpty($tetiList);
        foreach ($tetiList as $teti) {
            $this->assertNull($teti->homeroom_class, 'Bu Teti tidak boleh memiliki homeroom_class RABU');
            $this->assertSame('RABU', $teti->piket_day, 'Bu Teti harus berstatus guru piket hari RABU');
        }

        // 2. Verifikasi database: Pak Moh. Rifki Firdiansyah wali kelas 9B dan piket Senin
        $rifki = SchoolScheduleTeacher::query()
            ->where('academic_year_id', $year->id)
            ->where('code', '20')
            ->first();
        $this->assertNotNull($rifki);
        $this->assertSame('9B', $rifki->homeroom_class);
        $this->assertSame('SENIN', $rifki->piket_day);

        // 3. Verifikasi getHomeroomList terurut rapi 21 kelas
        $homeroomList = $service->getHomeroomList($year);
        $this->assertCount(21, $homeroomList);

        $expectedClasses = [
            '7A', '7B', '7C', '7D', '7E', '7F', '7G',
            '8A', '8B', '8C', '8D', '8E', '8F', '8G',
            '9A', '9B', '9C', '9D', '9E', '9F', '9G',
        ];
        $actualClasses = $homeroomList->pluck('class_code')->all();
        $this->assertSame($expectedClasses, $actualClasses);

        // Pastikan tidak ada "RABU" di daftar wali kelas
        $this->assertNotContains('RABU', $actualClasses);

        // 4. Verifikasi UI Master Jadwal Tab Piket
        $response = $this->actingAs($guru)->get(route('schedules.main.index', ['tab' => 'piket']));
        $response->assertOk();
        $response->assertSee('Daftar 21 Wali Kelas (Tingkat VII, VIII, IX)');
        $response->assertSee('Kelas 7A');
        $response->assertSee('Kelas 9G');
        $response->assertDontSee('Kelas RABU');
        $response->assertSee('TETI OKTAVIANI, S.Pd'); // Tampil di bagian Jadwal Guru Piket Hari RABU
    }

    public function test_admin_can_swap_cells_across_different_days_in_same_class(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();
        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Slot 1: Kelas 7A di Hari Senin (day 1) Jam 1
        $item1 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('day_of_week', 1)
            ->where('class_name', '7A')
            ->where('period_order', 1)
            ->firstOrFail();

        // Slot 2: Kelas 7A di Hari Rabu (day 3) Jam 3
        $item2 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('day_of_week', 3)
            ->where('class_name', '7A')
            ->where('period_order', 3)
            ->firstOrFail();

        $code1 = $item1->teacher_code;
        $code2 = $item2->teacher_code;

        // Hitung total JP Kelas 7A sebelum penukaran
        $totalJpBefore = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('class_name', '7A')
            ->whereNotNull('teacher_code')
            ->where('teacher_code', '!=', '')
            ->count();

        // Lakukan penukaran slot antar hari di kelas yang sama
        $response = $this->actingAs($admin)->post(route('admin.schedules.master.swap'), [
            'item_id_1' => $item1->id,
            'item_id_2' => $item2->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Nilai sel tertukar
        $this->assertEquals($code2, $item1->fresh()->teacher_code);
        $this->assertEquals($code1, $item2->fresh()->teacher_code);

        // Verifikasi bahwa total JP Kelas 7A tetap 100% sama persis (tidak berkurang atau bertambah)
        $totalJpAfter = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('class_name', '7A')
            ->whereNotNull('teacher_code')
            ->where('teacher_code', '!=', '')
            ->count();

        $this->assertEquals($totalJpBefore, $totalJpAfter, 'Total JP Kelas 7A harus tetap sama setelah penukaran antar-hari');
    }

    public function test_admin_cannot_swap_cells_between_different_classes(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();
        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        // Slot 1: Kelas 7A
        $item1 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('class_name', '7A')
            ->where('period_order', 1)
            ->firstOrFail();

        // Slot 2: Kelas 7B
        $item2 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('class_name', '7B')
            ->where('period_order', 1)
            ->firstOrFail();

        // Test via Session POST -> Ditolak dengan session error
        $response = $this->actingAs($admin)->post(route('admin.schedules.master.swap'), [
            'item_id_1' => $item1->id,
            'item_id_2' => $item2->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Test via JSON API -> Ditolak dengan status 422
        $responseJson = $this->actingAs($admin)->postJson(route('admin.schedules.master.swap'), [
            'item_id_1' => $item1->id,
            'item_id_2' => $item2->id,
        ]);

        $responseJson->assertStatus(422);
        $responseJson->assertJsonFragment(['success' => false]);
    }

    public function test_swap_targets_endpoint_returns_only_same_class_non_common_slots(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();
        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        $item1 = SchoolScheduleItem::query()
            ->where('academic_year_id', $year->id)
            ->where('class_name', '7A')
            ->where('period_order', 1)
            ->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('admin.schedules.master.swap-targets', [
            'item_id' => $item1->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('source.class_name', '7A');

        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);

        // Seluruh slot target harus memiliki label dan bukan dirinya sendiri
        foreach ($slots as $slot) {
            $this->assertNotEquals($item1->id, $slot['id']);
            $this->assertArrayHasKey('day_name', $slot);
            $this->assertArrayHasKey('period_order', $slot);
            $this->assertArrayHasKey('time_range', $slot);
            $this->assertArrayHasKey('label', $slot);

            // Pastikan bukan kegiatan umum seperti istirahat
            if ($slot['teacher_code']) {
                $this->assertNotContains(strtoupper(trim($slot['teacher_code'])), ['ISTIRAHAT', 'UPACARA BENDERA', 'UPACARA']);
            }
        }
    }
}
