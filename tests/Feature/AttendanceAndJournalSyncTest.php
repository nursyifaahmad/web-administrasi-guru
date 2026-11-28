<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\SchoolScheduleItem;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\AttendanceJournalSyncService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceAndJournalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function createClassWithFiveStudents(): array
    {
        $guru = User::query()->create([
            'name' => 'Guru Uji, S.Pd.',
            'email' => 'guru.uji@sekolah.sch.id',
            'nip' => '19910101 202001 1 001',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
        ]);

        $tClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas Uji, S.Pd.',
            'homeroom_teacher_nip' => '19800101 200501 2 002',
        ]);

        $students = [];
        for ($i = 1; $i <= 5; $i++) {
            $students[] = ClassStudent::query()->create([
                'teaching_class_id' => $tClass->id,
                'order_number' => $i,
                'full_name' => "Murid Ke-{$i}",
                'gender' => $i % 2 === 0 ? 'P' : 'L',
                'is_active' => true,
            ]);
        }

        return [$guru, $tClass, $students];
    }

    public function test_dispensasi_is_counted_as_present_while_distinguished_in_date_column_and_synced_to_journal(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        // Buat jurnal harian terlebih dahulu pada tanggal 2026-11-03
        $journal = DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-03',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Materi Persamaan Linear',
            'present_count' => 0,
            'absent_count' => 0,
            'is_attendance_overridden' => false,
        ]);

        // Simpan absensi 5 murid: 1='.', 2='d' (dispen), 3='s', 4='i', 5='a'
        // Ekspektasi: Jumlah Hadir = 2 (. + d), Tidak Hadir = 3 (s + i + a)
        $cells = [
            (string) $students[0]->id => [3 => '.'],
            (string) $students[1]->id => [3 => 'd'],
            (string) $students[2]->id => [3 => 's'],
            (string) $students[3]->id => [3 => 'i'],
            (string) $students[4]->id => [3 => 'a'],
        ];

        $response = $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => $cells,
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);

        // Pastikan Jurnal Harian otomatis tersinkronisasi: Hadir = 2, Tidak Hadir = 3
        $journal->refresh();
        $this->assertSame(2, $journal->present_count);
        $this->assertSame(3, $journal->absent_count);

        // Pastikan matriks bulanan membedakan 'd' di kolom tanggal namun menghitungnya sebagai Hadir pada murid ke-2
        $syncService = app(AttendanceJournalSyncService::class);
        $matrix = $syncService->buildMonthlyMatrix($tClass, 2026, 11);

        $rowDispenStudent = collect($matrix['rows'])->firstWhere('no', 2);
        $this->assertSame('d', $rowDispenStudent['days'][3]);
        $this->assertSame(1, $rowDispenStudent['dispen']);
        $this->assertSame(1, $rowDispenStudent['present']);
        $this->assertSame(0, $rowDispenStudent['absent']);

        $this->assertSame(2, $matrix['grand_totals']['present']);
        $this->assertSame(1, $matrix['grand_totals']['sakit']);
        $this->assertSame(1, $matrix['grand_totals']['izin']);
        $this->assertSame(1, $matrix['grand_totals']['alfa']);
        $this->assertSame(3, $matrix['grand_totals']['absent']);
    }

    public function test_journal_attendance_is_locked_and_always_automatically_synced_even_when_journal_created_before_attendance(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        // 1. Kode absensi tidak valid ditolak
        $badResp = $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => [
                (string) $students[0]->id => [4 => 'x'],
            ],
        ]);
        $badResp->assertStatus(422);

        // 2. Guru mengisi jurnal terlebih dahulu sebelum absensi (pada tanggal 2026-11-05)
        // Meskipun form mengirimkan input manual present_count/is_attendance_overridden, backend menguncinya mengikuti absensi (0 hadir saat ini)
        $this->actingAs($guru)->post(route('journals.store', $tClass), [
            'journal_date' => '2026-11-05',
            'lesson_hours' => '3 - 4',
            'activity_material' => 'Diskusi Kelompok',
            'present_count' => 99,
            'absent_count' => 50,
            'is_attendance_overridden' => 1,
        ])->assertRedirect();

        $journal = DailyJournal::query()->whereDate('journal_date', '2026-11-05')->firstOrFail();
        $this->assertFalse($journal->is_attendance_overridden);
        $this->assertSame(0, $journal->present_count);
        $this->assertSame(0, $journal->absent_count);

        // 3. Guru kemudian mengisi menu Absensi untuk tanggal 2026-11-05: 4 hadir (.), 1 sakit (s)
        $attendanceData = [
            (string) $students[0]->id => [5 => '.'],
            (string) $students[1]->id => [5 => '.'],
            (string) $students[2]->id => [5 => '.'],
            (string) $students[3]->id => [5 => '.'],
            (string) $students[4]->id => [5 => 's'],
        ];
        $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => $attendanceData,
        ])->assertOk();

        // Jurnal otomatis tersinkronisasi menjadi 4 Hadir, 1 Tidak Hadir tanpa perlu klik resync manual!
        $journal->refresh();
        $this->assertSame(4, $journal->present_count);
        $this->assertSame(1, $journal->absent_count);
        $this->assertStringContainsString('S:1', (string) $journal->attendance_breakdown);

        // 4. Guru memperbarui absensi menjadi semua hadir (5 murid .)
        $allPresent = [];
        foreach ($students as $st) {
            $allPresent[(string) $st->id] = [5 => '.'];
        }
        $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => $allPresent,
        ])->assertOk();

        // Jurnal otomatis terupdate lagi menjadi 5 Hadir, 0 Tidak Hadir!
        $journal->refresh();
        $this->assertSame(5, $journal->present_count);
        $this->assertSame(0, $journal->absent_count);

        // 5. Guru mencoba mengedit jurnal dengan angka manual sembarangan -> tetap dikunci mengikuti absensi
        $this->actingAs($guru)->put(route('journals.update', [$tClass, $journal]), [
            'journal_date' => '2026-11-05',
            'lesson_hours' => '3 - 4',
            'activity_material' => 'Diskusi Kelompok - Revisi',
            'present_count' => 12,
            'absent_count' => 88,
        ])->assertRedirect();

        $journal->refresh();
        $this->assertSame(5, $journal->present_count);
        $this->assertSame(0, $journal->absent_count);
        $this->assertFalse($journal->is_attendance_overridden);
    }

    public function test_attendance_and_gradebook_pages_render_bottom_save_cards_sticky_bars_and_navigation_guards(): void
    {
        [$guru, $tClass] = $this->createClassWithFiveStudents();

        $attPage = $this->actingAs($guru)->get(route('attendances.index', [$tClass, 'tab' => 'monthly', 'month' => 11, 'year' => 2026]));
        $attPage->assertOk()
            ->assertSee('Simpan Seluruh Data Absensi Bulan', false)
            ->assertSee('Simpan Absensi (Ctrl+S)', false)
            ->assertSee('Urungkan Perubahan', false)
            ->assertSee('window.confirmWorkspaceDiscard', false)
            ->assertSee('window.handleGuardedSelectNavigate', false);

        $gradePage = $this->actingAs($guru)->get(route('grades.index', $tClass));
        $gradePage->assertOk()
            ->assertSee('Simpan Seluruh Daftar Nilai & Perbarui Nilai Akhir (NA)', false)
            ->assertSee('Simpan Nilai', false)
            ->assertSee('Ctrl + S', false)
            ->assertSee('Urungkan Perubahan', false)
            ->assertSee('ensureSavedBeforeAction', false)
            ->assertSee('window.confirmWorkspaceDiscard', false);
    }

    public function test_absent_students_endpoint_returns_correct_absent_list_with_statuses(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        // Isi absensi pada tanggal 2026-11-10:
        // Murid 1: Hadir (.)
        // Murid 2: Sakit (s)
        // Murid 3: Izin (i)
        // Murid 4: Alpa (a)
        // Murid 5: Dispen (d)
        $cells = [
            (string) $students[0]->id => [10 => '.'],
            (string) $students[1]->id => [10 => 's'],
            (string) $students[2]->id => [10 => 'i'],
            (string) $students[3]->id => [10 => 'a'],
            (string) $students[4]->id => [10 => 'd'],
        ];

        $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => $cells,
        ])->assertOk();

        // Buat jurnal harian tanggal tersebut
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-10',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Materi Aljabar',
            'present_count' => 2, // . + d
            'absent_count' => 3,  // s + i + a
        ]);

        // Panggil endpoint absent-students
        $res = $this->actingAs($guru)->getJson(route('journals.absent-students', [
            'teachingClass' => $tClass->id,
            'date' => '2026-11-10',
        ]));

        $res->assertOk();
        $data = $res->json();

        $this->assertSame('2026-11-10', $data['date']);
        $this->assertSame(3, $data['total_absent']);
        $this->assertSame(1, $data['sakit_count']);
        $this->assertSame(1, $data['izin_count']);
        $this->assertSame(1, $data['alfa_count']);
        $this->assertSame(1, $data['total_dispen']);

        $returnedStudentNames = collect($data['students'])->pluck('full_name')->toArray();
        $this->assertContains('Murid Ke-2', $returnedStudentNames);
        $this->assertContains('Murid Ke-3', $returnedStudentNames);
        $this->assertContains('Murid Ke-4', $returnedStudentNames);
        $this->assertContains('Murid Ke-5', $returnedStudentNames);
        $this->assertNotContains('Murid Ke-1', $returnedStudentNames); // Murid Ke-1 Hadir, tidak boleh masuk list
    }

    public function test_journal_index_renders_lihat_button_for_absent_count_greater_than_zero(): void
    {
        [$guru, $tClass] = $this->createClassWithFiveStudents();

        // Jurnal 1: ada murid tidak hadir (absent_count = 2)
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-12',
            'lesson_hours' => '3 - 4',
            'activity_material' => 'Latihan Soal',
            'present_count' => 3,
            'absent_count' => 2,
        ]);

        // Jurnal 2: semua hadir (absent_count = 0)
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-13',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Diskusi Kelompok',
            'present_count' => 5,
            'absent_count' => 0,
        ]);

        $res = $this->actingAs($guru)->get(route('journals.index', $tClass));
        $res->assertOk();

        // Tombol Lihat muncul untuk tanggal 2026-11-12
        $res->assertSee("openAbsentDetail('2026-11-12')", false);
        $res->assertSee('Lihat', false);

        // Modal dan rincian murid tidak hadir ada di dokumen
        $res->assertSee('absentModal', false);
        $res->assertSee('Rincian Murid Tidak Hadir', false);
    }

    public function test_journal_attendance_preview_includes_master_schedule_suggestion(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();
        $year = $tClass->academicYear;

        // Hubungkan kode mengajar 32 pada Master Guru (TeacherSubject)
        TeacherSubject::query()->create([
            'user_id' => $guru->id,
            'subject_id' => $tClass->subject_id,
            'teaching_code' => '32',
        ]);

        // Tanggal 2026-11-05 adalah hari Kamis (day_of_week = 4)
        // Tambahkan item jadwal untuk kelas 7A (VII-A) pada hari Kamis jam 1-2
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 4,
            'shift' => 'pagi',
            'period_order' => 1,
            'time_range' => '07.40 - 08.10',
            'class_name' => '7A',
            'teacher_code' => '32',
            'teacher_name' => $guru->name,
            'user_id' => $guru->id,
        ]);

        SchoolScheduleItem::query()->create([
            'academic_year_id' => $year->id,
            'day_of_week' => 4,
            'shift' => 'pagi',
            'period_order' => 2,
            'time_range' => '08.10 - 08.40',
            'class_name' => '7A',
            'teacher_code' => '32',
            'teacher_name' => $guru->name,
            'user_id' => $guru->id,
        ]);

        // 1. Cek tanggal Kamis: harus mengembalikan saran jam '1 - 2' dan rentang '07.40 - 08.40'
        $response = $this->actingAs($guru)->getJson(route('journals.attendance-preview', [
            'teachingClass' => $tClass,
            'date' => '2026-11-05',
        ]));

        $response->assertOk();
        $response->assertJsonPath('schedule.found', true);
        $response->assertJsonPath('schedule.lesson_hours', '1 - 2');
        $response->assertJsonPath('schedule.time_range', '07.40 - 08.40');
        $response->assertJsonPath('schedule.day_name', 'Kamis');
        $response->assertJsonPath('schedule.source', 'master_schedule');

        // 2. Cek hari Minggu: harus mengembalikan found = false
        $responseSunday = $this->actingAs($guru)->getJson(route('journals.attendance-preview', [
            'teachingClass' => $tClass,
            'date' => '2026-11-08',
        ]));

        $responseSunday->assertOk();
        $responseSunday->assertJsonPath('schedule.found', false);
    }

    public function test_journal_index_view_contains_auto_schedule_state_and_helper_badges(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        $response = $this->actingAs($guru)->get(route('journals.index', $tClass));
        $response->assertOk();
        $response->assertSee('Otomatis Master Jadwal', false);
        $response->assertSee('scheduleInfo', false);
        $response->assertSee('Terapkan Jam Master', false);
    }

    public function test_attendance_view_includes_purple_active_name_cell_highlight_hooks(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        $response = $this->actingAs($guru)->get(route('attendances.index', $tClass));

        $response->assertOk();
        $response->assertSee('activeStudentId', false);
        $response->assertSee('name-cell-active', false);
        $response->assertSee('handleTableFocusOut', false);
        $response->assertSee('onCellFocus($el, st.id)', false);
        $response->assertSee('onCellTap(st.id, d)', false);
    }

    public function test_attendance_view_replaces_top_mode_hp_banner_with_draggable_fab(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        $response = $this->actingAs($guru)->get(route('attendances.index', $tClass));

        $response->assertOk()
            ->assertDontSee('bg-slate-900 text-slate-200 rounded-2xl px-4 py-3 text-xs space-y-2.5', false)
            ->assertSee('x-ref="modeFab"', false)
            ->assertSee('onFabPointerDown($event)', false)
            ->assertSee('resetFabPosition()', false)
            ->assertSee('selectTapStamp(', false)
            ->assertSee('ag_attendance_fab_pos', false)
            ->assertSee('Mode HP', false);

        $admin = User::query()->create([
            'name' => 'Admin Monitoring',
            'email' => 'admin.monitor@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $adminResponse = $this->actingAs($admin)->get(route('attendances.index', $tClass));
        $adminResponse->assertOk()
            ->assertDontSee('x-ref="modeFab"', false);
    }

    public function test_attendance_monthly_and_recap_tables_use_sticky_freeze_panes_headers(): void
    {
        [$guru, $tClass] = $this->createClassWithFiveStudents();

        $monthlyRes = $this->actingAs($guru)->get(route('attendances.index', $tClass));
        $monthlyRes->assertOk()
            ->assertSee('spreadsheet-scroll spreadsheet-freeze', false)
            ->assertSee('<thead class="sticky top-0 z-20 sticky-thead-shadow">', false)
            ->assertSee('sticky left-0 z-30 bg-slate-100', false)
            ->assertSee('spreadsheet-table-zoomable', false)
            ->assertSee('zoomOut()', false)
            ->assertSee('zoomIn()', false)
            ->assertSee('resetZoom()', false)
            ->assertSee('ag_attendance_zoom', false);

        $recapRes = $this->actingAs($guru)->get(route('attendances.index', [
            'teachingClass' => $tClass->id,
            'tab' => 'recap',
        ]));
        $recapRes->assertOk()
            ->assertSee('spreadsheet-scroll spreadsheet-freeze', false)
            ->assertSee('sticky top-0 z-20 sticky-thead-shadow', false)
            ->assertSee('sticky left-10 z-30 bg-slate-100', false)
            ->assertSee('spreadsheet-table-zoomable sz-no-fixed', false)
            ->assertSee('zoomOut()', false)
            ->assertSee('zoomIn()', false)
            ->assertSee('resetZoom()', false);
    }

    public function test_one_journal_per_day_enforced_on_store_update_and_database_level(): void
    {
        [$guru, $tClass] = $this->createClassWithFiveStudents();

        $otherClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $tClass->academic_year_id,
            'subject_id' => $tClass->subject_id,
            'grade_level' => 'VII',
            'class_name' => 'VII-B',
            'homeroom_teacher_name' => 'Wali Kelas VII-B',
        ]);

        // 1. Jurnal pertama tanggal 2026-11-28 berhasil disimpan
        $firstRes = $this->actingAs($guru)->post(route('journals.store', $tClass), [
            'journal_date' => '2026-11-28',
            'lesson_hours' => '4',
            'activity_material' => 'Materi, latihan, dan tugas konversi desimal ke oktal',
            'notes' => 'Periksa Tugas Desimal ke Biner dan Desimal ke Oktal',
        ]);
        $firstRes->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            DailyJournal::query()
                ->where('teaching_class_id', $tClass->id)
                ->whereDate('journal_date', '2026-11-28')
                ->count()
        );

        // 2. Percobaan menambah jurnal kedua pada tanggal sama di kelas sama ditolak
        $dupStoreRes = $this->actingAs($guru)->post(route('journals.store', $tClass), [
            'journal_date' => '2026-11-28',
            'lesson_hours' => '4',
            'activity_material' => 'a',
        ]);
        $dupStoreRes->assertSessionHasErrors('journal_date');
        $this->assertSame(
            1,
            DailyJournal::query()
                ->where('teaching_class_id', $tClass->id)
                ->whereDate('journal_date', '2026-11-28')
                ->count()
        );

        // 3. Kelas lain (VII-B) tetap boleh membuat jurnal pada tanggal 2026-11-28
        $otherClassRes = $this->actingAs($guru)->post(route('journals.store', $otherClass), [
            'journal_date' => '2026-11-28',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Konversi Bilangan di Kelas VII-B',
        ]);
        $otherClassRes->assertRedirect()->assertSessionHasNoErrors();

        // 4. Mengedit jurnal yang sama di tanggal 2026-11-28 diperbolehkan
        $journal28 = DailyJournal::query()
            ->where('teaching_class_id', $tClass->id)
            ->whereDate('journal_date', '2026-11-28')
            ->firstOrFail();

        $selfUpdateRes = $this->actingAs($guru)->put(route('journals.update', [$tClass, $journal28]), [
            'journal_date' => '2026-11-28',
            'lesson_hours' => '4 - 5',
            'activity_material' => 'Materi konversi desimal ke oktal (diperbarui)',
            'notes' => 'Selesai diperiksa',
        ]);
        $selfUpdateRes->assertRedirect()->assertSessionHasNoErrors();

        // 5. Mengubah tanggal jurnal lain ke 2026-11-28 ditolak
        $this->actingAs($guru)->post(route('journals.store', $tClass), [
            'journal_date' => '2026-11-29',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Pertemuan Lanjutan',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $journal29 = DailyJournal::query()
            ->where('teaching_class_id', $tClass->id)
            ->whereDate('journal_date', '2026-11-29')
            ->firstOrFail();

        $collideUpdateRes = $this->actingAs($guru)->put(route('journals.update', [$tClass, $journal29]), [
            'journal_date' => '2026-11-28',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Pindah ke tanggal terisi',
        ]);
        $collideUpdateRes->assertSessionHasErrors('journal_date');

        // 6. UI & endpoint preview menyertakan info jurnal existing untuk peringatan modal & tombol Edit langsung
        $indexRes = $this->actingAs($guru)->get(route('journals.index', $tClass));
        $indexRes->assertOk()
            ->assertViewHas('existingJournalsByDate', fn (array $map) => isset($map['2026-11-28']) && isset($map['2026-11-29']))
            ->assertSee('duplicateJournalForSelectedDate', false)
            ->assertSee('Buka &amp; Edit Jurnal Ini', false)
            ->assertSee('handleFormSubmit', false);

        $previewRes = $this->actingAs($guru)->getJson(route('journals.attendance-preview', [
            'teachingClass' => $tClass->id,
            'date' => '2026-11-28',
        ]));
        $previewRes->assertOk()
            ->assertJsonPath('existing_journal.id', $journal28->id)
            ->assertJsonPath('existing_journal.journal_date', '2026-11-28');

        // 7. Database level unique constraint menolak sisipan langsung ganda
        $this->expectException(UniqueConstraintViolationException::class);
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-28',
            'lesson_hours' => '4',
            'activity_material' => 'Sisipan ganda langsung',
        ]);
    }

    public function test_enforce_unique_daily_journal_migration_deduplicates_existing_rows(): void
    {
        [$guru, $tClass] = $this->createClassWithFiveStudents();

        $migration = require database_path('migrations/2026_11_28_000001_enforce_unique_daily_journal_per_class_date.php');
        $migration->down();
        $this->assertFalse(Schema::hasIndex('daily_journals', 'uniq_daily_journals_class_date'));

        // Duplikat 1: entri lengkap + entri acak "a" (persis kasus tangkapan layar)
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-28',
            'lesson_hours' => '4',
            'activity_material' => 'Materi, latihan, dan tugas konversi desimal ke oktal',
            'notes' => 'Periksa Tugas Desimal ke Biner dan Desimal ke Oktal',
        ]);
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-28',
            'lesson_hours' => '4',
            'activity_material' => 'a',
            'notes' => null,
        ]);

        // Duplikat 2: dua materi bermakna pada tanggal berbeda -> tergabung aman tanpa kehilangan catatan
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-21',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Sesi Pagi: Penjelasan Konsep',
            'notes' => null,
        ]);
        DailyJournal::query()->create([
            'teaching_class_id' => $tClass->id,
            'journal_date' => '2026-11-21',
            'lesson_hours' => '5 - 6',
            'activity_material' => 'Sesi Siang: Praktik Lab',
            'notes' => 'Tugas dikumpulkan',
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasIndex('daily_journals', 'uniq_daily_journals_class_date'));

        $nov28Rows = DailyJournal::query()
            ->where('teaching_class_id', $tClass->id)
            ->whereDate('journal_date', '2026-11-28')
            ->get();
        $this->assertCount(1, $nov28Rows);
        $this->assertSame('Materi, latihan, dan tugas konversi desimal ke oktal', $nov28Rows->first()->activity_material);
        $this->assertSame('Periksa Tugas Desimal ke Biner dan Desimal ke Oktal', $nov28Rows->first()->notes);

        $nov21Rows = DailyJournal::query()
            ->where('teaching_class_id', $tClass->id)
            ->whereDate('journal_date', '2026-11-21')
            ->get();
        $this->assertCount(1, $nov21Rows);
        $this->assertStringContainsString('Sesi Pagi: Penjelasan Konsep', $nov21Rows->first()->activity_material);
        $this->assertStringContainsString('Sesi Siang: Praktik Lab', $nov21Rows->first()->activity_material);
        $this->assertSame('Tugas dikumpulkan', $nov21Rows->first()->notes);
    }
}
