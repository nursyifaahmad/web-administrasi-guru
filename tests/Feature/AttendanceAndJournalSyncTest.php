<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
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
                'nickname' => "M{$i}",
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

    public function test_invalid_attendance_code_is_rejected_and_manual_journal_override_is_respected(): void
    {
        [$guru, $tClass, $students] = $this->createClassWithFiveStudents();

        $badResp = $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => [
                (string) $students[0]->id => [4 => 'x'],
            ],
        ]);
        $badResp->assertStatus(422);

        // Buat jurnal dengan manual override
        $this->actingAs($guru)->post(route('journals.store', $tClass), [
            'journal_date' => '2026-11-05',
            'lesson_hours' => '3 - 4',
            'activity_material' => 'Diskusi Kelompok',
            'present_count' => 4,
            'absent_count' => 1,
            'is_attendance_overridden' => 1,
        ])->assertRedirect();

        $journal = DailyJournal::query()->whereDate('journal_date', '2026-11-05')->firstOrFail();
        $this->assertTrue($journal->is_attendance_overridden);

        // Simpan absensi semua hadir (5 murid) pada tanggal yang sama -> karena overridden, jurnal tetap 4 hadir
        $allPresent = [];
        foreach ($students as $st) {
            $allPresent[(string) $st->id] = [5 => '.'];
        }
        $this->actingAs($guru)->postJson(route('attendances.bulk-save', $tClass), [
            'year' => 2026,
            'month' => 11,
            'cells' => $allPresent,
        ])->assertOk();

        $journal->refresh();
        $this->assertSame(4, $journal->present_count);

        // Klik tombol Resync -> override direset dan mengikuti data absensi (5 hadir, 0 tidak hadir)
        $this->actingAs($guru)->post(route('journals.resync', [$tClass, $journal]))->assertRedirect();
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
}
