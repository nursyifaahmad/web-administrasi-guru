<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassSeparationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private AcademicYear $academicYear;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::current()->update([
            'school_name' => 'SMP NEGERI 2 LEMAHABANG',
        ]);

        $this->academicYear = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->subject = Subject::query()->create([
            'code' => 'INF',
            'name' => 'Informatika',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Drs. Hendra Wijaya, M.M.Pd.',
            'email' => 'admin@sekolah.sch.id',
            'nip' => '196805121994031005',
            'role' => 'admin',
            'password' => 'admin123',
            'is_active' => true,
        ]);

        $this->guru = User::query()->create([
            'name' => 'Ahmad Nursyifa',
            'email' => 'guru@sekolah.sch.id',
            'nip' => '199501012022011001',
            'role' => 'guru',
            'password' => 'guru123',
            'is_active' => true,
        ]);
    }

    public function test_admin_defaults_to_own_classes_and_does_not_mix_with_other_teachers(): void
    {
        // Ahmad Nursyifa memiliki 3 kelas
        for ($i = 1; $i <= 3; $i++) {
            TeachingClass::query()->create([
                'teacher_id' => $this->guru->id,
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 'VII',
                'class_name' => "VII-Ahmad-{$i}",
                'homeroom_teacher_name' => "Wali {$i}",
            ]);
        }

        // Admin Hendra belum memiliki kelas sama sekali
        $response = $this->actingAs($this->admin)->get(route('classes.index'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('classes'));
        $this->assertSame(0, $response->viewData('myClassesCount'));
        $this->assertSame(3, $response->viewData('allClassesCount'));
        $this->assertSame('my', $response->viewData('effectiveView'));

        // Pesan empty state yang ramah dan opsi supervisi muncul
        $response->assertSee('Belum Ada Buku Kelas Administrasi Anda');
        $response->assertSee('Supervisi Seluruh Guru');
        // Tidak menampilkan kartu kelas milik Ahmad Nursyifa pada tab Kelas Saya
        $response->assertDontSee('Guru:</strong> Ahmad Nursyifa', false);
    }

    public function test_admin_with_own_classes_sees_only_own_classes_by_default(): void
    {
        // 2 Kelas milik Ahmad Nursyifa
        TeachingClass::query()->create([
            'teacher_id' => $this->guru->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-Ahmad',
            'homeroom_teacher_name' => 'Wali 1',
        ]);
        TeachingClass::query()->create([
            'teacher_id' => $this->guru->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-Ahmad',
            'homeroom_teacher_name' => 'Wali 2',
        ]);

        // 1 Kelas milik Admin Hendra
        TeachingClass::query()->create([
            'teacher_id' => $this->admin->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'IX',
            'class_name' => 'IX-Hendra',
            'homeroom_teacher_name' => 'Wali 3',
        ]);

        $response = $this->actingAs($this->admin)->get(route('classes.index'));

        $response->assertOk();
        $classes = $response->viewData('classes');
        $this->assertCount(1, $classes);
        $this->assertSame('IX-Hendra', $classes->first()->class_name);
        $this->assertSame(1, $response->viewData('myClassesCount'));
        $this->assertSame(3, $response->viewData('allClassesCount'));
        // Tidak ada kartu kelas guru lain
        $response->assertDontSee('Guru:</strong> Ahmad Nursyifa', false);
    }

    public function test_admin_can_switch_to_supervision_mode_to_view_all_classes(): void
    {
        TeachingClass::query()->create([
            'teacher_id' => $this->guru->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-Ahmad',
            'homeroom_teacher_name' => 'Wali 1',
        ]);

        TeachingClass::query()->create([
            'teacher_id' => $this->admin->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'IX',
            'class_name' => 'IX-Hendra',
            'homeroom_teacher_name' => 'Wali 2',
        ]);

        $response = $this->actingAs($this->admin)->get(route('classes.index', ['view' => 'all']));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('classes'));
        $this->assertSame('all', $response->viewData('effectiveView'));
        $response->assertSee('Mode Supervisi Administrator: Monitoring Seluruh Kelas');
        $response->assertSee('VII-Ahmad');
        $response->assertSee('IX-Hendra');
    }

    public function test_admin_can_filter_specific_teacher_in_supervision_mode(): void
    {
        TeachingClass::query()->create([
            'teacher_id' => $this->guru->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-Ahmad',
            'homeroom_teacher_name' => 'Wali 1',
        ]);

        TeachingClass::query()->create([
            'teacher_id' => $this->admin->id,
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 'IX',
            'class_name' => 'IX-Hendra',
            'homeroom_teacher_name' => 'Wali 2',
        ]);

        $response = $this->actingAs($this->admin)->get(route('classes.index', ['teacher_id' => $this->guru->id]));

        $response->assertOk();
        $classes = $response->viewData('classes');
        $this->assertCount(1, $classes);
        $this->assertSame('VII-Ahmad', $classes->first()->class_name);
    }

    public function test_admin_can_create_class_for_himself(): void
    {
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Ibu Guru',
        ]);

        $response = $this->actingAs($this->admin)->post(route('classes.store'), [
            'teacher_id' => $this->admin->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $this->subject->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('teaching_classes', [
            'teacher_id' => $this->admin->id,
            'academic_year_id' => $this->academicYear->id,
            'school_class_id' => $schoolClass->id,
            'class_name' => 'VII-A',
        ]);
    }
}
