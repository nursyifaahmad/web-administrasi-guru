<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\DailyJournal;
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

        // Uji login Guru A menggunakan NIP (tanpa spasi maupun dengan spasi)
        $this->post(route('login.post'), [
            'login' => '199205102019031012',
            'password' => 'secret123',
        ])->assertRedirect(route('classes.index'));

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

        // Uji Import Daftar Murid via file Excel (.xlsx)
        $tmpXlsx = tempnam(sys_get_temp_dir(), 'stu_') . '.xlsx';
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

        $this->actingAs($guruA)->post(route('classes.students.import', $firstClass), [
            'excel_file' => $uploaded,
        ])->assertRedirect();
        @unlink($tmpXlsx);

        $this->assertSame(2, ClassStudent::query()->where('teaching_class_id', $firstClass->id)->count());

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

            $verifyFile = tempnam(sys_get_temp_dir(), 'ver_') . '.xlsx';
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
}
