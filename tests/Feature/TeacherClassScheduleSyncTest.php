<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassScheduleSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private AcademicYear $academicYear;
    private Subject $subject;
    private SchoolClass $schoolClass7A;
    private SchoolClass $schoolClass7B;

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

        $this->schoolClass7A = SchoolClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas 7A, S.Pd.',
            'homeroom_teacher_nip' => '198001012005011001',
        ]);

        $this->schoolClass7B = SchoolClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 'VII',
            'name' => 'VII-B',
            'homeroom_teacher_name' => 'Wali Kelas 7B, S.Pd.',
            'homeroom_teacher_nip' => '198001012005011002',
        ]);

        // Buat jadwal mengajar untuk guru di 7A (Senin jam 1-2) dan 7B (Selasa jam 3-4)
        SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'day_of_week' => 1,
            'shift' => 1,
            'period_order' => 1,
            'time_range' => '07.40 - 08.20',
            'class_name' => '7A',
            'school_class_id' => $this->schoolClass7A->id,
            'teacher_code' => '32',
            'teacher_name' => $this->guru->name,
            'subject_name' => 'Informatika',
            'user_id' => $this->guru->id,
            'subject_id' => $this->subject->id,
        ]);

        SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'day_of_week' => 1,
            'shift' => 1,
            'period_order' => 2,
            'time_range' => '08.20 - 09.00',
            'class_name' => '7A',
            'school_class_id' => $this->schoolClass7A->id,
            'teacher_code' => '32',
            'teacher_name' => $this->guru->name,
            'subject_name' => 'Informatika',
            'user_id' => $this->guru->id,
            'subject_id' => $this->subject->id,
        ]);

        SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'day_of_week' => 2,
            'shift' => 1,
            'period_order' => 3,
            'time_range' => '09.30 - 10.10',
            'class_name' => '7B',
            'school_class_id' => $this->schoolClass7B->id,
            'teacher_code' => '32',
            'teacher_name' => $this->guru->name,
            'subject_name' => 'Informatika',
            'user_id' => $this->guru->id,
            'subject_id' => $this->subject->id,
        ]);
    }

    public function test_teacher_schedule_allocations_are_calculated_accurately(): void
    {
        $service = app(SchoolScheduleService::class);
        $allocations = $service->getTeacherClassAllocations($this->academicYear, $this->guru);

        $this->assertCount(2, $allocations);

        $alloc7A = $allocations->firstWhere('class_code', '7A');
        $this->assertNotNull($alloc7A);
        $this->assertSame('VII-A', $alloc7A['class_name']);
        $this->assertSame('Informatika', $alloc7A['subject_name']);
        $this->assertSame(2, $alloc7A['total_jp']);
        $this->assertSame([1], $alloc7A['teaching_days']);
        $this->assertFalse($alloc7A['already_created']);
        $this->assertStringContainsString('Senin', $alloc7A['schedule_info']);

        $alloc7B = $allocations->firstWhere('class_code', '7B');
        $this->assertNotNull($alloc7B);
        $this->assertSame(1, $alloc7B['total_jp']);
        $this->assertSame([2], $alloc7B['teaching_days']);
        $this->assertFalse($alloc7B['already_created']);
    }

    public function test_teacher_can_fetch_schedule_allocations_via_ajax(): void
    {
        $response = $this->actingAs($this->guru)->getJson(route('classes.schedule-allocations'));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'teacher_id' => $this->guru->id,
                'total_allocations' => 2,
                'pending_count' => 2,
                'created_count' => 0,
            ]);
    }

    public function test_class_creation_locks_and_saves_schedule_info_and_teaching_days(): void
    {
        $response = $this->actingAs($this->guru)->post(route('classes.store'), [
            'school_class_id' => $this->schoolClass7A->id,
            'subject_id' => $this->subject->id,
            'schedule_info' => 'Senin Jam 1-2 (07.40 - 09.00)',
            'teaching_days' => [1],
        ]);

        $response->assertRedirect(route('classes.index'));

        $class = TeachingClass::query()
            ->where('teacher_id', $this->guru->id)
            ->where('school_class_id', $this->schoolClass7A->id)
            ->firstOrFail();

        $this->assertSame('Senin Jam 1-2 (07.40 - 09.00)', $class->schedule_info);
        $this->assertSame([1], $class->teaching_days);
        $this->assertTrue($class->only_teaching_days);

        // Setelah dibuat, status alokasi menjadi already_created = true
        $service = app(SchoolScheduleService::class);
        $allocations = $service->getTeacherClassAllocations($this->academicYear, $this->guru);
        $alloc7A = $allocations->firstWhere('class_code', '7A');
        $this->assertTrue($alloc7A['already_created']);
    }

    public function test_duplicate_class_creation_is_prevented(): void
    {
        // Pembuatan kelas pertama
        $this->actingAs($this->guru)->post(route('classes.store'), [
            'school_class_id' => $this->schoolClass7A->id,
            'subject_id' => $this->subject->id,
            'schedule_info' => 'Senin Jam 1-2',
            'teaching_days' => [1],
        ])->assertRedirect();

        $this->assertSame(1, TeachingClass::query()->where('teacher_id', $this->guru->id)->count());

        // Guru mencoba membuat kelas yang sama untuk kedua kalinya
        $responseDuplicate = $this->actingAs($this->guru)->post(route('classes.store'), [
            'school_class_id' => $this->schoolClass7A->id,
            'subject_id' => $this->subject->id,
            'schedule_info' => 'Senin Jam 1-2',
            'teaching_days' => [1],
        ]);

        $responseDuplicate->assertRedirect(route('classes.index'));
        $responseDuplicate->assertSessionHas('warning');

        // Jumlah kelas tetap 1, tidak terjadi duplikasi
        $this->assertSame(1, TeachingClass::query()->where('teacher_id', $this->guru->id)->count());
    }

    public function test_one_click_sync_from_schedule_creates_all_pending_classes(): void
    {
        $this->assertSame(0, TeachingClass::query()->where('teacher_id', $this->guru->id)->count());

        $response = $this->actingAs($this->guru)->post(route('classes.sync-from-schedule'), [
            'teacher_id' => $this->guru->id,
        ]);

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success');

        // Kedua kelas (7A dan 7B) langsung terbuat otomatis
        $classes = TeachingClass::query()->where('teacher_id', $this->guru->id)->get();
        $this->assertCount(2, $classes);
        $this->assertTrue($classes->contains('class_name', 'VII-A'));
        $this->assertTrue($classes->contains('class_name', 'VII-B'));
    }

    public function test_admin_can_fetch_allocations_for_other_teachers(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('classes.schedule-allocations', [
            'teacher_id' => $this->guru->id,
        ]));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'teacher_id' => $this->guru->id,
                'total_allocations' => 2,
            ]);
    }
}
