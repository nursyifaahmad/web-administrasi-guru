<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherSubject;
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
        $response->assertDontSee('+ Buat Jadwal Baru dari Awal', false);
        $response->assertSee('createChoiceModal');
        $response->assertSee('Buat Jadwal Baru dengan Import File Excel');
        $response->assertSee('Susun Lewat 6 Tahapan Wizard');
        $response->assertSee(route('admin.schedules.master.builder'));
        $response->assertSee('1 Shift (Semua Rombel)');

        $this->actingAs($admin)->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertSee('Tambah Master Guru', false)
            ->assertDontSee('+ Tambah Master Guru', false);

        $this->actingAs($admin)->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSee('Tambah Rombel Baru', false)
            ->assertDontSee('+ Tambah Rombel Baru', false);

        $this->actingAs($admin)->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('Tambah Hari Libur / Agenda', false)
            ->assertDontSee('+ Tambah Hari Libur / Agenda', false);
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

        // Check that 21 classes and 36 master teaching codes exist, without creating bogus subjects
        $this->assertSame(36, TeacherSubject::query()->whereNotNull('teaching_code_normalized')->count());
        $this->assertSame(21, SchoolClass::query()->where('academic_year_id', $year->id)->count());
        $this->assertSame(0, Subject::query()->whereIn('code', ['BDAERAH', 'CIREBON', 'DAERAH', 'INDONESI', 'INGGRIS', 'KEPSEK'])->count());
        $this->assertDatabaseHas('teacher_subjects', [
            'teaching_code_normalized' => '1',
            'subject_id' => null,
            'role_label' => 'Kep.Sek.',
        ]);
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

        // Pastikan Kode 3 (RUDI INDRAWAN, S.Pd.) otomatis tertaut ke akun Master Guru Rudi Indrawan
        $this->assertDatabaseHas('school_schedule_items', [
            'academic_year_id' => $year->id,
            'teacher_code' => '3',
            'user_id' => $guru->id,
        ]);

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

        // Pastikan master kode guru 32 dan slot jadwal otomatis terhubung ke akunnya
        $this->assertDatabaseHas('teacher_subjects', [
            'user_id' => $ahmad->id,
            'teaching_code_normalized' => '32',
        ]);
        $this->assertDatabaseHas('school_schedule_items', [
            'academic_year_id' => $year->id,
            'teacher_code' => '32',
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
        $this->assertDatabaseMissing('teacher_subjects', [
            'user_id' => $admin->id,
            'teaching_code_normalized' => '1',
        ]);
        $this->assertDatabaseMissing('school_schedule_items', [
            'academic_year_id' => $year->id,
            'teacher_code' => '1',
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

        // 1. Verifikasi database terpusat: Bu Teti bukan wali kelas, melainkan guru piket hari RABU
        $tetiCodes = TeacherSubject::query()
            ->with('user')
            ->whereIn('teaching_code_normalized', ['24A', '24B'])
            ->get();
        $this->assertCount(2, $tetiCodes);
        $tetiUser = $tetiCodes->first()->user;
        $this->assertNotNull($tetiUser);
        $this->assertNull($tetiUser->homeroomClassForYear($year->id), 'Bu Teti tidak boleh memiliki tugas wali kelas');
        $this->assertSame(['RABU'], $tetiUser->piket_days_list, 'Bu Teti harus berstatus guru piket hari RABU');

        // 2. Verifikasi database terpusat: Pak M. Rifki Firdiansyah wali kelas IX-B dan piket SENIN
        $rifkiTs = TeacherSubject::query()
            ->with('user')
            ->where('teaching_code_normalized', '20')
            ->first();
        $this->assertNotNull($rifkiTs?->user);
        $this->assertSame('IX-B', $rifkiTs->user->homeroomClassForYear($year->id)?->name);
        $this->assertSame(['SENIN'], $rifkiTs->user->piket_days_list);

        // 3. Verifikasi getHomeroomList terurut rapi 21 kelas dari Master Rombel
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

        // 4. Verifikasi UI Master Jadwal Tab Piket membaca dinamis dari Master Data
        $response = $this->actingAs($guru)->get(route('schedules.main.index', ['tab' => 'piket']));
        $response->assertOk();
        $response->assertSee('Daftar Wali Kelas (21 Rombel)');
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

    public function test_clashing_schedule_slots_appear_as_bentrok_instantly_on_first_load(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        // Buat 2 slot jadwal di hari Selasa jam ke-1 dengan guru yang SAMA (Kode 3) di kelas 7A & 7B
        // Sengaja set is_conflict = false di database awal
        $item1 = SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 2, // Selasa
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.30 - 08.00',
            'class_name' => '7A',
            'teacher_code' => '3',
            'teacher_name' => 'Guru Tiga',
            'subject_name' => 'Matematika',
            'is_conflict' => false,
        ]);

        $item2 = SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 2, // Selasa
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.30 - 08.00',
            'class_name' => '7B',
            'teacher_code' => '3',
            'teacher_name' => 'Guru Tiga',
            'subject_name' => 'Matematika',
            'is_conflict' => false,
        ]);

        // Request pertama kali ke halaman hari Selasa tanpa refresh sebelumnya
        $response = $this->actingAs($admin)->get(route('admin.schedules.master.index', ['day' => 2]));

        $response->assertOk();
        // Memastikan styling sel merah bentrok (tanpa tulisan BENTROK di dalam kotak sel agar ringkas) dan banner konflik tampil pada kunjungan pertama
        $response->assertSee('data-conflict="1"', false);
        $response->assertSee('bg-rose-600 text-white', false);
        $response->assertSee('min-w-[34px]', false);
        $response->assertDontSee('>BENTROK<', false);
        $response->assertDontSee('cellBadgeText', false);
        $response->assertSee('Terdeteksi 1 Titik Jadwal Bertabrakan');

        // Verifikasi dari service bahwa getMasterMatrix langsung menghasilkan is_conflict = true
        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $matrix = $service->getMasterMatrix($year, 2);
        $slot1 = $matrix['matrix_by_day'][2]['pagi_slots']->first();
        $this->assertTrue($slot1['classes']['7A']->is_conflict);
        $this->assertTrue($slot1['classes']['7B']->is_conflict);
    }

    public function test_export_poster_renders_two_shift_side_by_side_and_downloads_pdf(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $mtk = Subject::query()->create(['code' => 'MTK', 'name' => 'MATEMATIKA', 'default_kktp' => 75, 'is_active' => true]);
        $ipa = Subject::query()->create(['code' => 'IPA', 'name' => 'IPA', 'default_kktp' => 75, 'is_active' => true]);

        $budi = User::query()->create([
            'name' => 'Budi Santoso, S.Pd',
            'email' => 'budi.poster@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'piket_days' => ['SENIN'],
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $siti = User::query()->create([
            'name' => 'Siti Aminah, M.Pd',
            'email' => 'siti.poster@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        TeacherSubject::query()->create(['user_id' => $budi->id, 'subject_id' => $mtk->id, 'teaching_code' => '1']);
        TeacherSubject::query()->create(['user_id' => $siti->id, 'subject_id' => $ipa->id, 'teaching_code' => '2']);

        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_id' => $budi->id,
            'homeroom_teacher_name' => $budi->name,
        ]);
        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VIII',
            'name' => 'VIII-A',
            'homeroom_teacher_id' => $siti->id,
            'homeroom_teacher_name' => $siti->name,
        ]);

        // Shift 1 (Pagi): VII & IX
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 0,
            'time_range' => '07.00 - 08.00',
            'class_name' => '7A',
            'teacher_code' => 'UPACARA BENDERA',
        ]);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '08.00 - 08.40',
            'class_name' => '7A',
            'teacher_code' => '1',
            'teacher_name' => $budi->name,
            'subject_name' => 'MATEMATIKA',
            'user_id' => $budi->id,
        ]);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '08.00 - 08.40',
            'class_name' => '9A',
            'teacher_code' => '1',
            'teacher_name' => $budi->name,
            'subject_name' => 'MATEMATIKA',
            'user_id' => $budi->id,
        ]);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 2,
            'time_range' => '10.20 - 10.45',
            'class_name' => '7A',
            'teacher_code' => 'ISTIRAHAT',
        ]);

        // Shift 2 (Siang): VIII
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'siang',
            'period_order' => 1,
            'time_range' => '12.30 - 13.05',
            'class_name' => '8A',
            'teacher_code' => '2',
            'teacher_name' => $siti->name,
            'subject_name' => 'IPA',
            'user_id' => $siti->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.export'));
        $response->assertOk();
        $response->assertSee('Mode 2 Shift (Side-by-Side)');
        $response->assertSee('SHIFT 1 (PAGI)');
        $response->assertSee('SHIFT 2 (SIANG)');
        $response->assertSee('KELAS VII');
        $response->assertSee('KELAS VIII');
        $response->assertSee('KELAS IX');
        $response->assertSee('NAMA GURU PIKET');
        $response->assertSee('WALI KELAS');
        $response->assertSee('ISTIRAHAT');
        $response->assertSee('bg-yellow');
        $response->assertSee('bg-conflict');

        $pdfResponse = $this->actingAs($admin)->get(route('admin.schedules.master.export', [
            'paper_size' => 'F4',
            'orientation' => 'landscape',
            'download' => 1,
        ]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    public function test_export_poster_renders_single_shift_when_only_pagi_exists(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $pai = Subject::query()->create(['code' => 'PAI', 'name' => 'PAI', 'default_kktp' => 75, 'is_active' => true]);
        $tohir = User::query()->create([
            'name' => 'Tohir, S.Ag',
            'email' => 'tohir@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        TeacherSubject::query()->create(['user_id' => $tohir->id, 'subject_id' => $pai->id, 'teaching_code' => '5']);

        foreach (['7A', '8A', '9A'] as $idx => $cls) {
            SchoolScheduleItem::query()->create([
                'academic_year_id' => $year->id,
                'day_of_week' => 1,
                'shift' => 'pagi',
                'period_order' => 1,
                'time_range' => '08.20 - 09.00',
                'class_name' => $cls,
                'teacher_code' => (string) ($idx + 5),
                'teacher_name' => 'Guru ' . $cls,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.export'));
        $response->assertOk();
        $response->assertSee('Mode 1 Shift (Semua Rombel)');
        $response->assertDontSee('SHIFT 2 (SIANG)');
        $response->assertSee('KELAS VII');
        $response->assertSee('KELAS VIII');
        $response->assertSee('KELAS IX');

        $pdfResponse = $this->actingAs($admin)->get(route('admin.schedules.master.export', [
            'paper_size' => 'F4',
            'orientation' => 'portrait',
            'download' => 1,
        ]));
        $pdfResponse->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    public function test_admin_can_export_master_schedule_excel_and_download_template_for_one_and_two_shifts(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $mtk = Subject::query()->create(['code' => 'MTK', 'name' => 'MATEMATIKA', 'default_kktp' => 75, 'is_active' => true]);
        $budi = User::query()->create([
            'name' => 'Budi Santoso, S.Pd',
            'email' => 'budi.xls@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'piket_days' => ['SENIN'],
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        TeacherSubject::query()->create(['user_id' => $budi->id, 'subject_id' => $mtk->id, 'teaching_code' => '1']);
        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_id' => $budi->id,
            'homeroom_teacher_name' => $budi->name,
        ]);

        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '08.20 - 09.00',
            'class_name' => '7A',
            'teacher_code' => '1',
            'user_id' => $budi->id,
        ]);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'siang',
            'period_order' => 1,
            'time_range' => '12.30 - 13.05',
            'class_name' => '8A',
            'teacher_code' => '1',
            'user_id' => $budi->id,
        ]);

        // 2-Shift Excel export
        $twoShiftResp = $this->actingAs($admin)->get(route('admin.schedules.master.export-excel'));
        $twoShiftResp->assertOk();
        $twoTmp = tempnam(sys_get_temp_dir(), 'xls2_') . '.xlsx';
        file_put_contents($twoTmp, $twoShiftResp->streamedContent());

        try {
            $spreadsheet2 = \PhpOffice\PhpSpreadsheet\IOFactory::load($twoTmp);
            $sheet2 = $spreadsheet2->getActiveSheet();
            $this->assertSame('Jadwal Pelajaran', $sheet2->getTitle());
            $this->assertSame(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE,
                $sheet2->getPageSetup()->getOrientation()
            );
            $flat2 = implode('|', array_map(fn ($r) => implode('|', array_map('strval', $r)), $sheet2->toArray()));
            $this->assertStringContainsString('SHIFT 1 (PAGI)', $flat2);
            $this->assertStringContainsString('SHIFT 2 (SIANG)', $flat2);
            $this->assertStringContainsString('KELAS VII', $flat2);
            $this->assertStringContainsString('KELAS VIII', $flat2);
            $this->assertStringContainsString('NAMA GURU PIKET', $flat2);
            $this->assertStringContainsString('WALI KELAS', $flat2);
        } finally {
            @unlink($twoTmp);
        }

        // Blank Template download
        $tplResp = $this->actingAs($admin)->get(route('admin.schedules.master.template'));
        $tplResp->assertOk();
        $tplTmp = tempnam(sys_get_temp_dir(), 'xlstpl_') . '.xlsx';
        file_put_contents($tplTmp, $tplResp->streamedContent());

        try {
            $tplSheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tplTmp)->getActiveSheet();
            $flatTpl = implode('|', array_map(fn ($r) => implode('|', array_map('strval', $r)), $tplSheet->toArray()));
            $this->assertStringContainsString('HARI', $flatTpl);
            $this->assertStringContainsString('WAKTU', $flatTpl);
            $this->assertStringContainsString('WALI KELAS', $flatTpl);
        } finally {
            @unlink($tplTmp);
        }

        // Hapus siang agar menjadi 1-Shift lalu uji export-excel mode 1-Shift
        SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('shift', 'siang')->delete();
        $oneShiftResp = $this->actingAs($admin)->get(route('admin.schedules.master.export-excel'));
        $oneShiftResp->assertOk();
        $oneTmp = tempnam(sys_get_temp_dir(), 'xls1_') . '.xlsx';
        file_put_contents($oneTmp, $oneShiftResp->streamedContent());

        try {
            $sheet1 = \PhpOffice\PhpSpreadsheet\IOFactory::load($oneTmp)->getActiveSheet();
            $this->assertSame(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT,
                $sheet1->getPageSetup()->getOrientation()
            );
            $flat1 = implode('|', array_map(fn ($r) => implode('|', array_map('strval', $r)), $sheet1->toArray()));
            $this->assertStringContainsString('KELAS VII', $flat1);
            $this->assertStringNotContainsString('SHIFT 2 (SIANG)', $flat1);
        } finally {
            @unlink($oneTmp);
        }
    }

    public function test_admin_can_round_trip_import_edited_exported_excel_and_sync_teaching_classes(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $mtk = Subject::query()->create(['name' => 'MATEMATIKA', 'code' => 'MTK', 'default_kktp' => 75, 'is_active' => true]);
        Subject::query()->create(['name' => 'Informatika', 'code' => 'INF', 'default_kktp' => 76, 'is_active' => true]);
        $pjok = Subject::query()->create(['name' => 'Penjasorkes', 'code' => 'PJOK', 'default_kktp' => 75, 'is_active' => true]);

        $budi = User::query()->create([
            'name' => 'Budi Santoso, S.Pd',
            'email' => 'budi.rt@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'piket_days' => ['SENIN'],
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        TeacherSubject::query()->create(['user_id' => $budi->id, 'subject_id' => $mtk->id, 'teaching_code' => '1']);
        TeacherSubject::query()->create(['user_id' => $guru->id, 'subject_id' => $pjok->id, 'teaching_code' => '2']);

        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_id' => $budi->id,
            'homeroom_teacher_name' => $budi->name,
        ]);

        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '08.20 - 09.00',
            'class_name' => '7A',
            'teacher_code' => '1',
            'user_id' => $budi->id,
        ]);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'siang',
            'period_order' => 1,
            'time_range' => '12.30 - 13.05',
            'class_name' => '8A',
            'teacher_code' => '1',
            'user_id' => $budi->id,
        ]);

        // Export -> Edit nilai cell 7A (ganti '1' ke '2') -> Save -> Import ulang dengan sync_to_classes=1
        $exportResp = $this->actingAs($admin)->get(route('admin.schedules.master.export-excel'));
        $exportResp->assertOk();

        $tmpFile = tempnam(sys_get_temp_dir(), 'roundtrip_') . '.xlsx';
        file_put_contents($tmpFile, $exportResp->streamedContent());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Baris 9 (Shift 1 JP 1 kelas 7A kolom D): ubah kode guru dari '1' menjadi '2'
            $this->assertSame('1', (string) $sheet->getCell('D9')->getValue());
            $sheet->setCellValueExplicit('D9', '2', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmpFile);

            $uploaded = \Illuminate\Http\UploadedFile::fake()->createWithContent(
                'Master_Jadwal_Edit.xlsx',
                (string) file_get_contents($tmpFile)
            );

            $importResp = $this->actingAs($admin)->post(route('admin.schedules.master.import'), [
                'academic_year_id' => $year->id,
                'file' => $uploaded,
                'sync_to_classes' => '1',
            ]);

            $importResp->assertRedirect(route('admin.schedules.master.index', ['academic_year_id' => $year->id]));
            $importResp->assertSessionHas('success');

            $updated7A = SchoolScheduleItem::query()
                ->where('academic_year_id', $year->id)
                ->where('day_of_week', 1)
                ->where('shift', 'pagi')
                ->where('class_name', '7A')
                ->first();
            $this->assertNotNull($updated7A);
            $this->assertSame('2', $updated7A->teacher_code);
            $this->assertSame($guru->name, $updated7A->teacher_name);

            $this->assertDatabaseHas('school_schedule_items', [
                'academic_year_id' => $year->id,
                'day_of_week' => 1,
                'shift' => 'siang',
                'class_name' => '8A',
                'teacher_code' => '1',
            ]);

            $this->assertDatabaseHas('teaching_classes', [
                'academic_year_id' => $year->id,
                'teacher_id' => $guru->id,
                'class_name' => 'VII-A',
            ]);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_import_excel_rejects_code_conflict_and_auto_creates_missing_master_teachers(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $mtk = Subject::query()->create(['name' => 'MATEMATIKA', 'code' => 'MTK', 'default_kktp' => 75, 'is_active' => true]);
        Subject::query()->create(['name' => 'Informatika', 'code' => 'INF', 'default_kktp' => 76, 'is_active' => true]);
        TeacherSubject::query()->create([
            'user_id' => $guru->id,
            'subject_id' => $mtk->id,
            'teaching_code' => '1',
        ]);

        // 1. Buat Excel dimana Kode '1' ditulis atas nama GURU BERBEDA -> HARUS DITOLAK
        $conflictSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $conflictSpreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'HARI')->setCellValue('B1', 'JP KE')->setCellValue('C1', 'WAKTU')->setCellValue('D1', 'KELAS VII');
        $sheet->setCellValue('D2', 'A');
        $sheet->setCellValue('A3', 'SENIN')->setCellValue('B3', '1')->setCellValue('C3', '07.40 - 08.20')->setCellValue('D3', '1');
        $sheet->setCellValue('F1', 'Kode')->setCellValue('G1', 'Nama Guru')->setCellValue('H1', 'Mata Pelajaran');
        $sheet->setCellValue('F2', '1')->setCellValue('G2', 'Guru Penyerobot, S.Pd')->setCellValue('H2', 'MATEMATIKA');

        $conflictTmp = tempnam(sys_get_temp_dir(), 'xlscnf_') . '.xlsx';
        $validTmp = tempnam(sys_get_temp_dir(), 'xlsok_') . '.xlsx';

        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($conflictSpreadsheet))->save($conflictTmp);
            $conflictUpload = \Illuminate\Http\UploadedFile::fake()->createWithContent(
                'conflict.xlsx',
                (string) file_get_contents($conflictTmp)
            );

            $respConflict = $this->actingAs($admin)->post(route('admin.schedules.master.import'), [
                'file' => $conflictUpload,
            ]);
            $respConflict->assertRedirect();
            $respConflict->assertSessionHas('error');
            $this->assertStringContainsString('Bentrok Kode Mengajar', (string) session('error'));

            // 2. Buat Excel dengan kode baru '99' (Guru Baru, Piket KAMIS, Wali Kelas VII-B) -> otomatis masuk Master Guru & Rombel
            $okSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sOk = $okSpreadsheet->getActiveSheet();
            $sOk->setCellValue('A1', 'HARI')->setCellValue('B1', 'JP KE')->setCellValue('C1', 'WAKTU')->setCellValue('D1', 'KELAS VII');
            $sOk->setCellValue('D2', 'B');
            $sOk->setCellValue('A3', 'SENIN')->setCellValue('B3', '1')->setCellValue('C3', '07.40 - 08.20')->setCellValue('D3', '99');

            $sOk->setCellValue('F1', 'Kode')->setCellValue('G1', 'Nama Guru')->setCellValue('H1', 'Mata Pelajaran');
            $sOk->setCellValue('F2', '99')->setCellValue('G2', 'Guru Impor Otomatis, S.Pd')->setCellValue('H2', 'INFORMATIKA');

            $sOk->setCellValue('F4', 'No')->setCellValue('G4', 'NAMA GURU PIKET')->setCellValue('H4', 'HARI');
            $sOk->setCellValue('F5', '1')->setCellValue('G5', 'Guru Impor Otomatis, S.Pd')->setCellValue('H5', 'KAMIS');

            $sOk->setCellValue('F7', 'No')->setCellValue('G7', 'NAMA GURU')->setCellValue('H7', 'WALI KELAS');
            $sOk->setCellValue('F8', '1')->setCellValue('G8', 'Guru Impor Otomatis, S.Pd')->setCellValue('H8', '7B');

            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($okSpreadsheet))->save($validTmp);
            $validUpload = \Illuminate\Http\UploadedFile::fake()->createWithContent(
                'valid_new_teacher.xlsx',
                (string) file_get_contents($validTmp)
            );

            $respOk = $this->actingAs($admin)->post(route('admin.schedules.master.import'), [
                'file' => $validUpload,
            ]);
            $respOk->assertRedirect();
            $respOk->assertSessionHas('success');

            $newTeacher = User::query()->where('name', 'Guru Impor Otomatis, S.Pd')->first();
            $this->assertNotNull($newTeacher);
            $this->assertSame(['KAMIS'], $newTeacher->piket_days_list);
            $this->assertDatabaseHas('teacher_subjects', [
                'user_id' => $newTeacher->id,
                'teaching_code_normalized' => '99',
            ]);
            $this->assertDatabaseHas('school_classes', [
                'academic_year_id' => $year->id,
                'name' => 'VII-B',
                'homeroom_teacher_id' => $newTeacher->id,
                'homeroom_teacher_name' => 'Guru Impor Otomatis, S.Pd',
            ]);
        } finally {
            @unlink($conflictTmp);
            @unlink($validTmp);
        }
    }

    public function test_dual_code_teacher_with_piket_appears_once_in_piket_legend_and_excel(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $pai = Subject::query()->create(['name' => 'PAI', 'code' => 'PAI', 'default_kktp' => 75, 'is_active' => true]);
        $snd = Subject::query()->create(['name' => 'B. Daerah', 'code' => 'SND', 'default_kktp' => 75, 'is_active' => true]);

        $ismail = User::query()->create([
            'name' => 'ISMAIL, S.Pd. I',
            'email' => 'ismail.dual@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'piket_days' => ['SELASA'],
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        TeacherSubject::query()->create(['user_id' => $ismail->id, 'subject_id' => $pai->id, 'teaching_code' => '14A']);
        TeacherSubject::query()->create(['user_id' => $ismail->id, 'subject_id' => $snd->id, 'teaching_code' => '14B']);

        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $piketList = $service->getPiketScheduleList();

        $selasaEntries = $piketList->where('piket_day', 'SELASA')->where('name', 'ISMAIL, S.Pd. I');
        $this->assertCount(1, $selasaEntries);
    }

    public function test_import_master_schedule_rejects_invalid_excel_and_denies_guru(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Kolom Ngasal')->setCellValue('B1', 'Tanpa Header Jadwal');
        $badTmp = tempnam(sys_get_temp_dir(), 'badxls_') . '.xlsx';

        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($badTmp);
            $badUpload = \Illuminate\Http\UploadedFile::fake()->createWithContent(
                'invalid.xlsx',
                (string) file_get_contents($badTmp)
            );

            $adminResp = $this->actingAs($admin)->post(route('admin.schedules.master.import'), [
                'file' => $badUpload,
            ]);
            $adminResp->assertRedirect();
            $adminResp->assertSessionHas('error');

            $this->actingAs($guru)->get(route('admin.schedules.master.export-excel'))->assertForbidden();
            $this->actingAs($guru)->get(route('admin.schedules.master.template'))->assertForbidden();
            $this->actingAs($guru)->post(route('admin.schedules.master.import'), [
                'file' => \Illuminate\Http\UploadedFile::fake()->create('test.xlsx', 10),
            ])->assertForbidden();
        } finally {
            @unlink($badTmp);
        }
    }

    public function test_export_poster_pdf_and_excel_render_complete_headmaster_and_kop_academic_year_data(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();
        $year->update([
            'year_name' => '2028/2029',
            'semester' => 'Genap',
        ]);

        // Buat gambar PNG kecil sebagai TTD digital Kepala Sekolah
        $sigImg = imagecreatetruecolor(80, 30);
        $white = imagecolorallocate($sigImg, 255, 255, 255);
        $black = imagecolorallocate($sigImg, 15, 23, 42);
        imagefill($sigImg, 0, 0, $white);
        imageline($sigImg, 5, 20, 75, 8, $black);
        ob_start();
        imagepng($sigImg);
        $pngBytes = ob_get_clean();
        imagedestroy($sigImg);
        $sigDataUri = 'data:image/png;base64,' . base64_encode((string) $pngBytes);

        $school = SchoolSetting::current();
        $school->update([
            'school_name' => 'SMP NEGERI 99 UJICOBA',
            'default_city' => 'Karawang Timur',
            'headmaster_name' => 'Dr. Teti Kusmiyati, M.M.',
            'headmaster_nip' => '19720514 199802 2 003',
            'headmaster_signature' => $sigDataUri,
        ]);

        $mtk = Subject::query()->create(['code' => 'MTK', 'name' => 'MATEMATIKA', 'default_kktp' => 75, 'is_active' => true]);
        TeacherSubject::query()->create(['user_id' => $guru->id, 'subject_id' => $mtk->id, 'teaching_code' => '1']);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '08.00 - 08.40',
            'class_name' => '7A',
            'teacher_code' => '1',
            'teacher_name' => $guru->name,
            'subject_name' => 'MATEMATIKA',
            'user_id' => $guru->id,
        ]);

        // 1. HTML Poster Print (default TTD digital ON + tanggal cetak kustom)
        $htmlResp = $this->actingAs($admin)->get(route('admin.schedules.master.export', [
            'academic_year_id' => $year->id,
            'print_date' => '2026-11-23',
        ]));
        $htmlResp->assertOk();
        $htmlResp->assertSee('TAHUN PELAJARAN 2028/2029');
        $htmlResp->assertSee('SEMESTER GENAP');
        $htmlResp->assertSee('SMP NEGERI 99 UJICOBA');
        $htmlResp->assertSee('Karawang Timur, 23 November 2026');
        $htmlResp->assertSee('Kepala SMP NEGERI 99 UJICOBA');
        $htmlResp->assertSee('Dr. Teti Kusmiyati, M.M.');
        $htmlResp->assertSee('NIP. 19720514 199802 2 003');
        $htmlResp->assertSee('alt="TTD Kepala Sekolah"', false);
        $htmlResp->assertDontSee('........................................');
        $htmlResp->assertSee('print_date=2026-11-23', false);

        // 2. HTML Poster Print dengan toggle TTD Digital OFF (ruang kosong TTD basah)
        $noSigResp = $this->actingAs($admin)->get(route('admin.schedules.master.export', [
            'academic_year_id' => $year->id,
            'print_date' => '2026-11-23',
            'show_digital_signature' => '0',
        ]));
        $noSigResp->assertOk();
        $noSigResp->assertSee('Dr. Teti Kusmiyati, M.M.');
        $noSigResp->assertSee('NIP. 19720514 199802 2 003');
        $noSigResp->assertDontSee('alt="TTD Kepala Sekolah"', false);

        // 3. Download PDF menyertakan tahun ajaran di nama file
        $pdfResp = $this->actingAs($admin)->get(route('admin.schedules.master.export', [
            'academic_year_id' => $year->id,
            'print_date' => '2026-11-23',
            'download' => 1,
        ]));
        $pdfResp->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfResp->getContent());
        $this->assertStringContainsString('20282029_genap', (string) $pdfResp->headers->get('content-disposition'));

        // 4. Export Excel dengan TTD ON vs TTD OFF
        $xlsOnResp = $this->actingAs($admin)->get(route('admin.schedules.master.export-excel', [
            'academic_year_id' => $year->id,
            'print_date' => '2026-11-23',
            'show_digital_signature' => '1',
        ]));
        $xlsOnResp->assertOk();
        $this->assertStringContainsString('20282029_genap', (string) $xlsOnResp->headers->get('content-disposition'));

        $xlsOffResp = $this->actingAs($admin)->get(route('admin.schedules.master.export-excel', [
            'academic_year_id' => $year->id,
            'print_date' => '2026-11-23',
            'show_digital_signature' => '0',
        ]));
        $xlsOffResp->assertOk();

        $tmpOn = tempnam(sys_get_temp_dir(), 'hl_on_') . '.xlsx';
        $tmpOff = tempnam(sys_get_temp_dir(), 'hl_off_') . '.xlsx';
        file_put_contents($tmpOn, $xlsOnResp->streamedContent());
        file_put_contents($tmpOff, $xlsOffResp->streamedContent());

        try {
            $sheetOn = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpOn)->getActiveSheet();
            $sheetOff = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpOff)->getActiveSheet();

            $flatOn = implode('|', array_map(fn ($r) => implode('|', array_map('strval', $r)), $sheetOn->toArray()));
            $this->assertStringContainsString('TAHUN PELAJARAN 2028/2029', $flatOn);
            $this->assertStringContainsString('Karawang Timur, 23 November 2026', $flatOn);
            $this->assertStringContainsString('Kepala SMP NEGERI 99 UJICOBA', $flatOn);
            $this->assertStringContainsString('Dr. Teti Kusmiyati, M.M.', $flatOn);
            $this->assertStringContainsString('NIP. 19720514 199802 2 003', $flatOn);
            $this->assertStringNotContainsString('........................................', $flatOn);

            $this->assertGreaterThan(
                count($sheetOff->getDrawingCollection()),
                count($sheetOn->getDrawingCollection()),
                'Export Excel dengan show_digital_signature=1 harus memuat drawing TTD Kepala Sekolah.'
            );
        } finally {
            @unlink($tmpOn);
            @unlink($tmpOff);
        }
    }

    public function test_import_excel_rejects_unknown_subject_maps_aliases_and_keeps_kepsek_without_creating_subjects(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $bin = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_kktp' => 76, 'is_active' => true]);
        $big = Subject::query()->create(['code' => 'BIG', 'name' => 'Bahasa Inggris', 'default_kktp' => 75, 'is_active' => true]);
        $bd = Subject::query()->create(['code' => 'BD', 'name' => 'Bahasa Daerah', 'default_kktp' => 75, 'is_active' => true]);
        $pai = Subject::query()->create(['code' => 'PAI', 'name' => 'Pendidikan Agama Islam & Budi Pekerti', 'default_kktp' => 78, 'is_active' => true]);

        // 1. Upload Excel berisi varian alias B.INDONESIA, B. INGGRIS, B. CIREBON, b. B. DAERAH, B. DAERAH, serta Kep.Sek.
        $okSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $s = $okSpreadsheet->getActiveSheet();
        $s->setCellValue('A1', 'HARI')->setCellValue('B1', 'JP KE')->setCellValue('C1', 'WAKTU')->setCellValue('D1', 'KELAS VII');
        $s->setCellValue('D2', 'A');
        $s->setCellValue('A3', 'SENIN')->setCellValue('B3', '1')->setCellValue('C3', '07.40 - 08.20')->setCellValue('D3', '4');

        $s->setCellValue('F1', 'Kode')->setCellValue('G1', 'Nama Guru')->setCellValue('H1', 'Mata Pelajaran');
        // Merged 2-row header pada tabel Kode Guru tidak boleh menciptakan guru palsu "KODE / Nama Guru"
        $s->mergeCells('F1:F2')->mergeCells('G1:G2')->mergeCells('H1:H2');
        $s->setCellValue('F3', '1')->setCellValue('G3', 'TETI KUSMIYATI, S.Pd., MM')->setCellValue('H3', 'Kep.Sek.');
        $s->setCellValue('F4', '4')->setCellValue('G4', 'Dra. EEM AMIRAH')->setCellValue('H4', 'B.INDONESIA');
        $s->setCellValue('F5', '10')->setCellValue('G5', 'TRI MULYANI, S.Pd')->setCellValue('H5', 'B. INGGRIS');
        $s->setCellValue('F6', '13')->setCellValue('G6', 'SURYATI, S.Pd')->setCellValue('H6', 'B. CIREBON');
        $s->setCellValue('F7', '14A')->setCellValue('G7', 'ISMAIL, S.Pd. I')->setCellValue('H7', 'a.PAI');
        $s->setCellValue('F8', '14B')->setCellValue('G8', 'ISMAIL, S.Pd. I')->setCellValue('H8', 'b. B. DAERAH');
        $s->setCellValue('F9', '24B')->setCellValue('G9', 'TETI OKTAVIANI, S.Pd')->setCellValue('H9', 'B. DAERAH');

        // 2. Upload Excel berisi mata pelajaran asing yang belum terdaftar di Master KKTP
        $badSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sb = $badSpreadsheet->getActiveSheet();
        $sb->setCellValue('A1', 'HARI')->setCellValue('B1', 'JP KE')->setCellValue('C1', 'WAKTU')->setCellValue('D1', 'KELAS VII');
        $sb->setCellValue('D2', 'A');
        $sb->setCellValue('A3', 'SENIN')->setCellValue('B3', '1')->setCellValue('C3', '07.40 - 08.20')->setCellValue('D3', '88');
        $sb->setCellValue('F1', 'Kode')->setCellValue('G1', 'Nama Guru')->setCellValue('H1', 'Mata Pelajaran');
        $sb->setCellValue('F2', '88')->setCellValue('G2', 'Guru Mapel Asing, S.Pd')->setCellValue('H2', 'ASTRONOMI KUANTUM');

        $okTmp = tempnam(sys_get_temp_dir(), 'alias_ok_') . '.xlsx';
        $badTmp = tempnam(sys_get_temp_dir(), 'alias_bad_') . '.xlsx';

        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($okSpreadsheet))->save($okTmp);
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($badSpreadsheet))->save($badTmp);

            $okResp = $this->actingAs($admin)->post(route('admin.schedules.master.import'), [
                'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('ok.xlsx', (string) file_get_contents($okTmp)),
            ]);
            $okResp->assertRedirect();
            $okResp->assertSessionHas('success');

            $this->assertSame(4, Subject::query()->count(), 'Import alias dan Kep.Sek. tidak boleh menambah subject baru.');
            $this->assertDatabaseMissing('teacher_subjects', ['teaching_code_normalized' => 'KODE']);
            $this->assertDatabaseMissing('users', ['name' => 'Nama Guru']);
            $this->assertDatabaseHas('teacher_subjects', [
                'teaching_code_normalized' => '1',
                'subject_id' => null,
                'role_label' => 'Kep.Sek.',
            ]);
            $this->assertDatabaseHas('teacher_subjects', ['teaching_code_normalized' => '4', 'subject_id' => $bin->id]);
            $this->assertDatabaseHas('teacher_subjects', ['teaching_code_normalized' => '10', 'subject_id' => $big->id]);
            $this->assertDatabaseHas('teacher_subjects', ['teaching_code_normalized' => '13', 'subject_id' => $bd->id]);
            $this->assertDatabaseHas('teacher_subjects', ['teaching_code_normalized' => '14A', 'subject_id' => $pai->id]);
            $this->assertDatabaseHas('teacher_subjects', ['teaching_code_normalized' => '14B', 'subject_id' => $bd->id]);
            $this->assertDatabaseHas('teacher_subjects', ['teaching_code_normalized' => '24B', 'subject_id' => $bd->id]);

            $badResp = $this->actingAs($admin)->post(route('admin.schedules.master.import'), [
                'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('bad.xlsx', (string) file_get_contents($badTmp)),
            ]);
            $badResp->assertRedirect();
            $badResp->assertSessionHas('error');
            $this->assertStringContainsString('belum terdaftar', (string) session('error'));
            $this->assertSame(4, Subject::query()->count());
        } finally {
            @unlink($okTmp);
            @unlink($badTmp);
        }
    }

    public function test_cleanup_migration_merges_duplicate_subjects_and_unlinks_kepsek_safely(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $bd = Subject::query()->create(['code' => 'BD', 'name' => 'Bahasa Daerah', 'default_kktp' => 75, 'is_active' => true]);
        $bin = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_kktp' => 76, 'is_active' => true]);
        $big = Subject::query()->create(['code' => 'BIG', 'name' => 'Bahasa Inggris', 'default_kktp' => 75, 'is_active' => true]);
        $pjok = Subject::query()->create(['code' => 'PJOK', 'name' => 'Pendidikan Jasmani , Olahraga, dan Kesehatan', 'default_kktp' => 75, 'is_active' => true]);
        $pkn = Subject::query()->create(['code' => 'PKN', 'name' => 'Pendidikan Pancasila', 'default_kktp' => 78, 'is_active' => true]);
        $bk = Subject::query()->create(['code' => 'BK', 'name' => 'Bimbingan Konseling', 'default_kktp' => 75, 'is_active' => true]);
        $sbd = Subject::query()->create(['code' => 'SBD', 'name' => 'Seni Budaya & Prakarya', 'default_kktp' => 78, 'is_active' => true]);

        TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $bd->id,
            'class_name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => '-',
        ]);
        TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'subject_id' => $pjok->id,
            'class_name' => 'VII-B',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => '-',
        ]);

        // Simulasikan seluruh baris duplikat hasil insiden migrasi sebelumnya di server
        $bdaerah = Subject::query()->create(['code' => 'BDAERAH', 'name' => 'B. DAERAH', 'default_kktp' => 75, 'is_active' => true]);
        $cirebon = Subject::query()->create(['code' => 'CIREBON', 'name' => 'CIREBON', 'default_kktp' => 75, 'is_active' => true]);
        $daerah = Subject::query()->create(['code' => 'DAERAH', 'name' => 'DAERAH', 'default_kktp' => 75, 'is_active' => true]);
        $indonesi = Subject::query()->create(['code' => 'INDONESI', 'name' => 'INDONESIA', 'default_kktp' => 75, 'is_active' => true]);
        $inggris = Subject::query()->create(['code' => 'INGGRIS', 'name' => 'INGGRIS', 'default_kktp' => 75, 'is_active' => true]);
        $penjas = Subject::query()->create(['code' => 'PENJAS', 'name' => 'PENJAS', 'default_kktp' => 75, 'is_active' => true]);
        $penjaske = Subject::query()->create(['code' => 'PENJASKE', 'name' => 'PENJASKES', 'default_kktp' => 75, 'is_active' => true]);
        $pendpancasila = Subject::query()->create(['code' => 'PENDPANCASIL', 'name' => 'PEND. PANCASILA', 'default_kktp' => 75, 'is_active' => true]);
        $layananbk = Subject::query()->create(['code' => 'LAYANANBK', 'name' => 'LAYANAN BK', 'default_kktp' => 75, 'is_active' => true]);
        $senibudaya = Subject::query()->create(['code' => 'SENIBUDAYA', 'name' => 'SENI BUDAYA', 'default_kktp' => 75, 'is_active' => true]);
        $kepsek = Subject::query()->create(['code' => 'KEPSEK', 'name' => 'Kep.Sek.', 'default_kktp' => 75, 'is_active' => true]);

        $kepsekUser = User::query()->create(['name' => 'Teti Kusmiyati', 'email' => 'teti@test.local', 'role' => 'guru', 'default_subject' => 'Kep.Sek.', 'password' => bcrypt('x')]);
        $suryati = User::query()->create(['name' => 'Suryati', 'email' => 'suryati@test.local', 'role' => 'guru', 'default_subject' => 'CIREBON', 'password' => bcrypt('x')]);
        $ismail = User::query()->create(['name' => 'Ismail', 'email' => 'ismail@test.local', 'role' => 'guru', 'default_subject' => 'B. DAERAH', 'password' => bcrypt('x')]);
        $tetiOkt = User::query()->create(['name' => 'Teti Oktaviani', 'email' => 'teti.o@test.local', 'role' => 'guru', 'default_subject' => 'DAERAH', 'password' => bcrypt('x')]);
        $eem = User::query()->create(['name' => 'Eem Amirah', 'email' => 'eem@test.local', 'role' => 'guru', 'default_subject' => 'INDONESIA', 'password' => bcrypt('x')]);
        $tri = User::query()->create(['name' => 'Tri Mulyani', 'email' => 'tri@test.local', 'role' => 'guru', 'default_subject' => 'INGGRIS', 'password' => bcrypt('x')]);
        $rudiPjk = User::query()->create(['name' => 'Rudi Indrawan PJK', 'email' => 'rudi.pjk@test.local', 'role' => 'guru', 'default_subject' => 'PENJAS', 'password' => bcrypt('x')]);
        $soetanPjk = User::query()->create(['name' => 'Soetan Asrul Sani', 'email' => 'soetan.pjk@test.local', 'role' => 'guru', 'default_subject' => 'PENJASKES', 'password' => bcrypt('x')]);
        $adePkn = User::query()->create(['name' => 'Ade Sri Putih', 'email' => 'ade.pkn@test.local', 'role' => 'guru', 'default_subject' => 'PEND. PANCASILA', 'password' => bcrypt('x')]);
        $piohBk = User::query()->create(['name' => 'Pioh Susanti', 'email' => 'pioh.bk@test.local', 'role' => 'guru', 'default_subject' => 'LAYANAN BK', 'password' => bcrypt('x')]);
        $nopiSbd = User::query()->create(['name' => 'Nopi Nopiani', 'email' => 'nopi.sbd@test.local', 'role' => 'guru', 'default_subject' => 'SENI BUDAYA', 'password' => bcrypt('x')]);

        TeacherSubject::query()->create(['user_id' => $kepsekUser->id, 'subject_id' => $kepsek->id, 'teaching_code' => '1']);
        TeacherSubject::query()->create(['user_id' => $suryati->id, 'subject_id' => $cirebon->id, 'teaching_code' => '13']);
        TeacherSubject::query()->create(['user_id' => $ismail->id, 'subject_id' => $bdaerah->id, 'teaching_code' => '14B']);
        TeacherSubject::query()->create(['user_id' => $tetiOkt->id, 'subject_id' => $daerah->id, 'teaching_code' => '24B']);
        TeacherSubject::query()->create(['user_id' => $eem->id, 'subject_id' => $indonesi->id, 'teaching_code' => '4']);
        TeacherSubject::query()->create(['user_id' => $tri->id, 'subject_id' => $inggris->id, 'teaching_code' => '10']);
        TeacherSubject::query()->create(['user_id' => $rudiPjk->id, 'subject_id' => $penjas->id, 'teaching_code' => '3']);
        TeacherSubject::query()->create(['user_id' => $soetanPjk->id, 'subject_id' => $penjaske->id, 'teaching_code' => '16']);
        TeacherSubject::query()->create(['user_id' => $adePkn->id, 'subject_id' => $pendpancasila->id, 'teaching_code' => '22']);
        TeacherSubject::query()->create(['user_id' => $piohBk->id, 'subject_id' => $layananbk->id, 'teaching_code' => '33']);
        TeacherSubject::query()->create(['user_id' => $nopiSbd->id, 'subject_id' => $senibudaya->id, 'teaching_code' => '19']);

        $migration = require database_path('migrations/2026_11_23_000001_cleanup_duplicate_subjects_and_allow_nullable_teacher_subject.php');
        $migration->up();
        $migration2 = require database_path('migrations/2026_11_23_000002_cleanup_remaining_duplicate_subjects.php');
        $migration2->up();

        $this->assertSame(7, Subject::query()->count());
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $kepsekUser->id, 'subject_id' => null, 'role_label' => 'Kep.Sek.', 'teaching_code_normalized' => '1']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $suryati->id, 'subject_id' => $bd->id, 'teaching_code_normalized' => '13']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $ismail->id, 'subject_id' => $bd->id, 'teaching_code_normalized' => '14B']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $tetiOkt->id, 'subject_id' => $bd->id, 'teaching_code_normalized' => '24B']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $eem->id, 'subject_id' => $bin->id, 'teaching_code_normalized' => '4']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $tri->id, 'subject_id' => $big->id, 'teaching_code_normalized' => '10']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $rudiPjk->id, 'subject_id' => $pjok->id, 'teaching_code_normalized' => '3']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $soetanPjk->id, 'subject_id' => $pjok->id, 'teaching_code_normalized' => '16']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $adePkn->id, 'subject_id' => $pkn->id, 'teaching_code_normalized' => '22']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $piohBk->id, 'subject_id' => $bk->id, 'teaching_code_normalized' => '33']);
        $this->assertDatabaseHas('teacher_subjects', ['user_id' => $nopiSbd->id, 'subject_id' => $sbd->id, 'teaching_code_normalized' => '19']);
        $this->assertSame('Bahasa Daerah', $suryati->fresh()->default_subject);
        $this->assertSame('Pendidikan Jasmani , Olahraga, dan Kesehatan', $rudiPjk->fresh()->default_subject);
        $this->assertDatabaseHas('teaching_classes', ['subject_id' => $bd->id, 'class_name' => 'VII-A']);
        $this->assertDatabaseHas('teaching_classes', ['subject_id' => $pjok->id, 'class_name' => 'VII-B']);
    }

    public function test_admin_can_download_blank_template_in_explicit_single_and_double_shift_modes(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        SchoolClass::query()->create(['academic_year_id' => $year->id, 'grade_level' => 'VII', 'name' => 'VII-A', 'homeroom_teacher_name' => '-']);
        SchoolClass::query()->create(['academic_year_id' => $year->id, 'grade_level' => 'VIII', 'name' => 'VIII-A', 'homeroom_teacher_name' => '-']);
        SchoolClass::query()->create(['academic_year_id' => $year->id, 'grade_level' => 'IX', 'name' => 'IX-A', 'homeroom_teacher_name' => '-']);

        // 1. Database belum punya slot jadwal -> tetap bisa download template 1 Shift & 2 Shift sesuai pilihan
        $singleResp = $this->actingAs($admin)->get(route('admin.schedules.master.template', [
            'academic_year_id' => $year->id,
            'shift_mode' => 'single',
        ]));
        $singleResp->assertOk();
        $this->assertStringContainsString('Template_Master_Jadwal_1_Shift', (string) $singleResp->headers->get('content-disposition'));

        $doubleResp = $this->actingAs($admin)->get(route('admin.schedules.master.template', [
            'academic_year_id' => $year->id,
            'shift_mode' => 'double',
        ]));
        $doubleResp->assertOk();
        $this->assertStringContainsString('Template_Master_Jadwal_2_Shift', (string) $doubleResp->headers->get('content-disposition'));

        $tmpSingle = tempnam(sys_get_temp_dir(), 'tpl_s_') . '.xlsx';
        $tmpDouble = tempnam(sys_get_temp_dir(), 'tpl_d_') . '.xlsx';
        file_put_contents($tmpSingle, $singleResp->streamedContent());
        file_put_contents($tmpDouble, $doubleResp->streamedContent());

        try {
            $sheetSingle = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpSingle)->getActiveSheet();
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT, $sheetSingle->getPageSetup()->getOrientation());
            $flatSingle = implode('|', array_map(fn ($r) => implode('|', array_map('strval', $r)), $sheetSingle->toArray()));
            $this->assertStringContainsString('KELAS VII', $flatSingle);
            $this->assertStringContainsString('KELAS VIII', $flatSingle);
            $this->assertStringContainsString('KELAS IX', $flatSingle);
            $this->assertStringNotContainsString('SHIFT 2 (SIANG)', $flatSingle);

            $sheetDouble = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpDouble)->getActiveSheet();
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE, $sheetDouble->getPageSetup()->getOrientation());
            $flatDouble = implode('|', array_map(fn ($r) => implode('|', array_map('strval', $r)), $sheetDouble->toArray()));
            $this->assertStringContainsString('SHIFT 1 (PAGI)', $flatDouble);
            $this->assertStringContainsString('SHIFT 2 (SIANG)', $flatDouble);
            $this->assertStringContainsString('KELAS VII', $flatDouble);
            $this->assertStringContainsString('KELAS VIII', $flatDouble);
            $this->assertStringContainsString('KELAS IX', $flatDouble);
        } finally {
            @unlink($tmpSingle);
            @unlink($tmpDouble);
        }
    }

    public function test_importing_single_shift_excel_replaces_two_shift_ui_matrix_and_shows_class_viii_together(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();
        $bin = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_kktp' => 76, 'is_active' => true]);
        $ips = Subject::query()->create(['code' => 'IPS', 'name' => 'Ilmu Pengetahuan Sosial', 'default_kktp' => 76, 'is_active' => true]);

        // Seed jadwal 2 shift sebelumnya agar teruji terganti penuh menjadi 1 shift
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.40 - 08.10',
            'class_name' => '7A',
            'teacher_code' => '2',
            'teacher_name' => 'Guru 2',
            'subject_name' => $bin->name,
        ]);
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 1,
            'shift' => 'siang',
            'period_order' => 1,
            'time_range' => '12.00 - 12.30',
            'class_name' => '8A',
            'teacher_code' => '11',
            'teacher_name' => 'Guru 11',
            'subject_name' => $ips->name,
        ]);

        // Buat file Excel 1 Shift berisi Kelas VII, VIII, IX dalam satu tabel
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setCellValue('A1', 'HARI')->setCellValue('B1', 'JP KE')->setCellValue('C1', 'WAKTU')
            ->setCellValue('D1', 'KELAS VII')->setCellValue('E1', 'KELAS VIII')->setCellValue('F1', 'KELAS IX');
        $sh->setCellValue('D2', 'A')->setCellValue('E2', 'A')->setCellValue('F2', 'A');
        $sh->setCellValue('A3', 'SENIN')->setCellValue('B3', '0')->setCellValue('C3', '07.00 - 08.00')
            ->setCellValue('D3', 'UPACARA BENDERA');
        $sh->mergeCells('D3:F3');
        $sh->setCellValue('B4', '')->setCellValue('C4', '08.00 - 08.20')
            ->setCellValue('D4', 'PEMBIASAAN (BACA ALQURAN DI KELAS)');
        $sh->mergeCells('D4:F4');
        $sh->setCellValue('B5', '1')->setCellValue('C5', '08.20 - 09.00')
            ->setCellValue('D5', '2')->setCellValue('E5', '14A')->setCellValue('F5', '16');
        $sh->setCellValue('B6', '7')->setCellValue('C6', '13.15 - 13.55')
            ->setCellValue('D6', '2')->setCellValue('E6', '14A')->setCellValue('F6', '16');

        $sh->setCellValue('H1', 'Kode')->setCellValue('I1', 'Nama Guru')->setCellValue('J1', 'Mata Pelajaran');
        $sh->setCellValue('H2', '2')->setCellValue('I2', 'Guru Dua, S.Pd')->setCellValue('J2', 'Bahasa Indonesia');
        $sh->setCellValue('H3', '14A')->setCellValue('I3', 'Guru Empat Belas, S.Pd')->setCellValue('J3', 'Ilmu Pengetahuan Sosial');
        $sh->setCellValue('H4', '16')->setCellValue('I4', 'Guru Enam Belas, S.Pd')->setCellValue('J4', 'Bahasa Indonesia');

        $tmp = tempnam(sys_get_temp_dir(), 'single_shift_') . '.xlsx';
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($tmp);

            $postResp = $this->actingAs($admin)->followingRedirects()->post(route('admin.schedules.master.import'), [
                'academic_year_id' => $year->id,
                'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('jadwal_1_shift.xlsx', (string) file_get_contents($tmp)),
            ]);

            $postResp->assertOk();
            $postResp->assertSee('Impor Master Jadwal berhasil!');
            $postResp->assertSee('(1 shift)');
            $postResp->assertSee('3 Rombel');
            $postResp->assertSee('1 Shift (Semua Rombel)');
            $postResp->assertSee('Jadwal 1 Shift:');
            $postResp->assertSee('07.00 – 13.55 WIB');
            $postResp->assertSee('PEMBIASAAN (BACA ALQURAN DI KELAS)');
            $postResp->assertSee('8A');
            $postResp->assertSee('14A');
            $postResp->assertDontSee('Shift Siang:');
            $postResp->assertDontSee('Belum ada data jadwal untuk Shift Siang');

            $this->assertSame(0, SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('shift', 'siang')->count());
            $this->assertSame(12, SchoolScheduleItem::query()->where('academic_year_id', $year->id)->where('shift', 'pagi')->count());
            $this->assertDatabaseHas('school_schedule_items', [
                'academic_year_id' => $year->id,
                'teacher_code' => 'PEMBIASAAN (BACA ALQURAN DI KELAS)',
            ]);

            $mainResp = $this->actingAs($admin)->get(route('schedules.main.index', [
                'tab' => 'school_matrix',
                'day' => 1,
            ]));
            $mainResp->assertOk();
            $mainResp->assertSee('Matriks 3 Rombel Sekolah');
            $mainResp->assertSee('Jadwal 1 Shift:');
            $mainResp->assertSee('8A');
            $mainResp->assertDontSee('Shift Siang:');
        } finally {
            @unlink($tmp);
        }
    }

    public function test_failed_schedule_import_renders_visible_error_flash_banner_on_redirect(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ss->getActiveSheet()->setCellValue('A1', 'KOLOM TIDAK VALID');
        $tmp = tempnam(sys_get_temp_dir(), 'bad_layout_') . '.xlsx';

        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($tmp);

            $resp = $this->actingAs($admin)->followingRedirects()->post(route('admin.schedules.master.import'), [
                'academic_year_id' => $year->id,
                'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('bad_layout.xlsx', (string) file_get_contents($tmp)),
            ]);

            $resp->assertOk();
            $resp->assertSee('Gagal mengimpor file Master Jadwal:');
        } finally {
            @unlink($tmp);
        }
    }

    public function test_master_schedule_displays_all_six_days_and_all_teacher_codes_simultaneously(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();
        /** @var SchoolScheduleService $service */
        $service = app(SchoolScheduleService::class);
        $service->importFromWakasekCsv($year);

        $response = $this->actingAs($admin)->get(route('admin.schedules.master.index'));

        $response->assertOk();
        foreach ([1, 2, 3, 4, 5, 6] as $d) {
            $response->assertSee('data-day-section="' . $d . '"', false);
        }
        $response->assertSee('SEMUA HARI (SENIN–SABTU)');
        $response->assertSee('Semua Kode Guru, Wali Kelas &amp; Guru Piket', false);
        $response->assertSee('Mode Tukar Cepat Semua Sel');
        $response->assertSee('onCellClick');
        $response->assertSee('onCellPointerDown');
        $response->assertViewHas('activeDay', 'all');

        // Saat ?day=2 tetap memuat semua 6 hari untuk drag & drop lintas hari
        $dayResp = $this->actingAs($admin)->get(route('admin.schedules.master.index', ['day' => 2]));
        $dayResp->assertOk();
        $dayResp->assertViewHas('activeDay', 2);
        $dayResp->assertSee('data-day-section="1"', false);
        $dayResp->assertSee('data-day-section="6"', false);
    }

    public function test_json_swap_cells_returns_updated_items_and_live_conflicts_when_resolved(): void
    {
        [$admin, $guru, $year] = $this->createEnvironment();

        // Buat bentrok Selasa Jam 1 untuk Kode 3 di kelas 7A & 7B
        $item7aTue = SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 2,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.40 - 08.20',
            'class_name' => '7A',
            'teacher_code' => '3',
            'teacher_name' => 'Guru Tiga',
            'subject_name' => 'PJOK',
        ]);
        $item7bTue = SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 2,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.40 - 08.20',
            'class_name' => '7B',
            'teacher_code' => '3',
            'teacher_name' => 'Guru Tiga',
            'subject_name' => 'PJOK',
        ]);
        $item7aThu = SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 4,
            'shift' => 'pagi',
            'period_order' => 2,
            'time_range' => '08.20 - 09.00',
            'class_name' => '7A',
            'teacher_code' => '7',
            'teacher_name' => 'Guru Tujuh',
            'subject_name' => 'Matematika',
        ]);

        // Pastikan sebelum swap terdeteksi 1 bentrok
        app(SchoolScheduleService::class)->detectConflicts($year);
        $this->assertTrue((bool) $item7aTue->fresh()->is_conflict);
        $this->assertTrue((bool) $item7bTue->fresh()->is_conflict);

        $resp = $this->actingAs($admin)->postJson(route('admin.schedules.master.swap'), [
            'item_id_1' => $item7aTue->id,
            'item_id_2' => $item7aThu->id,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('success', true);
        $resp->assertJsonPath('conflicts_count', 0);
        $resp->assertJsonPath('conflicts', []);
        $resp->assertJsonPath("items.{$item7aTue->id}.teacher_code", '7');
        $resp->assertJsonPath("items.{$item7aTue->id}.is_conflict", false);
        $resp->assertJsonPath("items.{$item7aThu->id}.teacher_code", '3');
        $resp->assertJsonPath("items.{$item7aThu->id}.is_conflict", false);

        // Pastikan sel pasangan 7B yang tadinya bentrok juga ikut pulih di DB
        $this->assertFalse((bool) $item7bTue->fresh()->is_conflict);
    }
}
