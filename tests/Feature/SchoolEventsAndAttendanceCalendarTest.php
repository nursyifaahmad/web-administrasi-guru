<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\AttendanceJournalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolEventsAndAttendanceCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_school_events_and_holidays(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Pengampu',
            'email' => 'guru@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        // 1. Admin membuka halaman kalender
        $this->actingAs($admin)->get(route('admin.events.index'))->assertOk();

        // 2. Guru biasa dilarang mengakses manajemen kalender admin
        $this->actingAs($guru)->get(route('admin.events.index'))->assertStatus(403);

        // 3. Admin menambahkan hari libur nasional (HUT RI)
        $this->actingAs($admin)->post(route('admin.events.store'), [
            'academic_year_id' => $year->id,
            'event_date' => '2026-08-17',
            'title' => 'HUT Kemerdekaan RI ke-81',
            'type' => 'libur_nasional',
            'is_holiday' => 1,
            'description' => 'Upacara bendera & libur nasional',
        ])->assertRedirect();

        $this->assertDatabaseHas('school_events', [
            'academic_year_id' => $year->id,
            'title' => 'HUT Kemerdekaan RI ke-81',
            'type' => 'libur_nasional',
            'is_holiday' => true,
        ]);

        // 4. Admin menambahkan kegiatan sekolah multi-hari (Classmeeting)
        $this->actingAs($admin)->post(route('admin.events.store'), [
            'academic_year_id' => $year->id,
            'event_date' => '2026-09-14',
            'event_end_date' => '2026-09-18',
            'title' => 'Classmeeting & Porseni',
            'type' => 'kegiatan_sekolah',
            'is_holiday' => 1,
        ])->assertRedirect();

        $cm = SchoolEvent::query()->where('title', 'Classmeeting & Porseni')->firstOrFail();
        $this->assertTrue($cm->coversDate('2026-09-16'));
        $this->assertFalse($cm->coversDate('2026-09-20'));

        // 5. Admin menghapus agenda
        $this->actingAs($admin)->delete(route('admin.events.destroy', $cm))->assertRedirect();
        $this->assertDatabaseMissing('school_events', ['id' => $cm->id]);
    }

    public function test_attendance_matrix_identifies_sundays_and_holidays_without_skewing_attendance(): void
    {
        $guru = User::query()->create([
            'name' => 'Guru Matematika',
            'email' => 'guru.mtk@sekolah.sch.id',
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

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas',
        ]);

        $student = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'nis_nisn' => '1001',
            'full_name' => 'Budi Santoso',
            'nickname' => 'Budi',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'school_class_id' => $schoolClass->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'class_name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas',
        ]);

        // Buat event 17 Agustus 2026
        SchoolEvent::query()->create([
            'academic_year_id' => $year->id,
            'event_date' => '2026-08-17',
            'title' => 'HUT Kemerdekaan RI',
            'type' => 'libur_nasional',
            'is_holiday' => true,
        ]);

        $syncService = app(AttendanceJournalSyncService::class);
        $matrix = $syncService->buildMonthlyMatrix($teachingClass, 2026, 8);

        // Verifikasi hari Minggu di bulan Agustus 2026: tgl 2, 9, 16, 23, 30
        $meta2 = $matrix['days_meta'][2];
        $this->assertTrue($meta2['is_sunday']);
        $this->assertTrue($meta2['is_holiday']);
        $this->assertSame('Min', $meta2['day_name']);

        // Verifikasi tgl 3 Agustus 2026 adalah Senin (hari biasa)
        $meta3 = $matrix['days_meta'][3];
        $this->assertFalse($meta3['is_sunday']);
        $this->assertFalse($meta3['is_holiday']);
        $this->assertSame('Sen', $meta3['day_name']);

        // Verifikasi tgl 17 Agustus 2026 adalah Hari Libur HUT RI
        $meta17 = $matrix['days_meta'][17];
        $this->assertTrue($meta17['is_holiday']);
        $this->assertTrue($meta17['is_event']);
        $this->assertSame('HUT Kemerdekaan RI', $meta17['event_title']);

        // Simpan absensi: Budi hadir pada Senin 3 & 10 Agustus. Hari Minggu & tgl 17 dibiarkan kosong.
        $this->actingAs($guru)->post(route('attendances.bulk-save', $teachingClass), [
            'year' => 2026,
            'month' => 8,
            'cells' => [
                $student->id => [
                    3 => '.',
                    10 => '.',
                ],
            ],
        ])->assertOk();

        // Hitung rekap kehadiran
        $recap = $syncService->buildSemesterRecap($teachingClass);
        $rowBudi = $recap['rows'][0];

        // Karena hari Minggu & 17 Agt kosong, total pertemuan dihitung hanya 2 kali dan Budi hadir 100%
        $this->assertSame(2, $rowBudi['total_meetings']);
        $this->assertSame(2, $rowBudi['present']);
        $this->assertSame(0, $rowBudi['absent']);
        $this->assertSame(100.0, $rowBudi['percentage']);

        // Web View, PDF, dan Excel render dengan sukses
        $this->actingAs($guru)->get(route('attendances.index', [$teachingClass, 'month' => 8, 'year' => 2026]))
            ->assertOk();

        $this->actingAs($guru)->get(route('exports.attendance-monthly.pdf', [$teachingClass, 'month' => 8, 'year' => 2026]))
            ->assertOk();

        $this->actingAs($guru)->get(route('exports.attendance-monthly.excel', [$teachingClass, 'month' => 8, 'year' => 2026]))
            ->assertOk();
    }
}
