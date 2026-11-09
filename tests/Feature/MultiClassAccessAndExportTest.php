<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeachingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MultiClassAccessAndExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_manage_15_classes_isolated_from_other_teachers_while_admin_controls_all_and_exports_include_signatures(): void
    {
        SchoolSetting::current()->update([
            'school_name' => 'SMP NEGERI UJI COBA',
            'default_city' => 'Lemahabang',
            'headmaster_name' => 'Kepala Sekolah Uji, M.Pd.',
            'headmaster_nip' => '19700101 199501 1 001',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Utama',
            'email' => 'admin@uji.sch.id',
            'nip' => '19800001',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guruA = User::query()->create([
            'name' => 'Ahmad Subarjo, S.Pd.',
            'email' => 'ahmad@uji.sch.id',
            'nip' => '19920510 201903 1 012',
            'role' => 'guru',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $guruB = User::query()->create([
            'name' => 'Dewi Sartika, S.Pd.',
            'email' => 'dewi@uji.sch.id',
            'nip' => '19930611 202012 2 009',
            'role' => 'guru',
            'password' => 'secret123',
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
            'default_kktp' => 78,
        ]);

        // Uji login Guru A menggunakan NIP (tanpa spasi maupun dengan spasi) -> dialihkan ke menu Jadwal Hari Ini
        $this->post(route('login.post'), [
            'login' => '199205102019031012',
            'password' => 'secret123',
        ])->assertRedirect(route('schedules.index'));

        // Guru A membuat 15 Kelas Administrasi sekaligus
        for ($i = 1; $i <= 15; $i++) {
            $grade = $i <= 5 ? 'VII' : ($i <= 10 ? 'VIII' : 'IX');
            $this->actingAs($guruA)->post(route('classes.store'), [
                'subject_id' => $subject->id,
                'grade_level' => $grade,
                'class_name' => "{$grade}-" . chr(64 + (($i - 1) % 5) + 1),
                'homeroom_teacher_name' => "Wali Kelas Ke-{$i}, S.Pd.",
                'homeroom_teacher_nip' => "19850101 201001 1 0" . sprintf('%02d', $i),
            ])->assertRedirect();
        }

        $this->assertSame(15, TeachingClass::query()->where('teacher_id', $guruA->id)->count());

        $firstClass = TeachingClass::query()->where('teacher_id', $guruA->id)->firstOrFail();

        // Guru B ditolak (403) saat mencoba mengakses kelas milik Guru A
        $this->actingAs($guruB)->get(route('journals.index', $firstClass))->assertStatus(403);
        $this->actingAs($guruB)->get(route('attendances.index', $firstClass))->assertStatus(403);
        $this->actingAs($guruB)->get(route('grades.index', $firstClass))->assertStatus(403);

        // Admin dapat melihat seluruh 15 kelas di Dashboard Monitoring & mengaksesnya
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ahmad Subarjo, S.Pd.');
        $this->actingAs($admin)->get(route('grades.index', $firstClass))->assertOk();

        // Guru A dilarang mengimpor data murid (403) karena pengelolaan data terpusat oleh Admin
        $tmpDir = sys_get_temp_dir();
        $tmpXlsx = $tmpDir . DIRECTORY_SEPARATOR . 'stu_' . uniqid() . '.xlsx';
        $sp = new Spreadsheet();
        $sh = $sp->getActiveSheet();
        $sh->fromArray([
            ['No', 'NIS/NISN', 'Nama Lengkap', 'Nama Panggilan', 'Jenis Kelamin'],
            [1, '00112233', 'Rizky Pratama', 'Rizky', 'L'],
            [2, '00112234', 'Siti Aminah', 'Siti', 'P'],
        ]);
        (new Xlsx($sp))->save($tmpXlsx);

        $uploaded = new UploadedFile(
            $tmpXlsx,
            'murid.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Guru A ditolak 403 saat mencoba import
        $this->actingAs($guruA)->post(route('classes.students.import', $firstClass), [
            'excel_file' => $uploaded,
        ])->assertStatus(403);

        // Admin mengimpor murid ke rombel kelas tersebut (berhasil)
        $this->actingAs($admin)->post(route('classes.students.import', $firstClass), [
            'excel_file' => $uploaded,
        ])->assertRedirect();
        @unlink($tmpXlsx);

        $this->assertSame(2, $firstClass->activeStudents()->count());

        DailyJournal::query()->create([
            'teaching_class_id' => $firstClass->id,
            'journal_date' => '2026-11-02',
            'lesson_hours' => '1 - 2',
            'activity_material' => 'Teks Deskripsi',
            'present_count' => 2,
            'absent_count' => 0,
        ]);

        // Uji Cetak PDF di ketiga menu + bundel
        $printQuery = [
            'city' => 'Karawang',
            'print_date' => '2026-11-02',
            'paper_size' => 'F4',
            'orientation' => 'landscape',
            'show_headmaster' => '1',
            'month' => 11,
            'year' => 2026,
        ];

        foreach ([
            'exports.journal.pdf',
            'exports.attendance-monthly.pdf',
            'exports.attendance-recap.pdf',
            'exports.gradebook.pdf',
            'exports.bundle.pdf',
        ] as $pdfRoute) {
            $pdfRes = $this->actingAs($guruA)->get(route($pdfRoute, array_merge(['teachingClass' => $firstClass->id], $printQuery)));
            $pdfRes->assertOk();
            $this->assertStringStartsWith('%PDF', $pdfRes->getContent());
        }

        // Uji Download Excel (.xlsx) & pastikan berisi Titimangsa, Wali Kelas + NIP, Guru + NIP, dan Kepala Sekolah + NIP
        foreach ([
            'exports.journal.excel',
            'exports.attendance-monthly.excel',
            'exports.gradebook.excel',
            'exports.bundle.excel',
        ] as $excelRoute) {
            $xlsRes = $this->actingAs($guruA)->get(route($excelRoute, array_merge(['teachingClass' => $firstClass->id], $printQuery)));
            $xlsRes->assertOk();

            ob_start();
            $xlsRes->sendContent();
            $binary = ob_get_clean();

            $verifyFile = $tmpDir . DIRECTORY_SEPARATOR . 'ver_' . uniqid() . '.xlsx';
            file_put_contents($verifyFile, $binary);
            $loaded = IOFactory::load($verifyFile);
            $flatText = json_encode($loaded->getActiveSheet()->toArray());
            @unlink($verifyFile);

            $this->assertStringContainsString('Karawang, 02 November 2026', $flatText);
            $this->assertStringContainsString($firstClass->homeroom_teacher_name, $flatText);
            $this->assertStringContainsString($firstClass->homeroom_teacher_nip, $flatText);
            $this->assertStringContainsString($guruA->name, $flatText);
            $this->assertStringContainsString($guruA->nip, $flatText);
            $this->assertStringContainsString('Kepala Sekolah Uji, M.Pd.', $flatText);
        }
    }

    public function test_centralized_rombel_and_shared_students_between_teachers(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Pusat',
            'email' => 'admin.pusat@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guruMtk = User::query()->create([
            'name' => 'Guru Matematika',
            'email' => 'guru.mtk@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guruBin = User::query()->create([
            'name' => 'Guru Bahasa Indonesia',
            'email' => 'guru.bin@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subMtk = Subject::query()->create(['code' => 'MTK', 'name' => 'Matematika', 'default_kktp' => 75]);
        $subBin = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_kktp' => 76]);

        // 1. Admin membuat Master Rombel VII-A
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'grade_level' => 'VII',
            'name' => 'VII-A',
            'homeroom_teacher_name' => 'Dra. Hj. Siti Romlah, M.Pd.',
            'homeroom_teacher_nip' => '19750101 200001 2 001',
        ]);

        // 2. Admin menambahkan 3 murid ke Master Rombel VII-A
        $tmpDir = sys_get_temp_dir();
        $tplRes = $this->actingAs($admin)->get(route('admin.classes.students.template', $schoolClass));
        $tplRes->assertOk();

        ob_start();
        $tplRes->sendContent();
        $binaryTpl = ob_get_clean();

        $verifyTpl = $tmpDir . DIRECTORY_SEPARATOR . 'ver_tpl_' . uniqid() . '.xlsx';
        file_put_contents($verifyTpl, $binaryTpl);
        $loadedTpl = IOFactory::load($verifyTpl);
        $tplRows = $loadedTpl->getActiveSheet()->toArray();
        @unlink($verifyTpl);

        $this->assertSame(['No', 'NIS / NISN', 'Nama Lengkap', 'Jenis Kelamin (L/P)'], $tplRows[0]);

        $this->actingAs($admin)->post(route('admin.classes.students.store', $schoolClass), [
            'order_number' => 1,
            'nis_nisn' => '1001',
            'full_name' => 'Ahmad Faiz',
            'gender' => 'L',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.classes.students.store', $schoolClass), [
            'order_number' => 2,
            'nis_nisn' => '1002',
            'full_name' => 'Bilqis Syakira',
            'gender' => 'P',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.classes.students.store', $schoolClass), [
            'order_number' => 3,
            'nis_nisn' => '1003',
            'full_name' => 'Chairul Anam',
            'gender' => 'L',
        ])->assertRedirect();

        $this->assertSame(3, $schoolClass->students()->count());

        // 3. Guru MTK membuat kelas dengan memilih rombel VII-A
        $this->actingAs($guruMtk)->post(route('classes.store'), [
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subMtk->id,
            'schedule_info' => 'Senin 1-2',
        ])->assertRedirect();

        // 4. Guru BIN membuat kelas dengan memilih rombel VII-A
        $this->actingAs($guruBin)->post(route('classes.store'), [
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subBin->id,
            'schedule_info' => 'Selasa 3-4',
        ])->assertRedirect();

        $classMtk = TeachingClass::query()->where('teacher_id', $guruMtk->id)->where('subject_id', $subMtk->id)->firstOrFail();
        $classBin = TeachingClass::query()->where('teacher_id', $guruBin->id)->where('subject_id', $subBin->id)->firstOrFail();

        // Keduanya mewarisi nama rombel & wali kelas dari Master Rombel
        $this->assertSame('VII-A', $classMtk->class_name);
        $this->assertSame('VII-A', $classBin->class_name);
        $this->assertSame('Dra. Hj. Siti Romlah, M.Pd.', $classMtk->homeroom_teacher_name);
        $this->assertSame('Dra. Hj. Siti Romlah, M.Pd.', $classBin->homeroom_teacher_name);

        // Keduanya melihat 3 murid yang SAMA PERSIS
        $mtkStudents = $classMtk->activeStudents()->get();
        $binStudents = $classBin->activeStudents()->get();
        $this->assertCount(3, $mtkStudents);
        $this->assertCount(3, $binStudents);
        $this->assertEquals($mtkStudents->pluck('id')->all(), $binStudents->pluck('id')->all());

        // Guru dilarang menambah, mengedit, atau menghapus murid (403 Forbidden)
        $firstStudent = $mtkStudents->first();
        $this->actingAs($guruMtk)->post(route('classes.students.store', $classMtk), [
            'full_name' => 'Murid Ilegal',
            'gender' => 'L',
        ])->assertStatus(403);

        $this->actingAs($guruMtk)->put(route('classes.students.update', [$classMtk, $firstStudent]), [
            'order_number' => 1,
            'full_name' => 'Nama Diubah Guru',
            'gender' => 'L',
        ])->assertStatus(403);

        $this->actingAs($guruMtk)->delete(route('classes.students.destroy', [$classMtk, $firstStudent]))
            ->assertStatus(403);

        $this->actingAs($guruBin)->delete(route('classes.students.destroy', [$classBin, $firstStudent]))
            ->assertStatus(403);

        // 5. Admin menambahkan murid ke-4 ke Master Rombel
        $this->actingAs($admin)->post(route('admin.classes.students.store', $schoolClass), [
            'order_number' => 4,
            'nis_nisn' => '1004',
            'full_name' => 'Dinda Kirana',
            'gender' => 'P',
        ])->assertRedirect();

        // Murid ke-4 otomatis langsung muncul di kelas Guru MTK dan Guru BIN secara real-time
        $this->assertSame(4, $classMtk->activeStudents()->count());
        $this->assertSame(4, $classBin->activeStudents()->count());
        $this->assertTrue($classMtk->activeStudents()->where('full_name', 'Dinda Kirana')->exists());
        $this->assertTrue($classBin->activeStudents()->where('full_name', 'Dinda Kirana')->exists());

        // Halaman absensi kelas MTK berhasil dibuka tanpa error
        $attRes = $this->actingAs($guruMtk)->get(route('attendances.index', $classMtk));
        $attRes->assertOk();

        // Export bulanan & rekap PDF/Excel berjalan mulus
        $this->actingAs($guruMtk)->get(route('exports.attendance-monthly.pdf', [$classMtk, 'month' => 7, 'year' => 2026]))
            ->assertOk();
        $this->actingAs($guruMtk)->get(route('exports.attendance-recap.pdf', $classMtk))
            ->assertOk();
        $this->actingAs($guruMtk)->get(route('exports.attendance-monthly.excel', [$classMtk, 'month' => 7, 'year' => 2026]))
            ->assertOk();
        $this->actingAs($guruMtk)->get(route('exports.attendance-recap.excel', $classMtk))
            ->assertOk();
    }

    public function test_admin_cannot_delete_teacher_administration_book_and_gets_403(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Utama',
            'email' => 'admin.sec@uji.sch.id',
            'nip' => '19800002',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Pengampu, S.Pd.',
            'email' => 'guru.sec@uji.sch.id',
            'nip' => '19920510 201903 1 099',
            'role' => 'guru',
            'password' => 'secret123',
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

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A, S.Pd.',
        ]);

        // Admin mencoba menghapus buku administrasi guru -> harus ditolak 403 Forbidden!
        $response = $this->actingAs($admin)->delete(route('classes.destroy', $teachingClass));
        $response->assertStatus(403);

        // Pastikan buku kelas tetap aman dan ada di database
        $this->assertDatabaseHas('teaching_classes', [
            'id' => $teachingClass->id,
        ]);
    }

    public function test_other_teacher_cannot_delete_another_teachers_book_and_gets_403(): void
    {
        $guruA = User::query()->create([
            'name' => 'Guru A, S.Pd.',
            'email' => 'guruA@uji.sch.id',
            'nip' => '19920510 201903 1 011',
            'role' => 'guru',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $guruB = User::query()->create([
            'name' => 'Guru B, S.Pd.',
            'email' => 'guruB@uji.sch.id',
            'nip' => '19920510 201903 1 022',
            'role' => 'guru',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'code' => 'IPS',
            'name' => 'Ilmu Pengetahuan Sosial',
            'default_kktp' => 75,
        ]);

        $classA = TeachingClass::query()->create([
            'teacher_id' => $guruA->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-B',
            'homeroom_teacher_name' => 'Wali Kelas VII-B, S.Pd.',
        ]);

        // Guru B mencoba menghapus kelas Guru A -> harus ditolak 403
        $response = $this->actingAs($guruB)->delete(route('classes.destroy', $classA));
        $response->assertStatus(403);

        $this->assertDatabaseHas('teaching_classes', [
            'id' => $classA->id,
        ]);
    }

    public function test_owner_teacher_can_delete_own_administration_book(): void
    {
        $guru = User::query()->create([
            'name' => 'Guru Asli, S.Pd.',
            'email' => 'guru.asli@uji.sch.id',
            'nip' => '19920510 201903 1 033',
            'role' => 'guru',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'default_kktp' => 75,
        ]);

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VIII',
            'class_name' => 'VIII-A',
            'homeroom_teacher_name' => 'Wali Kelas VIII-A, S.Pd.',
        ]);

        // Guru pemilik menghapus kelas miliknya -> berhasil redirect
        $response = $this->actingAs($guru)->delete(route('classes.destroy', $teachingClass));
        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('teaching_classes', [
            'id' => $teachingClass->id,
        ]);
    }

    public function test_admin_sees_locked_protection_badge_in_settings_view(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin.view@uji.sch.id',
            'nip' => '19800003',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Pengampu Asli, S.Pd.',
            'email' => 'guru.view@uji.sch.id',
            'nip' => '19920510 201903 1 044',
            'role' => 'guru',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'code' => 'PAI',
            'name' => 'Pendidikan Agama Islam',
            'default_kktp' => 78,
        ]);

        $teachingClass = TeachingClass::query()->create([
            'teacher_id' => $guru->id,
            'academic_year_id' => $year->id,
            'subject_id' => $subject->id,
            'grade_level' => 'IX',
            'class_name' => 'IX-A',
            'homeroom_teacher_name' => 'Wali Kelas IX-A, S.Pd.',
        ]);

        // Admin membuka pengaturan kelas:
        // Harus melihat badge Proteksi Terkunci dan TIDAK ADA tombol hapus
        $responseAdmin = $this->actingAs($admin)->get(route('classes.settings', $teachingClass));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Proteksi Buku Administrasi Guru (Terkunci untuk Admin)');
        $responseAdmin->assertDontSee('Hapus Buku Kelas');

        // Guru pemilik membuka pengaturan kelas:
        // Harus melihat tombol Hapus Buku Kelas
        $responseGuru = $this->actingAs($guru)->get(route('classes.settings', $teachingClass));
        $responseGuru->assertOk();
        $responseGuru->assertSee('Hapus Buku Kelas');
        $responseGuru->assertDontSee('Proteksi Buku Administrasi Guru (Terkunci untuk Admin)');
    }
}
