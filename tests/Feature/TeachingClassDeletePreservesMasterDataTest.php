<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\GradeColumn;
use App\Models\GradeWeight;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingClassDeletePreservesMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guruA;
    private User $guruB;
    private AcademicYear $academicYear;
    private Subject $subjectMat;
    private Subject $subjectIpa;
    private SchoolClass $schoolClass;
    private TeachingClass $tcMat;
    private TeachingClass $tcIpa;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::query()->create([
            'id' => 1,
            'school_name' => 'SMP NEGERI 2 LEMAHABANG',
            'global_default_kktp' => 75,
            'available_grade_levels' => ['VII', 'VIII', 'IX'],
        ]);

        $this->academicYear = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'start_date' => '2026-07-13',
            'end_date' => '2026-12-19',
        ]);

        $this->admin = User::query()->create([
            'name' => 'Administrator Kurikulum',
            'email' => 'admin.test@sekolah.sch.id',
            'nip' => '19800101',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->guruA = User::query()->create([
            'name' => 'Guru Matematika, S.Pd.',
            'email' => 'guru.a@sekolah.sch.id',
            'nip' => '19850101',
            'role' => 'guru',
            'password' => 'guru123',
            'is_active' => true,
        ]);

        $this->guruB = User::query()->create([
            'name' => 'Guru IPA, S.Pd.',
            'email' => 'guru.b@sekolah.sch.id',
            'nip' => '19870202',
            'role' => 'guru',
            'password' => 'guru123',
            'is_active' => true,
        ]);

        $this->subjectMat = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $this->subjectIpa = Subject::query()->create([
            'code' => 'IPA',
            'name' => 'Ilmu Pengetahuan Alam',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        // Master Rombel Kelas VII-A
        $this->schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
            'homeroom_teacher_nip' => '19900101',
        ]);

        // Tambah 5 murid ke Master Rombel
        for ($i = 1; $i <= 5; $i++) {
            ClassStudent::query()->create([
                'school_class_id' => $this->schoolClass->id,
                'order_number' => $i,
                'nis_nisn' => "NISN00{$i}",
                'full_name' => "Murid Contoh {$i}",
                'gender' => $i % 2 === 0 ? 'P' : 'L',
                'is_active' => true,
            ]);
        }

        // Buku Kelas Guru A (Matematika VII-A)
        $this->tcMat = TeachingClass::query()->create([
            'teacher_id' => $this->guruA->id,
            'academic_year_id' => $this->academicYear->id,
            'school_class_id' => $this->schoolClass->id,
            'subject_id' => $this->subjectMat->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);
        $this->tcMat->ensureDefaultsInitialized();

        // Buku Kelas Guru B (IPA VII-A)
        $this->tcIpa = TeachingClass::query()->create([
            'teacher_id' => $this->guruB->id,
            'academic_year_id' => $this->academicYear->id,
            'school_class_id' => $this->schoolClass->id,
            'subject_id' => $this->subjectIpa->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);
        $this->tcIpa->ensureDefaultsInitialized();
    }

    public function test_deleting_teaching_class_removes_book_data_but_preserves_students_and_master_data(): void
    {
        $students = $this->schoolClass->students()->get();
        $this->assertCount(5, $students);

        // Tambahkan Jurnal, Presensi, dan Nilai pada Buku Matematika (Guru A)
        $journalA = DailyJournal::query()->create([
            'teaching_class_id' => $this->tcMat->id,
            'journal_date' => '2026-08-01',
            'lesson_hours' => '1-2',
            'activity_material' => 'Pengenalan Aljabar',
            'present_count' => 5,
            'absent_count' => 0,
        ]);

        Attendance::query()->create([
            'teaching_class_id' => $this->tcMat->id,
            'class_student_id' => $students[0]->id,
            'attendance_date' => '2026-08-01',
            'status' => 'H',
        ]);

        StudentGrade::query()->create([
            'teaching_class_id' => $this->tcMat->id,
            'class_student_id' => $students[0]->id,
            'attendance_score' => 100,
            'sts_score' => 88,
            'sas_score' => 90,
            'final_score' => 89.5,
        ]);

        // Tambahkan Jurnal, Presensi, dan Nilai pada Buku IPA (Guru B)
        $journalB = DailyJournal::query()->create([
            'teaching_class_id' => $this->tcIpa->id,
            'journal_date' => '2026-08-02',
            'lesson_hours' => '3-4',
            'activity_material' => 'Klasifikasi Makhluk Hidup',
            'present_count' => 5,
            'absent_count' => 0,
        ]);

        Attendance::query()->create([
            'teaching_class_id' => $this->tcIpa->id,
            'class_student_id' => $students[0]->id,
            'attendance_date' => '2026-08-02',
            'status' => 'H',
        ]);

        StudentGrade::query()->create([
            'teaching_class_id' => $this->tcIpa->id,
            'class_student_id' => $students[0]->id,
            'attendance_score' => 100,
            'sts_score' => 92,
            'sas_score' => 94,
            'final_score' => 93,
        ]);

        // Guru A menghapus Buku Matematika miliknya
        $response = $this->actingAs($this->guruA)->delete(route('classes.destroy', $this->tcMat));
        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success');

        // 1. Verifikasi Buku Matematika (Guru A) terhapus
        $this->assertDatabaseMissing('teaching_classes', [
            'id' => $this->tcMat->id,
        ]);

        // 2. Verifikasi data internal Buku Matematika terhapus
        $this->assertDatabaseMissing('daily_journals', ['id' => $journalA->id]);
        $this->assertDatabaseMissing('attendances', ['teaching_class_id' => $this->tcMat->id]);
        $this->assertDatabaseMissing('student_grades', ['teaching_class_id' => $this->tcMat->id]);

        // 3. KRUSIAL: Verifikasi Master Rombel VII-A TETAP ADA
        $this->assertDatabaseHas('school_classes', [
            'id' => $this->schoolClass->id,
            'name' => 'VII-A',
        ]);

        // 4. KRUSIAL: Verifikasi SELURUH 5 MURID TETAP UTUH DI MASTER ROMBEL & BASIS DATA
        $this->assertDatabaseCount('class_students', 5);
        $this->assertEquals(5, $this->schoolClass->fresh()->students()->count());

        foreach ($students as $st) {
            $this->assertDatabaseHas('class_students', [
                'id' => $st->id,
                'school_class_id' => $this->schoolClass->id,
                'full_name' => $st->full_name,
            ]);
        }

        // 5. Verifikasi Buku IPA (Guru B) TETAP ADA BESERTA DATANYA
        $this->assertDatabaseHas('teaching_classes', [
            'id' => $this->tcIpa->id,
        ]);
        $this->assertDatabaseHas('daily_journals', ['id' => $journalB->id]);
        $this->assertDatabaseHas('attendances', ['teaching_class_id' => $this->tcIpa->id]);
        $this->assertDatabaseHas('student_grades', ['teaching_class_id' => $this->tcIpa->id]);

        // Guru B tetap melihat seluruh 5 murid di kelas VII-A
        $this->assertEquals(5, $this->tcIpa->fresh()->students()->count());

        // 6. Verifikasi Master Guru & Master Mapel TETAP ADA
        $this->assertDatabaseHas('users', ['id' => $this->guruA->id]);
        $this->assertDatabaseHas('users', ['id' => $this->guruB->id]);
        $this->assertDatabaseHas('subjects', ['id' => $this->subjectMat->id]);
        $this->assertDatabaseHas('subjects', ['id' => $this->subjectIpa->id]);
    }

    public function test_admin_cannot_delete_teacher_book(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('classes.destroy', $this->tcMat));
        $response->assertStatus(403);

        $this->assertDatabaseHas('teaching_classes', [
            'id' => $this->tcMat->id,
        ]);
    }

    public function test_other_teacher_cannot_delete_teacher_book(): void
    {
        $response = $this->actingAs($this->guruB)->delete(route('classes.destroy', $this->tcMat));
        $response->assertStatus(403);

        $this->assertDatabaseHas('teaching_classes', [
            'id' => $this->tcMat->id,
        ]);
    }
}
