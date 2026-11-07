<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassStudentBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function setupEnvironment(): array
    {
        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin@sekolah.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Biasa, S.Pd.',
            'email' => 'guru@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);

        return [$admin, $guru, $schoolClass];
    }

    public function test_admin_can_view_student_list_with_checkboxes_and_detect_duplicates(): void
    {
        [$admin, , $schoolClass] = $this->setupEnvironment();

        // Buat 3 murid dengan 2 data duplikat (seperti pada screenshot user)
        $st1 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'nis_nisn' => '242507002',
            'full_name' => 'ACHMAD SUMANTRY',
            'gender' => 'L',
        ]);

        $st2 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'nis_nisn' => '242507002',
            'full_name' => 'ACHMAD SUMANTRY',
            'gender' => 'L',
        ]);

        $st3 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 2,
            'nis_nisn' => '242507007',
            'full_name' => 'AFIKA NUR MAULIDIA',
            'gender' => 'P',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.classes.show', $schoolClass));
        $response->assertOk();

        // Pastikan checkbox Master dan checkbox baris dirender
        $response->assertSee('x-model="selectAll"', false);
        $response->assertSee('x-model.number="selectedStudents"', false);
        $response->assertSee('confirmBulkDelete()', false);
        $response->assertSee('selectDuplicates()', false);

        // Pastikan deteksi duplikat menemukan 1 data duplikat ($st2)
        $duplicates = $response->viewData('duplicateStudentIds');
        $this->assertCount(1, $duplicates);
        $this->assertContains($st2->id, $duplicates);
    }

    public function test_admin_can_bulk_delete_selected_students(): void
    {
        [$admin, , $schoolClass] = $this->setupEnvironment();

        $st1 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'nis_nisn' => '242507002',
            'full_name' => 'ACHMAD SUMANTRY',
            'gender' => 'L',
        ]);

        $st2 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'nis_nisn' => '242507002',
            'full_name' => 'ACHMAD SUMANTRY',
            'gender' => 'L',
        ]);

        $st3 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 2,
            'nis_nisn' => '242507007',
            'full_name' => 'AFIKA NUR MAULIDIA',
            'gender' => 'P',
        ]);

        // Hapus duplikat $st2 dan $st1 sekaligus
        $response = $this->actingAs($admin)->delete(route('admin.classes.students.bulk-destroy', $schoolClass), [
            'student_ids' => [$st1->id, $st2->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan st1 dan st2 terhapus, sedangkan st3 masih ada
        $this->assertDatabaseMissing('class_students', ['id' => $st1->id]);
        $this->assertDatabaseMissing('class_students', ['id' => $st2->id]);
        $this->assertDatabaseHas('class_students', ['id' => $st3->id]);
        $this->assertSame(1, ClassStudent::query()->where('school_class_id', $schoolClass->id)->count());
    }

    public function test_bulk_delete_only_affects_students_in_the_targeted_school_class(): void
    {
        [$admin, , $schoolClass1] = $this->setupEnvironment();

        $year = AcademicYear::query()->first();
        $schoolClass2 = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-B',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-B',
        ]);

        $stInClass1 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass1->id,
            'order_number' => 1,
            'full_name' => 'Murid Kelas 1',
            'gender' => 'L',
        ]);

        $stInClass2 = ClassStudent::query()->create([
            'school_class_id' => $schoolClass2->id,
            'order_number' => 1,
            'full_name' => 'Murid Kelas 2',
            'gender' => 'P',
        ]);

        // Coba kirim ID murid dari Kelas 2 ke endpoint Kelas 1
        $response = $this->actingAs($admin)->delete(route('admin.classes.students.bulk-destroy', $schoolClass1), [
            'student_ids' => [$stInClass1->id, $stInClass2->id],
        ]);

        $response->assertRedirect();

        // stInClass1 harus terhapus, tetapi stInClass2 TIDAK BOLEH terhapus
        $this->assertDatabaseMissing('class_students', ['id' => $stInClass1->id]);
        $this->assertDatabaseHas('class_students', ['id' => $stInClass2->id]);
    }

    public function test_non_admin_cannot_bulk_delete_students(): void
    {
        [, $guru, $schoolClass] = $this->setupEnvironment();

        $st = ClassStudent::query()->create([
            'school_class_id' => $schoolClass->id,
            'order_number' => 1,
            'full_name' => 'Siswa Test',
            'gender' => 'L',
        ]);

        $response = $this->actingAs($guru)->delete(route('admin.classes.students.bulk-destroy', $schoolClass), [
            'student_ids' => [$st->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('class_students', ['id' => $st->id]);
    }
}
