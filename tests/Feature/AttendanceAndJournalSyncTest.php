<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\AttendanceJournalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Simpan Nilai (Ctrl+S)', false)
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

        // Buat guru jadwal dengan kode 32
        $schedTeacher = SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $year->id,
            'code' => '32',
            'name' => $guru->name,
            'subject' => 'Matematika',
            'user_id' => $guru->id,
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
}
