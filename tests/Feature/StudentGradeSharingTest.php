<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\GradeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentGradeSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function createFixtureClass(): array
    {
        SchoolSetting::current();

        $admin = User::query()->create([
            'name' => 'Admin Wakasek',
            'email' => 'admin.share@test.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Ibu Siti Aminah, S.Pd.',
            'email' => 'guru.share@test.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $otherGuru = User::query()->create([
            'name' => 'Pak Budi Santoso, S.Pd.',
            'email' => 'guru.lain@test.sch.id',
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
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);
        $teachingClass->ensureDefaultsInitialized();

        $student1 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'nis_nisn' => '2425001 / 0098120001',
            'full_name' => 'Ahmad Fauzan Pratama',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $student2 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 2,
            'nis_nisn' => '0098120002',
            'full_name' => 'Siti Rahmawati Putri',
            'gender' => 'P',
            'is_active' => true,
        ]);

        $student3MissingNis = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 3,
            'nis_nisn' => null,
            'full_name' => 'Murid Belum Ada NISN',
            'gender' => 'L',
            'is_active' => true,
        ]);

        // Isi nilai spesifik agar mudah memastikan isolasi antar murid
        $phCols = $teachingClass->phColumns()->orderBy('sort_order')->get();
        $ntCols = $teachingClass->ntColumns()->orderBy('sort_order')->get();
        $ph1 = $phCols[0];
        $ph2 = $phCols[1];
        $nt1 = $ntCols[0];
        $nt2 = $ntCols[1];

        $ph1->update(['topic_note' => 'Bilangan Bulat', 'assessment_date' => '2026-11-10']);

        StudentGrade::query()->create([
            'teaching_class_id' => $teachingClass->id,
            'class_student_id' => $student1->id,
            'attendance_score' => 95,
            'sts_score' => 88,
            'sas_score' => 91,
            'dynamic_scores' => [
                (string) $ph1->id => 86,
                (string) $ph2->id => null, // Belum dinilai
                (string) $nt1->id => 90,
                (string) $nt2->id => 84,
            ],
        ]);

        StudentGrade::query()->create([
            'teaching_class_id' => $teachingClass->id,
            'class_student_id' => $student2->id,
            'attendance_score' => 60,
            'sts_score' => 42,
            'sas_score' => 37,
            'dynamic_scores' => [
                (string) $ph1->id => 49,
                (string) $ph2->id => 51,
                (string) $nt1->id => 44,
                (string) $nt2->id => 46,
            ],
        ]);

        Attendance::query()->create([
            'teaching_class_id' => $teachingClass->id,
            'class_student_id' => $student1->id,
            'attendance_date' => '2026-11-10',
            'status' => '.',
        ]);

        app(GradeCalculationService::class)->recalculateClass($teachingClass);

        return compact('admin', 'guru', 'otherGuru', 'year', 'subject', 'schoolClass', 'teachingClass', 'student1', 'student2', 'student3MissingNis');
    }

    public function test_teacher_can_toggle_student_sharing_and_manage_unique_slug_with_strict_authorization(): void
    {
        $f = $this->createFixtureClass();
        $tc = $f['teachingClass'];

        $this->assertFalse((bool) $tc->is_grade_shared_with_students);
        $this->assertNull($tc->student_share_slug);

        // Guru lain & Admin bukan pemilik kelas ditolak
        $this->actingAs($f['otherGuru'])
            ->patchJson(route('classes.toggle-student-sharing', $tc))
            ->assertForbidden();

        $this->actingAs($f['admin'])
            ->patchJson(route('classes.toggle-student-sharing', $tc))
            ->assertForbidden();

        // Guru pengampu mengaktifkan sharing (ON) dengan Anti-Screenshot via JSON
        $onRes = $this->actingAs($f['guru'])
            ->patchJson(route('classes.toggle-student-sharing', $tc), ['anti_screenshot' => true]);

        $onRes->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('is_grade_shared_with_students', true)
            ->assertJsonPath('is_student_share_anti_screenshot', true)
            ->assertJsonPath('missing_nis_count', 1);

        $tc->refresh();
        $firstSlug = $tc->student_share_slug;
        $this->assertNotEmpty($firstSlug);
        $this->assertTrue((bool) $tc->is_student_share_anti_screenshot);
        $this->assertStringStartsWith('vii-a-mtk-ganjil-', $firstSlug);
        $this->assertNotNull($tc->student_share_enabled_at);

        // Halaman /kelas & Menu 3 Nilai menampilkan kontrol sharing, modal konfirmasi & peringatan murid tanpa NISN
        $classesView = $this->actingAs($f['guru'])->get(route('classes.index'));
        $classesView->assertOk()
            ->assertSee('Sharing Nilai Murid', false)
            ->assertSee($firstSlug, false)
            ->assertSee('toggleStudentSharing', false)
            ->assertSee('Aktifkan dengan Anti-Screenshot', false)
            ->assertSee('Aktifkan Tanpa Anti-Screenshot (Mode Normal)', false);

        $gradesView = $this->actingAs($f['guru'])->get(route('grades.index', $tc));
        $gradesView->assertOk()
            ->assertSee('Sharing Nilai Mandiri untuk Murid (Link Slug Unik)', false)
            ->assertSee($firstSlug, false)
            ->assertSee('Aktifkan dengan Anti-Screenshot', false)
            ->assertSee('Aktifkan Tanpa Anti-Screenshot (Mode Normal)', false)
            ->assertSee('1</strong> peserta didik aktif', false);

        // Regenerasi link slug memutus slug lama
        $regenRes = $this->actingAs($f['guru'])
            ->postJson(route('classes.regenerate-student-slug', $tc));

        $regenRes->assertOk()
            ->assertJsonPath('status', 'ok');

        $tc->refresh();
        $secondSlug = $tc->student_share_slug;
        $this->assertNotSame($firstSlug, $secondSlug);

        $this->get(route('student-grades.show', $firstSlug))->assertNotFound();
        $this->get(route('student-grades.show', $secondSlug))->assertOk();

        // Matikan kembali (OFF) -> slug tetap tersimpan, akses publik ditolak 403
        $this->actingAs($f['guru'])
            ->patch(route('classes.toggle-student-sharing', $tc))
            ->assertRedirect();

        $tc->refresh();
        $this->assertFalse((bool) $tc->is_grade_shared_with_students);
        $this->assertSame($secondSlug, $tc->student_share_slug);

        $closedRes = $this->get(route('student-grades.show', $secondSlug));
        $closedRes->assertForbidden()
            ->assertSee('Akses Lihat Nilai Ditutup Sementara', false);
        $this->assertStringContainsString('no-store', (string) $closedRes->headers->get('Cache-Control'));
    }

    public function test_student_verifies_via_nis_nisn_sees_only_own_grades_with_anti_screenshot_shield(): void
    {
        $f = $this->createFixtureClass();
        $tc = $f['teachingClass'];

        $this->actingAs($f['guru'])
            ->patch(route('classes.toggle-student-sharing', $tc))
            ->assertRedirect();

        $tc->refresh();
        $slug = $tc->student_share_slug;

        // 1. Belum verifikasi -> tampil gerbang NIS/NISN, tanpa data siswa mana pun
        $gateRes = $this->get(route('student-grades.show', $slug));
        $gateRes->assertOk()
            ->assertSee('Verifikasi Identitas Siswa', false)
            ->assertDontSee('Ahmad Fauzan Pratama', false)
            ->assertDontSee('Siti Rahmawati Putri', false);

        // 2. NIS/NISN salah ditolak dengan pesan error
        $this->from(route('student-grades.show', $slug))
            ->post(route('student-grades.verify', $slug), ['nis_nisn' => '9999999999'])
            ->assertRedirect(route('student-grades.show', $slug))
            ->assertSessionHasErrors('nis_nisn');

        // 3. Siswa 1 memverifikasi dengan token NISN saja beserta spasi & tanda hubung
        $this->post(route('student-grades.verify', $slug), ['nis_nisn' => '0098-1200 01'])
            ->assertRedirect(route('student-grades.show', $slug));

        $reportRes = $this->get(route('student-grades.show', $slug));
        $reportRes->assertOk();

        // Header anti-cache & anti-index
        $this->assertStringContainsString('no-store', (string) $reportRes->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $reportRes->headers->get('X-Robots-Tag'));

        // Hanya menampilkan nama & rincian nilai Siswa 1, tidak membocorkan Siswa 2
        $reportRes->assertSee('Ahmad Fauzan Pratama', false)
            ->assertSee('2425001 / 0098120001', false)
            ->assertSee('Bilangan Bulat', false)
            ->assertSee('Belum Dinilai', false)
            ->assertSee('88', false)
            ->assertSee('91', false)
            ->assertDontSee('Siti Rahmawati Putri', false)
            ->assertDontSee('0098120002', false);

        // Elemen proteksi anti-screenshot maksimal ter-render
        $reportRes->assertSee('Tekan &amp; Tahan untuk Lihat Nilai', false)
            ->assertSee('Intip 8 Detik', false)
            ->assertSee('studentGradeShield(', false)
            ->assertSee('visibilitychange', false)
            ->assertSee('PrintScreen', false)
            ->assertSee('@media print', false);

        // 4. Keluar / tutup sesi murid -> kembali ke gerbang verifikasi
        $this->post(route('student-grades.logout', $slug))
            ->assertRedirect(route('student-grades.show', $slug));

        $afterLogout = $this->get(route('student-grades.show', $slug));
        $afterLogout->assertOk()
            ->assertSee('Verifikasi Identitas Siswa', false)
            ->assertDontSee('Tekan &amp; Tahan untuk Lihat Nilai', false);
    }

    public function test_duplicate_nis_nisn_in_class_is_safely_rejected_as_ambiguous(): void
    {
        $f = $this->createFixtureClass();
        $tc = $f['teachingClass'];

        // Simulasikan ada 2 murid aktif memiliki NISN sama di Rombel yang sama
        ClassStudent::query()->create([
            'school_class_id' => $f['schoolClass']->id,
            'order_number' => 4,
            'nis_nisn' => '0098120002',
            'full_name' => 'Siswa Duplikat NISN',
            'gender' => 'P',
            'is_active' => true,
        ]);

        $this->actingAs($f['guru'])
            ->patch(route('classes.toggle-student-sharing', $tc));
        $tc->refresh();

        $this->from(route('student-grades.show', $tc->student_share_slug))
            ->post(route('student-grades.verify', $tc->student_share_slug), ['nis_nisn' => '0098120002'])
            ->assertRedirect(route('student-grades.show', $tc->student_share_slug))
            ->assertSessionHasErrors('nis_nisn');

        $this->get(route('student-grades.show', $tc->student_share_slug))
            ->assertSee('Data NIS/NISN terdeteksi ganda', false)
            ->assertDontSee('Siti Rahmawati Putri', false)
            ->assertDontSee('Siswa Duplikat NISN', false);
    }

    public function test_student_grade_lookup_is_rate_limited(): void
    {
        $f = $this->createFixtureClass();
        $tc = $f['teachingClass'];

        $this->actingAs($f['guru'])
            ->patch(route('classes.toggle-student-sharing', $tc));
        $tc->refresh();
        $slug = $tc->student_share_slug;

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('student-grades.verify', $slug), ['nis_nisn' => '0000000000'])
                ->assertRedirect();
        }

        $this->post(route('student-grades.verify', $slug), ['nis_nisn' => '0000000000'])
            ->assertStatus(429);
    }

    public function test_teacher_can_enable_student_sharing_without_anti_screenshot_and_student_sees_clean_mode(): void
    {
        $f = $this->createFixtureClass();
        $tc = $f['teachingClass'];

        // 1. Guru mengaktifkan sharing tanpa fitur Anti-Screenshot (Mode Normal)
        $onNormal = $this->actingAs($f['guru'])
            ->patchJson(route('classes.toggle-student-sharing', $tc), ['anti_screenshot' => false]);

        $onNormal->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('is_grade_shared_with_students', true)
            ->assertJsonPath('is_student_share_anti_screenshot', false);

        $tc->refresh();
        $this->assertFalse((bool) $tc->is_student_share_anti_screenshot);
        $slug = $tc->student_share_slug;

        // Portal Guru (/kelas & Menu 3 Nilai) menampilkan badge Mode Normal
        $this->actingAs($f['guru'])->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Mode Normal', false);

        $this->actingAs($f['guru'])->get(route('grades.index', $tc))
            ->assertOk()
            ->assertSee('Mode Normal (Boleh Screenshot)', false);

        // Admin monitoring tetap tidak melihat tombol/modal pengaktifan sharing murid kelas guru lain
        $this->actingAs($f['admin'])->get(route('grades.index', $tc))
            ->assertOk()
            ->assertDontSee('Sharing Nilai Mandiri untuk Murid', false);

        // 2. Halaman gerbang murid tetap mewajibkan verifikasi NIS/NISN tanpa teks Anti-Screenshot
        $this->get(route('student-grades.show', $slug))
            ->assertOk()
            ->assertSee('Verifikasi Identitas Siswa', false)
            ->assertDontSee('Mode Privasi Anti-Screenshot', false);

        // 3. Siswa 1 login dengan NISN -> nilai langsung terlihat jelas, tetap terisolasi, tanpa anti-screenshot
        $this->post(route('student-grades.verify', $slug), ['nis_nisn' => '0098120001'])
            ->assertRedirect(route('student-grades.show', $slug));

        $normalView = $this->get(route('student-grades.show', $slug));
        $normalView->assertOk();
        $this->assertStringContainsString('no-store', (string) $normalView->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $normalView->headers->get('X-Robots-Tag'));

        $normalView->assertSee('Mode Tampilan Normal (Boleh Screenshot)', false)
            ->assertSee('Ahmad Fauzan Pratama', false)
            ->assertSee('88', false)
            ->assertSee('91', false)
            ->assertDontSee('Siti Rahmawati Putri', false)
            ->assertDontSee('0098120002', false)
            ->assertDontSee('studentGradeShield(', false)
            ->assertDontSee('Tekan &amp; Tahan untuk Lihat Nilai', false)
            ->assertDontSee('Intip 8 Detik', false)
            ->assertDontSee('visibilitychange', false)
            ->assertDontSee('@media print', false)
            ->assertDontSee('blur-md', false);

        // 4. Matikan (OFF) lalu nyalakan kembali (ON) dengan Anti-Screenshot -> kembali terproteksi
        $this->actingAs($f['guru'])
            ->patchJson(route('classes.toggle-student-sharing', $tc))
            ->assertOk()
            ->assertJsonPath('is_grade_shared_with_students', false);

        $this->actingAs($f['guru'])
            ->patchJson(route('classes.toggle-student-sharing', $tc), ['anti_screenshot' => true])
            ->assertOk()
            ->assertJsonPath('is_grade_shared_with_students', true)
            ->assertJsonPath('is_student_share_anti_screenshot', true);

        // Siswa login ulang ke link aktif -> melihat Mode Anti-Screenshot aktif kembali
        $this->post(route('student-grades.verify', $slug), ['nis_nisn' => '0098120001'])
            ->assertRedirect(route('student-grades.show', $slug));

        $this->get(route('student-grades.show', $slug))
            ->assertOk()
            ->assertSee('studentGradeShield(', false)
            ->assertSee('Tekan &amp; Tahan untuk Lihat Nilai', false);
    }
}
