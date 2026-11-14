<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingClassBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'order' => 1,
        ]);

        $teacher1 = User::query()->create([
            'name' => 'Guru Satu, S.Pd.',
            'email' => 'guru1@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $teacher2 = User::query()->create([
            'name' => 'Guru Dua, S.Pd.',
            'email' => 'guru2@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'name' => 'Muhammad Irfan, S.Pd.',
            'email' => 'admin@sekolah.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $rombelA = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        $rombelB = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'IX-B',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-B',
        ]);

        $student1 = ClassStudent::query()->create([
            'school_class_id' => $rombelA->id,
            'order_number' => 1,
            'nis_nisn' => '1001',
            'full_name' => 'Budi Murid Satu',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $student2 = ClassStudent::query()->create([
            'school_class_id' => $rombelB->id,
            'order_number' => 1,
            'nis_nisn' => '1002',
            'full_name' => 'Siti Murid Dua',
            'gender' => 'P',
            'is_active' => true,
        ]);

        return [$year, $subject, $teacher1, $teacher2, $admin, $rombelA, $rombelB, $student1, $student2];
    }

    public function test_teacher_can_bulk_delete_own_teaching_classes_with_hapus_confirmation(): void
    {
        [$year, $subject, $teacher1, , , $rombelA, $rombelB, $student1, $student2] = $this->setupData();

        $tc1 = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        $tc2 = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelB->id,
            'class_name' => 'IX-B',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-B',
        ]);

        // Kaitkan murid ke teaching class
        $student1->update(['teaching_class_id' => $tc1->id]);
        $student2->update(['teaching_class_id' => $tc2->id]);

        // Buat jurnal harian internal pada tc1
        DailyJournal::query()->create([
            'teaching_class_id' => $tc1->id,
            'journal_date' => now()->toDateString(),
            'lesson_hours' => '1-2',
            'activity_material' => 'Aljabar',
        ]);

        $response = $this->actingAs($teacher1)->delete(route('classes.bulk-destroy'), [
            'class_ids' => [$tc1->id, $tc2->id],
            'confirmation_text' => 'HAPUS',
        ]);

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success', 'Berhasil menghapus 2 buku administrasi kelas.');

        // Buku administrasi kelas harus terhapus
        $this->assertDatabaseMissing('teaching_classes', ['id' => $tc1->id]);
        $this->assertDatabaseMissing('teaching_classes', ['id' => $tc2->id]);

        // Jurnal harian internal terhapus via cascade
        $this->assertDatabaseMissing('daily_journals', ['teaching_class_id' => $tc1->id]);

        // Master murid dan rombel HARUS TETAP UTUH (tidak boleh terhapus)
        $this->assertDatabaseHas('school_classes', ['id' => $rombelA->id]);
        $this->assertDatabaseHas('school_classes', ['id' => $rombelB->id]);
        $this->assertDatabaseHas('class_students', [
            'id' => $student1->id,
            'school_class_id' => $rombelA->id,
            'teaching_class_id' => null,
        ]);
        $this->assertDatabaseHas('class_students', [
            'id' => $student2->id,
            'school_class_id' => $rombelB->id,
            'teaching_class_id' => null,
        ]);
    }

    public function test_bulk_delete_case_insensitive_and_trimmed_hapus_accepted(): void
    {
        [$year, $subject, $teacher1, , , $rombelA] = $this->setupData();

        $tc = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        $response = $this->actingAs($teacher1)->delete(route('classes.bulk-destroy'), [
            'class_ids' => [$tc->id],
            'confirmation_text' => '   hapus   ',
        ]);

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success', 'Berhasil menghapus 1 buku administrasi kelas.');
        $this->assertDatabaseMissing('teaching_classes', ['id' => $tc->id]);
    }

    public function test_bulk_delete_rejected_if_confirmation_text_is_invalid(): void
    {
        [$year, $subject, $teacher1, , , $rombelA] = $this->setupData();

        $tc = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        $response = $this->actingAs($teacher1)->from(route('classes.index'))->delete(route('classes.bulk-destroy'), [
            'class_ids' => [$tc->id],
            'confirmation_text' => 'BUKAN_HAPUS',
        ]);

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('error', 'Konfirmasi gagal. Anda wajib mengetik kata "HAPUS" secara tepat.');
        $this->assertDatabaseHas('teaching_classes', ['id' => $tc->id]);
    }

    public function test_bulk_delete_validation_requires_at_least_one_class_id(): void
    {
        [, , $teacher1] = $this->setupData();

        $response = $this->actingAs($teacher1)->delete(route('classes.bulk-destroy'), [
            'class_ids' => [],
            'confirmation_text' => 'HAPUS',
        ]);

        $response->assertSessionHasErrors('class_ids');
    }

    public function test_teacher_cannot_bulk_delete_another_teachers_classes(): void
    {
        [$year, $subject, $teacher1, $teacher2, , $rombelA, $rombelB] = $this->setupData();

        $tcTeacher1 = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        $tcTeacher2 = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher2->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelB->id,
            'class_name' => 'IX-B',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-B',
        ]);

        // Teacher 1 mencoba menghapus kelas milik Teacher 2
        $response = $this->actingAs($teacher1)->delete(route('classes.bulk-destroy'), [
            'class_ids' => [$tcTeacher1->id, $tcTeacher2->id],
            'confirmation_text' => 'HAPUS',
        ]);

        $response->assertStatus(403);

        // Kedua kelas tidak boleh terhapus
        $this->assertDatabaseHas('teaching_classes', ['id' => $tcTeacher1->id]);
        $this->assertDatabaseHas('teaching_classes', ['id' => $tcTeacher2->id]);
    }

    public function test_admin_cannot_bulk_delete_classes_of_other_teachers(): void
    {
        [$year, $subject, $teacher1, , $admin, $rombelA] = $this->setupData();

        $tc = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        // Admin mencoba menghapus kelas milik Guru 1
        $response = $this->actingAs($admin)->delete(route('classes.bulk-destroy'), [
            'class_ids' => [$tc->id],
            'confirmation_text' => 'HAPUS',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('teaching_classes', ['id' => $tc->id]);
    }

    public function test_admin_who_teaches_can_bulk_delete_their_own_classes(): void
    {
        [$year, $subject, , , $admin, $rombelA] = $this->setupData();

        $tcAdmin = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $admin->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        $response = $this->actingAs($admin)->delete(route('classes.bulk-destroy'), [
            'class_ids' => [$tcAdmin->id],
            'confirmation_text' => 'HAPUS',
        ]);

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('teaching_classes', ['id' => $tcAdmin->id]);
    }

    public function test_classes_index_and_portal_guru_views_replace_pengaturan_with_hapus_buku_modal(): void
    {
        [$year, $subject, $teacher1, , $admin, $rombelA] = $this->setupData();
        $year->is_active = true;
        $year->save();

        $tc = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'school_class_id' => $rombelA->id,
            'class_name' => 'IX-A',
            'grade_level' => 'IX',
            'homeroom_teacher_name' => 'Wali IX-A',
        ]);

        // 1. Pemilik kelas melihat tombol Hapus Buku + modal konfirmasi ketik "Hapus", bukan Pengaturan
        $indexRes = $this->actingAs($teacher1)->get(route('classes.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('Hapus Buku');
        $indexRes->assertSee('openSingleDeleteModal');
        $indexRes->assertSee('singleDeleteModal');
        $indexRes->assertSee('Hapus Buku Administrasi?');
        $indexRes->assertSee('Hapus Buku Permanen');
        $indexRes->assertDontSee(route('classes.settings', $tc));

        // 2. Admin yang memantau kelas guru lain tidak melihat tombol Hapus Buku pada kartu tersebut
        $adminIndexRes = $this->actingAs($admin)->get(route('classes.index'));
        $adminIndexRes->assertOk();
        $adminIndexRes->assertSee('grid-cols-3');
        $adminIndexRes->assertDontSee(route('classes.destroy', $tc));
        $adminIndexRes->assertDontSee(route('classes.settings', $tc));

        // 3. Tab workspace & halaman absensi/jadwal guru tidak lagi mengarah ke Pengaturan
        $journalRes = $this->actingAs($teacher1)->get(route('journals.index', $tc));
        $journalRes->assertOk();
        $journalRes->assertDontSee('Pengaturan Kelas');

        $attendanceRes = $this->actingAs($teacher1)->get(route('attendances.index', $tc));
        $attendanceRes->assertOk();
        $attendanceRes->assertSee('Jadwal Mengikuti Portal Admin');
        $attendanceRes->assertDontSee('Atur Hari Mengajar');

        $scheduleRes = $this->actingAs($teacher1)->get(route('schedules.index'));
        $scheduleRes->assertOk();
        $scheduleRes->assertDontSee('Pengaturan Kelas & Jadwal');
        $scheduleRes->assertDontSee('Atur Hari');
        $scheduleRes->assertSee('Sinkron via Admin');
        $scheduleRes->assertDontSee(route('classes.students', $tc));

        // 4. Akses langsung ke route kelas settings untuk guru menampilkan informasi read-only sinkron Admin
        $settingsRes = $this->actingAs($teacher1)->get(route('classes.settings', $tc));
        $settingsRes->assertOk();
        $settingsRes->assertSee('Informasi Kelas Tersinkronisasi Portal Admin');
        $settingsRes->assertDontSee('Simpan Pengaturan Kelas');
    }
}
