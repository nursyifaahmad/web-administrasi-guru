<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use App\Services\AttendanceJournalSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingDaysAttendanceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function createTeachingClassEnvironment(array $teachingDays = [1, 4], bool $onlyTeachingDays = true): array
    {
        $guru = User::query()->create([
            'name' => 'Guru Informatika',
            'email' => 'guru.it@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-E',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-E',
            'homeroom_teacher_nip' => '198001012005011001',
        ]);

        $subject = Subject::query()->create([
            'name' => 'Informatika',
            'code' => 'INF',
            'default_kktp' => 75,
            'order_number' => 1,
            'is_active' => true,
        ]);

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-E',
            'schedule_info' => 'Senin jam 6-7, Kamis jam 2',
            'teaching_days' => $teachingDays,
            'only_teaching_days' => $onlyTeachingDays,
            'show_nickname_column' => true,
            'homeroom_teacher_name' => 'Wali Kelas VII-E',
            'homeroom_teacher_nip' => '198001012005011001',
        ]);

        $teachingClass->ensureDefaultsInitialized();

        ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'teaching_class_id' => $teachingClass->id,
            'order_number' => 1,
            'nis_nisn' => '1001',
            'full_name' => 'Ahmad Santoso',
            'nickname' => 'Ahmad',
            'gender' => 'L',
            'is_active' => true,
        ]);

        ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'teaching_class_id' => $teachingClass->id,
            'order_number' => 2,
            'nis_nisn' => '1002',
            'full_name' => 'Budi Pratama',
            'nickname' => 'Budi',
            'gender' => 'L',
            'is_active' => true,
        ]);

        return compact('guru', 'year', 'schoolClass', 'subject', 'teachingClass');
    }

    public function test_teacher_can_update_teaching_days_and_mode_in_class_settings(): void
    {
        $env = $this->createTeachingClassEnvironment(teachingDays: [1], onlyTeachingDays: false);
        $guru = $env['guru'];
        $cls = $env['teachingClass'];
        $subject = $env['subject'];

        $this->actingAs($guru)->put(route('classes.update', $cls), [
            'subject_id' => $subject->id,
            'schedule_info' => 'Senin jam ke 6-7 & Kamis jam ke 2',
            'teaching_days' => ['1', '4'], // Senin & Kamis
            'only_teaching_days' => 1,
            'show_nickname_column' => 1,
        ])->assertRedirect();

        $cls->refresh();

        $this->assertEquals([1, 4], $cls->teaching_days);
        $this->assertTrue($cls->only_teaching_days);
        $this->assertTrue($cls->isTeachingDay(1)); // Senin
        $this->assertTrue($cls->isTeachingDay(4)); // Kamis
        $this->assertFalse($cls->isTeachingDay(2)); // Selasa
        $this->assertFalse($cls->isTeachingDay(3)); // Rabu
        $this->assertFalse($cls->isTeachingDay(5)); // Jumat
        $this->assertFalse($cls->isTeachingDay(6)); // Sabtu
        $this->assertFalse($cls->isTeachingDay(0)); // Minggu
        $this->assertEquals('Senin & Kamis', $cls->teaching_days_label);
    }

    public function test_build_monthly_matrix_identifies_teaching_days_correctly(): void
    {
        $env = $this->createTeachingClassEnvironment(teachingDays: [1, 4]); // Senin & Kamis
        $cls = $env['teachingClass'];

        /** @var AttendanceJournalSyncService $service */
        $service = app(AttendanceJournalSyncService::class);

        // November 2026:
        // 2026-11-01 is Sunday (dayOfWeek 0)
        // 2026-11-02 is Monday (dayOfWeek 1) -> teaching day!
        // 2026-11-03 is Tuesday (dayOfWeek 2) -> non teaching
        // 2026-11-04 is Wednesday (dayOfWeek 3) -> non teaching
        // 2026-11-05 is Thursday (dayOfWeek 4) -> teaching day!
        $matrix = $service->buildMonthlyMatrix($cls, 2026, 11);

        $this->assertTrue($matrix['has_teaching_days']);
        $this->assertEquals([1, 4], $matrix['teaching_days']);

        $meta = $matrix['days_meta'];
        $this->assertFalse($meta[1]['is_teaching_day']); // Sunday
        $this->assertTrue($meta[2]['is_teaching_day']);  // Monday
        $this->assertFalse($meta[3]['is_teaching_day']); // Tuesday
        $this->assertFalse($meta[4]['is_teaching_day']); // Wednesday
        $this->assertTrue($meta[5]['is_teaching_day']);  // Thursday
    }

    public function test_attendance_web_page_renders_teaching_days_elements(): void
    {
        $env = $this->createTeachingClassEnvironment(teachingDays: [1, 4], onlyTeachingDays: true);
        $guru = $env['guru'];
        $cls = $env['teachingClass'];

        $res = $this->actingAs($guru)->get(route('attendances.index', [
            $cls,
            'tab' => 'monthly',
            'month' => 11,
            'year' => 2026,
        ]));

        $res->assertOk();
        $res->assertSee('hasTeachingDays: true', false);
        $res->assertSee('filterTeachingDays: true', false);
        $res->assertSee('displayedDays.length', false);
        $res->assertSee('Tanggal KBM Saja', false);
    }

    public function test_pdf_and_excel_exports_with_only_teaching_days(): void
    {
        $env = $this->createTeachingClassEnvironment(teachingDays: [1, 4], onlyTeachingDays: true);
        $guru = $env['guru'];
        $cls = $env['teachingClass'];

        // 1. PDF Monthly with only_teaching_days = 1
        $pdfRes = $this->actingAs($guru)->get(route('exports.attendance-monthly.pdf', [
            $cls,
            'month' => 11,
            'year' => 2026,
            'only_teaching_days' => 1,
        ]));
        $pdfRes->assertOk();
        $this->assertEquals('application/pdf', $pdfRes->headers->get('Content-Type'));

        // 2. PDF Monthly with only_teaching_days = 0 (Full 31 days)
        $pdfFullRes = $this->actingAs($guru)->get(route('exports.attendance-monthly.pdf', [
            $cls,
            'month' => 11,
            'year' => 2026,
            'only_teaching_days' => 0,
        ]));
        $pdfFullRes->assertOk();

        // 3. Excel Monthly with only_teaching_days = 1
        $excelRes = $this->actingAs($guru)->get(route('exports.attendance-monthly.excel', [
            $cls,
            'month' => 11,
            'year' => 2026,
            'only_teaching_days' => 1,
        ]));
        $excelRes->assertOk();

        // 4. Bundle PDF and Excel
        $bundlePdfRes = $this->actingAs($guru)->get(route('exports.bundle.pdf', [
            $cls,
            'month' => 11,
            'year' => 2026,
            'only_teaching_days' => 1,
        ]));
        $bundlePdfRes->assertOk();

        $bundleExcelRes = $this->actingAs($guru)->get(route('exports.bundle.excel', [
            $cls,
            'month' => 11,
            'year' => 2026,
            'only_teaching_days' => 1,
        ]));
        $bundleExcelRes->assertOk();
    }
}
