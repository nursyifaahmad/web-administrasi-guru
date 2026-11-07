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
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAsTeacherFullCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $academicYear;
    protected User $adminTeacher; // Contoh: Muhammad Irfan (role: admin, mengampu B. Inggris)
    protected User $regularTeacher; // Contoh: Budi Santoso (role: guru)
    protected Subject $subjectEnglish;
    protected Subject $subjectMath;
    protected SchoolClass $schoolClass;
    protected TeachingClass $adminClass;
    protected TeachingClass $regularClass;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::query()->create([
            'school_name' => 'SMP Negeri 2 Lemahabang',
            'grade_levels' => ['VII', 'VIII', 'IX'],
        ]);

        $this->academicYear = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        // Muhammad Irfan: Guru Bahasa Inggris sekaligus Admin / Wakasek
        $this->adminTeacher = User::query()->create([
            'name' => 'Muhammad Irfan, S.Pd.',
            'email' => 'irfan@sekolah.sch.id',
            'nip' => '19880101',
            'role' => 'admin',
            'password' => 'guru123',
            'is_active' => true,
        ]);

        // Guru reguler lain (Budi Santoso)
        $this->regularTeacher = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi@sekolah.sch.id',
            'nip' => '19850202',
            'role' => 'guru',
            'password' => 'guru123',
            'is_active' => true,
        ]);

        $this->subjectEnglish = Subject::query()->create([
            'code' => 'ING',
            'name' => 'Bahasa Inggris',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        $this->subjectMath = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        // Master Rombel VII-A
        $this->schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
            'homeroom_teacher_nip' => '19900101',
        ]);

        // Buku Kelas milik Muhammad Irfan (Bahasa Inggris VII-A)
        $this->adminClass = TeachingClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'school_class_id' => $this->schoolClass->id,
            'teacher_id' => $this->adminTeacher->id,
            'subject_id' => $this->subjectEnglish->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);
        $this->adminClass->ensureDefaultsInitialized();

        // Buku Kelas milik Budi Santoso (Matematika VII-A)
        $this->regularClass = TeachingClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'school_class_id' => $this->schoolClass->id,
            'teacher_id' => $this->regularTeacher->id,
            'subject_id' => $this->subjectMath->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);
        $this->regularClass->ensureDefaultsInitialized();

        // 3 Siswa di Rombel VII-A
        foreach (range(1, 3) as $i) {
            ClassStudent::query()->create([
                'school_class_id' => $this->schoolClass->id,
                'order_number' => $i,
                'nis_nisn' => 'NIS00' . $i,
                'full_name' => 'Siswa ' . $i,
                'gender' => $i % 2 === 0 ? 'P' : 'L',
                'is_active' => true,
            ]);
        }
    }

    public function test_admin_teacher_can_delete_their_own_teaching_class_and_preserves_master_students(): void
    {
        // Muhammad Irfan menghapus buku Bahasa Inggris miliknya
        $response = $this->actingAs($this->adminTeacher)->delete(route('classes.destroy', $this->adminClass));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Buku kelas Bahasa Inggris terhapus
        $this->assertDatabaseMissing('teaching_classes', [
            'id' => $this->adminClass->id,
        ]);

        // Master Rombel & Seluruh 3 Murid TETAP UTUH
        $this->assertDatabaseHas('school_classes', [
            'id' => $this->schoolClass->id,
        ]);
        $this->assertDatabaseCount('class_students', 3);

        // Buku Matematika milik Budi Santoso tetap aman
        $this->assertDatabaseHas('teaching_classes', [
            'id' => $this->regularClass->id,
        ]);
    }

    public function test_admin_cannot_delete_another_teachers_book(): void
    {
        // Muhammad Irfan (admin) mencoba menghapus buku Matematika milik Budi Santoso
        $response = $this->actingAs($this->adminTeacher)->delete(route('classes.destroy', $this->regularClass));

        $response->assertStatus(403);

        // Buku Matematika tidak terhapus
        $this->assertDatabaseHas('teaching_classes', [
            'id' => $this->regularClass->id,
        ]);
    }

    public function test_admin_teacher_can_store_update_and_destroy_daily_journal_in_their_own_class(): void
    {
        // 1. Store Journal
        $response = $this->actingAs($this->adminTeacher)->post(route('journals.store', $this->adminClass), [
            'journal_date' => '2026-08-10',
            'lesson_hours' => '1-2',
            'activity_material' => 'Introduction and Greetings',
            'notes' => 'Catatan KBM Irfan',
            'signature_mode' => 'empty',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $journal = DailyJournal::query()->where('teaching_class_id', $this->adminClass->id)->first();
        $this->assertNotNull($journal);
        $this->assertEquals('Introduction and Greetings', $journal->activity_material);

        // 2. Update Journal
        $updateResp = $this->actingAs($this->adminTeacher)->put(route('journals.update', [$this->adminClass, $journal]), [
            'journal_date' => '2026-08-10',
            'lesson_hours' => '1-3',
            'activity_material' => 'Introduction and Greetings (Updated)',
            'notes' => 'Diperbarui',
            'signature_mode' => 'empty',
        ]);
        $updateResp->assertRedirect();
        $this->assertEquals('Introduction and Greetings (Updated)', $journal->fresh()->activity_material);

        // 3. Destroy Journal
        $deleteResp = $this->actingAs($this->adminTeacher)->delete(route('journals.destroy', [$this->adminClass, $journal]));
        $deleteResp->assertRedirect();
        $this->assertDatabaseMissing('daily_journals', ['id' => $journal->id]);
    }

    public function test_admin_cannot_store_or_modify_daily_journal_in_another_teachers_class(): void
    {
        // Muhammad Irfan mencoba menambah jurnal di kelas Budi Santoso
        $response = $this->actingAs($this->adminTeacher)->post(route('journals.store', $this->regularClass), [
            'journal_date' => '2026-08-10',
            'lesson_hours' => '1-2',
            'activity_material' => 'Illegal entry by admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_teacher_can_save_attendance_in_their_own_class(): void
    {
        $student = $this->adminClass->activeStudents()->first();
        $this->assertNotNull($student);

        $response = $this->actingAs($this->adminTeacher)->postJson(route('attendances.bulk-save', $this->adminClass), [
            'year' => 2026,
            'month' => 8,
            'cells' => [
                $student->id => [
                    10 => '.', // Hadir pada tanggal 10
                    11 => 's', // Sakit pada tanggal 11
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('attendances', [
            'teaching_class_id' => $this->adminClass->id,
            'class_student_id' => $student->id,
            'attendance_date' => '2026-08-10',
            'status' => '.',
        ]);
    }

    public function test_admin_cannot_save_attendance_in_another_teachers_class(): void
    {
        $student = $this->regularClass->activeStudents()->first();

        $response = $this->actingAs($this->adminTeacher)->postJson(route('attendances.bulk-save', $this->regularClass), [
            'year' => 2026,
            'month' => 8,
            'cells' => [
                $student->id => [
                    10 => '.',
                ],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_teacher_can_manage_grades_weights_and_columns_in_their_own_class(): void
    {
        // 1. Tambah Kolom PH
        $colResp = $this->actingAs($this->adminTeacher)->post(route('grades.columns.store', $this->adminClass), [
            'type' => 'PH',
            'label' => 'PH 1 Reading',
            'topic_note' => 'Chapter 1',
        ]);
        $colResp->assertRedirect();
        $this->assertDatabaseHas('grade_columns', [
            'teaching_class_id' => $this->adminClass->id,
            'label' => 'PH 1 Reading',
        ]);

        $column = GradeColumn::query()->where('teaching_class_id', $this->adminClass->id)->first();

        // 2. Simpan Nilai Murid
        $student = $this->adminClass->activeStudents()->first();
        $scoreResp = $this->actingAs($this->adminTeacher)->postJson(route('grades.bulk-save', $this->adminClass), [
            'rows' => [
                [
                    'student_id' => $student->id,
                    'dynamic_scores' => [
                        (string) $column->id => 88,
                    ],
                    'attendance_score' => 95,
                    'sts_score' => 85,
                    'sas_score' => 90,
                ],
            ],
        ]);
        $scoreResp->assertStatus(200);
        $scoreResp->assertJson(['status' => 'ok']);

        // 3. Update Bobot Rumus NA
        $weightResp = $this->actingAs($this->adminTeacher)->put(route('grades.weights.update', $this->adminClass), [
            'weight_ph' => 30,
            'weight_attendance' => 10,
            'weight_nt' => 20,
            'weight_sts' => 20,
            'weight_sas' => 20,
            'rounding_precision' => 1,
            'attendance_mode' => GradeWeight::MODE_MANUAL,
        ]);
        $weightResp->assertRedirect();
        $this->assertEquals(30, (float) $this->adminClass->gradeWeight()->first()->weight_ph);
    }

    public function test_admin_cannot_save_grades_in_another_teachers_class(): void
    {
        $student = $this->regularClass->activeStudents()->first();

        $scoreResp = $this->actingAs($this->adminTeacher)->postJson(route('grades.bulk-save', $this->regularClass), [
            'rows' => [
                [
                    'student_id' => $student->id,
                    'dynamic_scores' => [],
                    'attendance_score' => 90,
                    'sts_score' => 80,
                    'sas_score' => 85,
                ],
            ],
        ]);
        $scoreResp->assertStatus(403);
    }

    public function test_admin_teacher_receives_master_schedule_allocations(): void
    {
        // Setup slot jadwal resmi Wakasek untuk Kode 12 (MUHAMMAD IRFAN, S.Pd)
        \App\Models\SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'code' => '12',
            'name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject' => 'B. INGGRIS',
            'user_id' => null, // Belum tertaut langsung
        ]);

        \App\Models\SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'teacher_code' => '12',
            'teacher_name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject_name' => 'B. INGGRIS',
            'class_name' => '9E',
            'day_of_week' => 1,
            'period_order' => 1,
            'time_range' => '07.40 - 08.20',
        ]);
        \App\Models\SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'teacher_code' => '12',
            'teacher_name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject_name' => 'B. INGGRIS',
            'class_name' => '9F',
            'day_of_week' => 2,
            'period_order' => 2,
            'time_range' => '08.20 - 09.00',
        ]);

        // Muhammad Irfan (role: admin) memanggil API alokasi jadwal
        $response = $this->actingAs($this->adminTeacher)->getJson(route('classes.schedule-allocations'));
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'teacher_id' => $this->adminTeacher->id,
            'total_allocations' => 2,
        ]);

        $allocations = $response->json('allocations');
        $this->assertCount(2, $allocations);
        $classCodes = collect($allocations)->pluck('class_code')->all();
        $this->assertContains('9E', $classCodes);
        $this->assertContains('9F', $classCodes);

        // Pastikan relasi otomatis ditautkan ke user_id Muhammad Irfan
        $scheduleTeacher = \App\Models\SchoolScheduleTeacher::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->where('code', '12')
            ->first();
        $this->assertEquals($this->adminTeacher->id, $scheduleTeacher->user_id);
    }

    public function test_admin_teacher_sees_schedule_allocations_in_classes_index_page(): void
    {
        // Setup slot jadwal resmi untuk Muhammad Irfan
        \App\Models\SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'code' => '12',
            'name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject' => 'B. INGGRIS',
            'user_id' => $this->adminTeacher->id,
        ]);

        \App\Models\SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'teacher_code' => '12',
            'teacher_name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject_name' => 'B. INGGRIS',
            'class_name' => '9G',
            'day_of_week' => 3,
            'period_order' => 1,
            'time_range' => '07.30 - 08.10',
            'user_id' => $this->adminTeacher->id,
        ]);

        $response = $this->actingAs($this->adminTeacher)->get(route('classes.index'));
        $response->assertStatus(200);
        $response->assertViewHas('scheduleAllocations');

        $allocs = $response->viewData('scheduleAllocations');
        $this->assertNotEmpty($allocs);
        $this->assertEquals('9G', $allocs->first()['class_code']);

        // Pastikan view memuat elemen Jadwal Resmi Wakasek
        $response->assertSee('Jadwal Resmi Wakasek:');
        $response->assertSee('Pilih Kelas Mengajar dari Master Jadwal Sekolah *');
    }

    public function test_name_matching_supports_single_m_spelling_variation_for_admin(): void
    {
        \App\Models\SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'code' => '12',
            'name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject' => 'B. INGGRIS',
            'user_id' => null,
        ]);
        \App\Models\SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'teacher_code' => '12',
            'teacher_name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject_name' => 'B. INGGRIS',
            'class_name' => '9E',
            'day_of_week' => 1,
            'period_order' => 1,
            'time_range' => '07.40 - 08.20',
        ]);

        // Akun dengan ejaan 1 'm' (Muhamad Irfan)
        $adminSingleM = User::query()->create([
            'name' => 'Muhamad Irfan, S.Pd.',
            'email' => 'muhamad.irfan@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => 'guru123',
            'is_active' => true,
        ]);

        $service = app(\App\Services\SchoolScheduleService::class);
        $allocations = $service->getTeacherClassAllocations($this->academicYear, $adminSingleM);
        $this->assertCount(1, $allocations);
        $this->assertEquals('9E', $allocations->first()['class_code']);

        $schedule = $service->getTeacherSchedule($adminSingleM, $this->academicYear);
        $this->assertEquals(1, $schedule['total_hours']);
        $this->assertEquals('12', $schedule['teacher']['code']);
    }

    public function test_pure_admin_without_teaching_schedule_returns_zero_hours(): void
    {
        $pureAdmin = User::query()->create([
            'name' => 'Drs. Hendra Wijaya, M.M.Pd.',
            'email' => 'hendra@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'guru123',
            'is_active' => true,
        ]);

        $service = app(\App\Services\SchoolScheduleService::class);
        $allocations = $service->getTeacherClassAllocations($this->academicYear, $pureAdmin);
        $this->assertCount(0, $allocations);

        $schedule = $service->getTeacherSchedule($pureAdmin, $this->academicYear);
        $this->assertEquals(0, $schedule['total_hours']);
        $this->assertTrue($schedule['is_admin']);
    }

    public function test_admin_teacher_can_sync_and_create_all_classes_from_schedule(): void
    {
        \App\Models\SchoolScheduleTeacher::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'code' => '12',
            'name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject' => 'B. INGGRIS',
            'user_id' => $this->adminTeacher->id,
        ]);

        \App\Models\SchoolScheduleItem::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'teacher_code' => '12',
            'teacher_name' => 'MUHAMMAD IRFAN, S.Pd',
            'subject_name' => 'B. INGGRIS',
            'class_name' => '9E',
            'day_of_week' => 1,
            'period_order' => 1,
            'time_range' => '07.40 - 08.20',
            'user_id' => $this->adminTeacher->id,
        ]);

        $resp = $this->actingAs($this->adminTeacher)->post(route('classes.sync-from-schedule'), [
            'teacher_id' => $this->adminTeacher->id,
        ]);
        $resp->assertRedirect(route('classes.index'));
        $resp->assertSessionHas('success');

        $createdClasses = TeachingClass::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->where('teacher_id', $this->adminTeacher->id)
            ->get();

        $this->assertCount(2, $createdClasses); // VII-A from setUp + IX-E from sync
        $class9E = $createdClasses->firstWhere('class_name', 'IX-E');
        $this->assertNotNull($class9E);
        $this->assertEquals($this->subjectEnglish->id, $class9E->subject_id);

        // Alokasi sekarang statusnya already_created = true
        $service = app(\App\Services\SchoolScheduleService::class);
        $allocations = $service->getTeacherClassAllocations($this->academicYear, $this->adminTeacher);
        $alloc9E = $allocations->firstWhere('class_code', '9E');
        $this->assertTrue($alloc9E['already_created']);
    }
}
