<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\GradeColumn;
use App\Models\SchoolSetting;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\SubjectKktp;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicGradebookAndKktpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_ph_nt_columns_custom_formula_weights_and_admin_kktp_control(): void
    {
        SchoolSetting::current();

        $admin = User::query()->create([
            'name' => 'Admin Kurikulum',
            'email' => 'admin@test.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Matematika',
            'email' => 'guru@test.sch.id',
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
            'code' => 'IPA',
            'name' => 'IPA Terpadu',
            'default_kktp' => 75,
        ]);
        SubjectKktp::query()->create([
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'kktp_value' => 76,
        ]);

        $classA = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-A',
            'homeroom_teacher_name' => 'Wali VIII-A',
        ]);
        $classA->ensureDefaultsInitialized();

        $classB = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-B',
            'homeroom_teacher_name' => 'Wali VIII-B',
        ]);
        $classB->ensureDefaultsInitialized();

        $student = ClassStudent::query()->create([
            'teaching_class_id' => $classA->id,
            'order_number' => 1,
            'full_name' => 'Bintang Pratama',
            'gender' => 'L',
        ]);

        // Guru TIDAK boleh mengakses halaman pengubahan KKM/KKTP milik Admin
        $this->actingAs($guru)->get(route('admin.kktp.index'))->assertStatus(403);
        $this->actingAs($guru)->put(route('admin.kktp.matrix.update'), [
            'global_default_kktp' => 60,
            'grade_levels_csv' => 'VII, VIII, IX',
        ])->assertStatus(403);

        // Tambah kolom PH ke-3 secara dinamis (default sudah ada PH 1, PH 2 dan NT 1, NT 2)
        $this->actingAs($guru)->post(route('grades.columns.store', $classA), [
            'type' => 'PH',
            'label' => 'PH 3',
            'topic_note' => 'Keaktifan Kelas',
        ])->assertRedirect();

        $phCols = $classA->phColumns()->get()->values();
        $ntCols = $classA->ntColumns()->get()->values();
        $this->assertCount(3, $phCols);
        $this->assertCount(2, $ntCols);

        // Validasi penolakan jika total persentase rumus != 100%
        $this->actingAs($guru)->put(route('grades.weights.update', $classA), [
            'weight_ph' => 30,
            'weight_attendance' => 20,
            'weight_nt' => 20,
            'weight_sts' => 20,
            'weight_sas' => 20, // Total 110% -> gagal
            'rounding_precision' => 2,
        ])->assertSessionHasErrors('weights');

        // Atur kontrak belajar valid: PH 30%, Kehadiran 10%, NT 20%, STS 15%, SAS 25% = 100%, sekaligus salin ke VIII-B
        $this->actingAs($guru)->put(route('grades.weights.update', $classA), [
            'weight_ph' => 30,
            'weight_attendance' => 10,
            'weight_nt' => 20,
            'weight_sts' => 15,
            'weight_sas' => 25,
            'rounding_precision' => 2,
            'agreement_note' => 'Kontrak Belajar Kelas VIII',
            'copy_to_class_ids' => [$classB->id],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(30.0, $classB->gradeWeight()->first()->weight_ph);
        $this->assertEquals(15.0, $classB->gradeWeight()->first()->weight_sts);

        // Simpan nilai siswa:
        // PH1=90, PH2=60, PH3=kosong(0) -> avg_ph = (90+60+0)/3 = 50
        // Kehadiran (manual) = 100
        // NT1=80, NT2=90 -> avg_nt = 85
        // STS = 80, SAS = 80
        // Ekspektasi NA = (50*0.30) + (100*0.10) + (85*0.20) + (80*0.15) + (80*0.25)
        //               = 15 + 10 + 17 + 12 + 20 = 74.00
        $this->actingAs($guru)->postJson(route('grades.bulk-save', $classA), [
            'rows' => [
                [
                    'student_id' => $student->id,
                    'dynamic_scores' => [
                        (string) $phCols[0]->id => 90,
                        (string) $phCols[1]->id => 60,
                        (string) $phCols[2]->id => null,
                        (string) $ntCols[0]->id => 80,
                        (string) $ntCols[1]->id => 90,
                    ],
                    'attendance_score' => 100,
                    'sts_score' => 80,
                    'sas_score' => 80,
                ],
            ],
        ])->assertOk();

        $savedGrade = StudentGrade::query()->where('class_student_id', $student->id)->firstOrFail();
        $this->assertEquals(50.0, $savedGrade->avg_ph);
        $this->assertEquals(85.0, $savedGrade->avg_nt);
        $this->assertEquals(74.0, $savedGrade->final_score);

        // Hapus kolom PH 3 yang kosong -> sekarang pembagi PH menjadi 2 kolom: avg_ph = (90+60)/2 = 75
        // NA baru = (75*0.30) + 10 + 17 + 12 + 20 = 22.5 + 59 = 81.50
        $this->actingAs($guru)->delete(route('grades.columns.destroy', [$classA, $phCols[2]]))->assertRedirect();

        $savedGrade->refresh();
        $this->assertEquals(75.0, $savedGrade->avg_ph);
        $this->assertEquals(81.5, $savedGrade->final_score);

        // Admin mengubah KKTP IPA Kelas VIII menjadi 85 -> effective_kktp berubah menjadi 85
        $this->actingAs($admin)->put(route('admin.kktp.matrix.update'), [
            'global_default_kktp' => 75,
            'grade_levels_csv' => 'VII, VIII, IX',
            'subjects' => [
                $subject->id => [
                    'code' => 'IPA',
                    'name' => 'IPA Terpadu',
                    'default_kktp' => 75,
                    'kktps' => ['VII' => 75, 'VIII' => 85, 'IX' => 80],
                ],
            ],
        ])->assertRedirect();

        $classA->unsetRelation('subject');
        $this->assertSame(85, $classA->fresh(['subject.kktps'])->effective_kktp);
    }

    public function test_auto_attendance_score_with_sick_and_leave_tolerance_syncs_from_attendance(): void
    {
        SchoolSetting::current();

        $guru = User::query()->create([
            'name' => 'Guru Bahasa Indonesia',
            'email' => 'guru.bindo@test.sch.id',
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
            'code' => 'BIN',
            'name' => 'Bahasa Indonesia',
            'default_kktp' => 75,
        ]);

        $classA = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali VII-A',
        ]);
        $classA->ensureDefaultsInitialized();

        $classB = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-B',
            'homeroom_teacher_name' => 'Wali VII-B',
        ]);
        $classB->ensureDefaultsInitialized();

        // Murid 1: 12 Hadir ('.') + 1 Dispen ('d') + 2 Sakit ('s') + 1 Izin ('i') + 0 Alfa => S+I = 3 (<=3) => Skor 100
        $studentWithinTol = ClassStudent::query()->create([
            'teaching_class_id' => $classA->id,
            'order_number' => 1,
            'full_name' => 'Alya Putri',
            'gender' => 'P',
        ]);

        // Murid 2: 12 Hadir ('.') + 2 Sakit ('s') + 2 Izin ('i') + 0 Alfa => S+I = 4 (>3) => Efektif 15/16 = 93.75
        $studentExceedTol = ClassStudent::query()->create([
            'teaching_class_id' => $classA->id,
            'order_number' => 2,
            'full_name' => 'Bagas Saputra',
            'gender' => 'L',
        ]);

        // Murid 3: 13 Hadir ('.') + 1 Sakit ('s') + 1 Izin ('i') + 1 Alfa ('a') => S+I = 2 (<=3), tapi Alfa 1 => Efektif 15/16 = 93.75
        $studentWithAlfa = ClassStudent::query()->create([
            'teaching_class_id' => $classA->id,
            'order_number' => 3,
            'full_name' => 'Citra Lestari',
            'gender' => 'P',
        ]);

        // Aktifkan mode kehadiran otomatis dengan toleransi S+I = 3x dan bobot Kehadiran 20%, PH 20%, NT 20%, STS 20%, SAS 20%
        $this->actingAs($guru)->put(route('grades.weights.update', $classA), [
            'weight_ph' => 20,
            'weight_attendance' => 20,
            'weight_nt' => 20,
            'weight_sts' => 20,
            'weight_sas' => 20,
            'rounding_precision' => 2,
            'attendance_mode' => 'auto',
            'sick_leave_tolerance' => 3,
            'agreement_note' => 'Toleransi Sakit/Izin maks 3 kali',
            'copy_to_class_ids' => [$classB->id],
        ])->assertSessionHasNoErrors();

        $weightB = $classB->gradeWeight()->firstOrFail();
        $this->assertSame('auto', $weightB->attendance_mode);
        $this->assertSame(3, $weightB->sick_leave_tolerance);

        // Isi nilai komponen lain 100 semua untuk memudahkan verifikasi pengaruh Nilai Kehadiran terhadap NA
        $phCols = $classA->phColumns()->get()->values();
        $ntCols = $classA->ntColumns()->get()->values();
        $fullDynamic = [
            (string) $phCols[0]->id => 100,
            (string) $phCols[1]->id => 100,
            (string) $ntCols[0]->id => 100,
            (string) $ntCols[1]->id => 100,
        ];

        $this->actingAs($guru)->postJson(route('grades.bulk-save', $classA), [
            'rows' => [
                ['student_id' => $studentWithinTol->id, 'dynamic_scores' => $fullDynamic, 'attendance_score' => 50, 'sts_score' => 100, 'sas_score' => 100],
                ['student_id' => $studentExceedTol->id, 'dynamic_scores' => $fullDynamic, 'attendance_score' => 50, 'sts_score' => 100, 'sas_score' => 100],
                ['student_id' => $studentWithAlfa->id, 'dynamic_scores' => $fullDynamic, 'attendance_score' => 50, 'sts_score' => 100, 'sas_score' => 100],
            ],
        ])->assertOk();

        // Simpan 16 pertemuan di Menu Absensi -> harus otomatis menyinkronkan attendance_score & final_score!
        $cellsWithin = [];
        for ($d = 1; $d <= 12; $d++) {
            $cellsWithin[$d] = '.';
        }
        $cellsWithin[13] = 'd'; // Dispen tetap hadir penuh, tidak makan kuota S+I
        $cellsWithin[14] = 's';
        $cellsWithin[15] = 's';
        $cellsWithin[16] = 'i';

        $cellsExceed = [];
        for ($d = 1; $d <= 12; $d++) {
            $cellsExceed[$d] = '.';
        }
        $cellsExceed[13] = 's';
        $cellsExceed[14] = 's';
        $cellsExceed[15] = 'i';
        $cellsExceed[16] = 'i'; // Total S+I = 4x (> 3x)

        $cellsAlfa = [];
        for ($d = 1; $d <= 13; $d++) {
            $cellsAlfa[$d] = '.';
        }
        $cellsAlfa[14] = 's';
        $cellsAlfa[15] = 'i';
        $cellsAlfa[16] = 'a'; // 1 Alfa tidak dapat toleransi

        $this->actingAs($guru)->postJson(route('attendances.bulk-save', $classA), [
            'year' => 2026,
            'month' => 11,
            'cells' => [
                $studentWithinTol->id => $cellsWithin,
                $studentExceedTol->id => $cellsExceed,
                $studentWithAlfa->id => $cellsAlfa,
            ],
        ])->assertOk();

        $grade1 = StudentGrade::query()->where('class_student_id', $studentWithinTol->id)->firstOrFail();
        $grade2 = StudentGrade::query()->where('class_student_id', $studentExceedTol->id)->firstOrFail();
        $grade3 = StudentGrade::query()->where('class_student_id', $studentWithAlfa->id)->firstOrFail();

        // Murid 1: Kehadiran = 100.00 -> NA = (100*0.8) + (100*0.2) = 100.00
        $this->assertEquals(100.0, $grade1->attendance_score);
        $this->assertEquals(100.0, $grade1->final_score);

        // Murid 2: Kehadiran = 15/16 * 100 = 93.75 -> NA = 80 + (93.75 * 0.2) = 80 + 18.75 = 98.75
        $this->assertEquals(93.75, $grade2->attendance_score);
        $this->assertEquals(98.75, $grade2->final_score);

        // Murid 3: Kehadiran = 15/16 * 100 = 93.75 -> NA = 98.75
        $this->assertEquals(93.75, $grade3->attendance_score);
        $this->assertEquals(98.75, $grade3->final_score);

        // Pastikan halaman Daftar Nilai merender badge mode otomatis & toleransi
        $this->actingAs($guru)
            ->get(route('grades.index', $classA))
            ->assertOk()
            ->assertSee('Toleransi S+I: 3x');
    }

    public function test_bulk_save_scores_supports_38_students_from_excel_paste_with_decimals(): void
    {
        SchoolSetting::current();

        $guru = User::query()->create([
            'name' => 'Guru IPA',
            'email' => 'guruipa@test.sch.id',
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
            'code' => 'IPA',
            'name' => 'IPA Terpadu',
            'default_kktp' => 75,
        ]);

        $class = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-C',
            'homeroom_teacher_name' => 'Wali VIII-C',
        ]);
        $class->ensureDefaultsInitialized();

        // Buat 38 murid aktif (absen 1 sampai 38)
        $students = [];
        $rowsPayload = [];
        for ($i = 1; $i <= 38; $i++) {
            $st = ClassStudent::query()->create([
                'teaching_class_id' => $class->id,
                'nis' => sprintf('2026%03d', $i),
                'nisn' => sprintf('001122%04d', $i),
                'full_name' => 'Siswa Absen ' . $i,
                'gender' => ($i % 2 === 0) ? 'P' : 'L',
                'is_active' => true,
            ]);
            $students[] = $st;

            $rowsPayload[] = [
                'student_id' => $st->id,
                'sts_score' => 70.0 + ($i * 0.5), // Nilai desimal misal 70.5, 71.0, 71.5...
                'sas_score' => 80.0,
                'dynamic_scores' => [],
            ];
        }

        // Simpan bulk 38 murid
        $response = $this->actingAs($guru)->postJson(route('grades.bulk-save', $class), [
            'rows' => $rowsPayload,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['saved_at']);

        // Verifikasi seluruh 38 data siswa tersimpan di database
        $this->assertEquals(38, StudentGrade::query()->where('teaching_class_id', $class->id)->count());

        $firstGrade = StudentGrade::query()->where('class_student_id', $students[0]->id)->firstOrFail();
        $this->assertEquals(70.5, $firstGrade->sts_score);

        $lastGrade = StudentGrade::query()->where('class_student_id', $students[37]->id)->firstOrFail();
        $this->assertEquals(89.0, $lastGrade->sts_score);

        // Akses halaman index dan pastikan view merender fitur Quick Paste & Fitur Samakan Nilai Absen 1
        $viewResponse = $this->actingAs($guru)->get(route('grades.index', $class));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Paste Excel');
        $viewResponse->assertSee('handleCellPaste');
        $viewResponse->assertSee('openQuickPasteModal');
        $viewResponse->assertSee('handleAbsen1Change');
        $viewResponse->assertSee('applySameScoreToAll');
        $viewResponse->assertSee('Set Nilai Sama dengan Absen 1?');
    }

    public function test_bulk_save_scores_supports_identical_scores_for_all_students_from_absen_1(): void
    {
        SchoolSetting::current();

        $guru = User::query()->create([
            'name' => 'Guru PKn',
            'email' => 'gurupkn@test.sch.id',
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
            'code' => 'PKN',
            'name' => 'Pendidikan Pancasila',
            'default_kktp' => 75,
        ]);

        $class = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali VII-A',
        ]);
        $class->ensureDefaultsInitialized();

        // Buat 38 murid
        $rowsPayload = [];
        for ($i = 1; $i <= 38; $i++) {
            $st = ClassStudent::query()->create([
                'teaching_class_id' => $class->id,
                'nis' => sprintf('20261%03d', $i),
                'nisn' => sprintf('001133%04d', $i),
                'full_name' => 'Murid VIIA ' . $i,
                'gender' => 'L',
                'is_active' => true,
            ]);

            // Simulasi seluruh 38 murid disamakan nilainya dengan absen 1 (misal 88.0)
            $rowsPayload[] = [
                'student_id' => $st->id,
                'sts_score' => 88.0,
                'sas_score' => 88.0,
                'dynamic_scores' => [],
            ];
        }

        $res = $this->actingAs($guru)->postJson(route('grades.bulk-save', $class), [
            'rows' => $rowsPayload,
        ]);

        $res->assertOk();
        $this->assertEquals(38, StudentGrade::query()->where('teaching_class_id', $class->id)->where('sts_score', 88.0)->count());
    }

    public function test_gradebook_view_includes_purple_active_name_cell_highlight_hooks(): void
    {
        SchoolSetting::current();

        $guru = User::query()->create([
            'name' => 'Guru Bahasa',
            'email' => 'gurubhs@test.sch.id',
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
            'code' => 'BIN',
            'name' => 'Bahasa Indonesia',
            'default_kktp' => 75,
        ]);

        $class = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-C',
            'homeroom_teacher_name' => 'Wali VII-C',
        ]);
        $class->ensureDefaultsInitialized();

        $response = $this->actingAs($guru)->get(route('grades.index', $class));

        $response->assertOk();
        $response->assertSee('activeStudentId', false);
        $response->assertSee('name-cell-active', false);
        $response->assertSee('handleTableFocusOut', false);
        $response->assertSee('selectInput($event, row.student_id)', false);
    }

    public function test_ph_assessment_date_absent_dispen_confirmation_data_and_null_preservation(): void
    {
        SchoolSetting::current();

        $admin = User::query()->create([
            'name' => 'Admin Kurikulum 2',
            'email' => 'admin2@test.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru IPA',
            'email' => 'guruipa@test.sch.id',
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
            'code' => 'IPA',
            'name' => 'Ilmu Pengetahuan Alam',
            'default_kktp' => 75,
        ]);

        $class = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-D',
            'homeroom_teacher_name' => 'Wali VII-D',
        ]);
        $class->ensureDefaultsInitialized();

        // Set bobot 100% ke PH untuk memverifikasi Aturan A5 secara presisi
        $class->gradeWeight()->update([
            'weight_ph' => 100,
            'weight_attendance' => 0,
            'weight_nt' => 0,
            'weight_sts' => 0,
            'weight_sas' => 0,
            'rounding_precision' => 2,
        ]);

        // 12 siswa: 3 Hadir (.), 2 Sakit (s), 2 Izin (i), 3 Alpa (a), 2 Dispensasi (d) = 9 terdampak
        $statuses = ['.', '.', '.', 's', 's', 'i', 'i', 'a', 'a', 'a', 'd', 'd'];
        $students = [];
        foreach ($statuses as $idx => $status) {
            $student = ClassStudent::query()->create([
                'teaching_class_id' => $class->id,
                'order_number' => $idx + 1,
                'full_name' => 'Siswa Uji ' . ($idx + 1),
                'gender' => $idx % 2 === 0 ? 'L' : 'P',
                'is_active' => true,
            ]);
            $students[] = $student;

            Attendance::query()->create([
                'teaching_class_id' => $class->id,
                'class_student_id' => $student->id,
                'attendance_date' => '2026-11-10',
                'status' => $status,
            ]);
        }

        DailyJournal::query()->create([
            'teaching_class_id' => $class->id,
            'journal_date' => '2026-11-10',
            'lesson_hours' => '3 - 4',
            'activity_material' => 'Penilaian Harian Bab 2 Klasifikasi',
            'present_count' => 5, // 3 hadir + 2 dispen
            'absent_count' => 7,  // 2s + 2i + 3a
        ]);

        // 1. Tambah kolom PH baru langsung dengan assessment_date
        $this->actingAs($guru)
            ->post(route('grades.columns.store', $class), [
                'type' => 'PH',
                'label' => 'PH Bab 2',
                'topic_note' => 'Klasifikasi',
                'assessment_date' => '2026-11-10',
            ])
            ->assertRedirect();

        $ph2 = GradeColumn::query()
            ->where('teaching_class_id', $class->id)
            ->where('type', 'PH')
            ->where('label', 'PH Bab 2')
            ->firstOrFail();
        $this->assertSame('2026-11-10', $ph2->assessment_date?->toDateString());

        // Tambah kolom NT dengan assessment_date -> harus diabaikan (null)
        $this->actingAs($guru)
            ->post(route('grades.columns.store', $class), [
                'type' => 'NT',
                'label' => 'NT Bab 2',
                'assessment_date' => '2026-11-10',
            ])
            ->assertRedirect();

        $ntExtra = GradeColumn::query()
            ->where('teaching_class_id', $class->id)
            ->where('type', 'NT')
            ->where('label', 'NT Bab 2')
            ->firstOrFail();
        $this->assertNull($ntExtra->assessment_date);

        // 2. Update tanggal pada PH default (PH 1) via JSON PUT
        $ph1 = $class->phColumns()->orderBy('sort_order')->firstOrFail();
        $updateRes = $this->actingAs($guru)
            ->putJson(route('grades.columns.update', [$class, $ph1]), [
                'label' => 'PH 1',
                'topic_note' => 'Bab 1',
                'assessment_date' => '2026-11-10',
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('column.assessment_date', '2026-11-10');

        // 3. Cek endpoint absent-students tanggal 2026-11-10 -> 9 terdampak (7 absent, 2 dispen)
        $absentRes = $this->actingAs($guru)
            ->getJson(route('journals.absent-students', ['teachingClass' => $class->id, 'date' => '2026-11-10']));

        $absentRes->assertOk()
            ->assertJsonPath('date', '2026-11-10')
            ->assertJsonPath('total_affected', 9)
            ->assertJsonPath('total_absent', 7)
            ->assertJsonPath('total_dispen', 2)
            ->assertJsonPath('sakit_count', 2)
            ->assertJsonPath('izin_count', 2)
            ->assertJsonPath('alfa_count', 3);

        // 4. Cek halaman Nilai untuk Guru memuat phAbsentMap, classJournalDates, dan modal hooks
        $guruIndexRes = $this->actingAs($guru)->get(route('grades.index', $class));
        $guruIndexRes->assertOk();
        $guruIndexRes->assertViewHas('phAbsentMap', function (array $map) {
            return isset($map['2026-11-10'])
                && $map['2026-11-10']['total_affected'] === 9
                && count($map['2026-11-10']['students']) === 9;
        });
        $guruIndexRes->assertViewHas('classJournalDates', function (array $dates) {
            return collect($dates)->contains('date', '2026-11-10');
        });
        $guruIndexRes->assertSee('absentConfirmModal', false);
        $guruIndexRes->assertSee('Kosongkan S/I/A (Pertahankan Dispen)', false);
        $guruIndexRes->assertSee('Isi Alpa = 0', false);
        $guruIndexRes->assertSee('getPhPillText(', false);

        // 5. Admin Read-Only tidak dirender modal & tombol konfirmasi S/I/A/D interaktif
        $adminIndexRes = $this->actingAs($admin)->get(route('grades.index', $class));
        $adminIndexRes->assertOk();
        $adminIndexRes->assertViewHas('phAbsentMap', []);
        $adminIndexRes->assertDontSee('x-show="absentConfirmModal.show"', false);
        $adminIndexRes->assertDontSee('@click.stop="openAbsentConfirmForColumn(', false);
        $adminIndexRes->assertDontSee('Kosongkan S/I/A (Pertahankan Dispen)', false);

        // 6. Simpan nilai hasil konfirmasi: Hadir=90, Sakit=null, Izin=null, Alpa=0, Dispen=85 pada PH 1
        // Sementara seluruh kolom PH aktif lainnya diisi 100 agar Aturan A5 (pembagi tetap seluruh kolom PH aktif) teruji
        $allPhIds = $class->phColumns()->orderBy('sort_order')->pluck('id')->all();
        $otherPhCount = max(0, count($allPhIds) - 1);
        $phCount = max(1, count($allPhIds));

        $payloadRows = [];
        foreach ($students as $idx => $student) {
            $st = $statuses[$idx];
            $ph1Val = match ($st) {
                '.' => 90,
                'd' => 85,
                'a' => 0,
                default => null, // Sakit & Izin dikosongkan
            };
            $dynScores = [];
            foreach ($allPhIds as $cid) {
                $dynScores[(string) $cid] = ($cid === $ph1->id) ? $ph1Val : 100;
            }
            $payloadRows[] = [
                'student_id' => $student->id,
                'dynamic_scores' => $dynScores,
                'attendance_score' => null,
                'sts_score' => null,
                'sas_score' => null,
            ];
        }

        $this->actingAs($guru)
            ->postJson(route('grades.bulk-save', $class), ['rows' => $payloadRows])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $expectedNullNa = round((100 * $otherPhCount) / $phCount, 2);
        $expectedDispenNa = round((85 + 100 * $otherPhCount) / $phCount, 2);

        // Cek siswa Sakit (#4, index 3) -> dynamic_scores[ph1] null & NA dihitung per Aturan A5
        $sakitGrade = StudentGrade::query()
            ->where('teaching_class_id', $class->id)
            ->where('class_student_id', $students[3]->id)
            ->firstOrFail();
        $this->assertNull($sakitGrade->dynamic_scores[(string) $ph1->id]);
        $this->assertEqualsWithDelta($expectedNullNa, (float) $sakitGrade->final_score, 0.01);

        // Cek siswa Alpa (#8, index 7) -> dynamic_scores[ph1] = 0 & NA identik dengan sel null
        $alfaGrade = StudentGrade::query()
            ->where('teaching_class_id', $class->id)
            ->where('class_student_id', $students[7]->id)
            ->firstOrFail();
        $this->assertSame(0.0, (float) $alfaGrade->dynamic_scores[(string) $ph1->id]);
        $this->assertEqualsWithDelta($expectedNullNa, (float) $alfaGrade->final_score, 0.01);

        // Cek siswa Dispensasi (#11, index 10) -> dynamic_scores[ph1] = 85
        $dispenGrade = StudentGrade::query()
            ->where('teaching_class_id', $class->id)
            ->where('class_student_id', $students[10]->id)
            ->firstOrFail();
        $this->assertSame(85.0, (float) $dispenGrade->dynamic_scores[(string) $ph1->id]);
        $this->assertEqualsWithDelta($expectedDispenNa, (float) $dispenGrade->final_score, 0.01);
    }
}
