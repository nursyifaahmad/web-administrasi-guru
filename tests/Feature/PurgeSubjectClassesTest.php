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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgeSubjectClassesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $guru;
    protected AcademicYear $year;
    protected Subject $subjectMtk;
    protected Subject $subjectBin;
    protected SchoolClass $schoolClass;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::query()->create([
            'school_name' => 'SMPN 2 Lemahabang',
            'global_default_kktp' => 75,
            'available_grade_levels' => ['VII', 'VIII', 'IX'],
        ]);

        $this->year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@sekolah.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->guru = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->subjectMtk = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $this->subjectBin = Subject::query()->create([
            'code' => 'BIN',
            'name' => 'Bahasa Indonesia',
            'default_kktp' => 76,
            'is_active' => true,
        ]);

        $this->schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $this->year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);
    }

    private function createSampleTeachingClass(Subject $subject): TeachingClass
    {
        $tc = TeachingClass::query()->create([
            'academic_year_id' => $this->year->id,
            'teacher_id' => $this->guru->id,
            'school_class_id' => $this->schoolClass->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);

        $student = ClassStudent::query()->create([
            'school_class_id' => $this->schoolClass->id,
            'teaching_class_id' => $tc->id,
            'full_name' => 'Siswa Contoh ' . uniqid(),
            'nis_nisn' => '12345',
            'gender' => 'L',
            'status' => 'aktif',
        ]);

        GradeWeight::query()->create([
            'teaching_class_id' => $tc->id,
            'weight_ph' => 40,
            'weight_attendance' => 10,
            'weight_nt' => 20,
            'weight_sts' => 15,
            'weight_sas' => 15,
        ]);

        $col = GradeColumn::query()->create([
            'teaching_class_id' => $tc->id,
            'type' => 'PH',
            'label' => 'PH 1',
            'sort_order' => 1,
        ]);

        StudentGrade::query()->create([
            'teaching_class_id' => $tc->id,
            'class_student_id' => $student->id,
            'grade_column_id' => $col->id,
            'score' => 85,
        ]);

        DailyJournal::query()->create([
            'teaching_class_id' => $tc->id,
            'journal_date' => '2026-08-01',
            'lesson_hours' => '1-2',
            'activity_material' => 'Materi Pembelajaran',
        ]);

        Attendance::query()->create([
            'teaching_class_id' => $tc->id,
            'class_student_id' => $student->id,
            'attendance_date' => '2026-08-01',
            'status' => 'H',
        ]);

        return $tc;
    }

    public function test_admin_can_purge_classes_via_web_action(): void
    {
        $this->createSampleTeachingClass($this->subjectMtk);
        $this->createSampleTeachingClass($this->subjectMtk);
        $binClass = $this->createSampleTeachingClass($this->subjectBin);

        $this->assertEquals(2, TeachingClass::query()->where('subject_id', $this->subjectMtk->id)->count());
        $this->assertEquals(1, TeachingClass::query()->where('subject_id', $this->subjectBin->id)->count());

        $initialStudentCount = ClassStudent::query()->count();

        $response = $this->actingAs($this->admin)->post(route('admin.kktp.subjects.purge-classes', $this->subjectMtk), [
            'confirm_text' => 'HAPUS',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Kelas MTK harus 0
        $this->assertEquals(0, TeachingClass::query()->where('subject_id', $this->subjectMtk->id)->count());
        // Kelas BIN harus tetap ada
        $this->assertEquals(1, TeachingClass::query()->where('subject_id', $this->subjectBin->id)->count());

        // Master murid TIDAK TERHAPUS
        $this->assertEquals($initialStudentCount, ClassStudent::query()->count());
    }

    public function test_purge_fails_if_confirmation_text_is_invalid(): void
    {
        $this->createSampleTeachingClass($this->subjectMtk);

        $response = $this->actingAs($this->admin)->post(route('admin.kktp.subjects.purge-classes', $this->subjectMtk), [
            'confirm_text' => 'BUKAN_HAPUS',
        ]);

        $response->assertSessionHasErrors('purge_confirm');
        $this->assertEquals(1, TeachingClass::query()->where('subject_id', $this->subjectMtk->id)->count());
    }

    public function test_non_admin_cannot_purge_subject_classes(): void
    {
        $this->createSampleTeachingClass($this->subjectMtk);

        $response = $this->actingAs($this->guru)->post(route('admin.kktp.subjects.purge-classes', $this->subjectMtk), [
            'confirm_text' => 'HAPUS',
        ]);

        $response->assertForbidden();
        $this->assertEquals(1, TeachingClass::query()->where('subject_id', $this->subjectMtk->id)->count());
    }

    public function test_artisan_command_purges_subject_classes(): void
    {
        $this->createSampleTeachingClass($this->subjectMtk);
        $this->createSampleTeachingClass($this->subjectMtk);

        $this->assertEquals(2, TeachingClass::query()->where('subject_id', $this->subjectMtk->id)->count());

        $exitCode = Artisan::call('classes:purge-subject', [
            'subject_code' => 'MTK',
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals(0, TeachingClass::query()->where('subject_id', $this->subjectMtk->id)->count());
    }

    public function test_admin_kktp_page_displays_purge_button_for_subjects_with_classes(): void
    {
        // 1. Mapel tanpa kelas menampilkan tombol "Hapus"
        $response = $this->actingAs($this->admin)->get(route('admin.kktp.index'));
        $response->assertOk();
        $response->assertSee('Hapus');

        // 2. Buat kelas pada mapel Matematika
        $this->createSampleTeachingClass($this->subjectMtk);

        $responseWithClass = $this->actingAs($this->admin)->get(route('admin.kktp.index'));
        $responseWithClass->assertOk();
        // Memastikan tombol "Bersihkan" muncul untuk mapel yang memiliki kelas aktif
        $responseWithClass->assertSee('Bersihkan');
        $responseWithClass->assertSee('purgeModal');
    }
}
