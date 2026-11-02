<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
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
}
