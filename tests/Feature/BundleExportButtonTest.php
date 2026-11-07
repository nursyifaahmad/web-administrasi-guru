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

class BundleExportButtonTest extends TestCase
{
    use RefreshDatabase;

    private function setupTestClass(): array
    {
        SchoolSetting::current()->update([
            'school_name' => 'SMP NEGERI 2 LEMAHABANG',
            'default_city' => 'Lemahabang',
            'headmaster_name' => 'Kepala Sekolah, M.Pd.',
            'headmaster_nip' => '19700101 199501 1 001',
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

        $guru = User::query()->create([
            'name' => 'Budi Santoso, S.Pd.',
            'email' => 'budi@sekolah.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin@sekolah.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'VII-A',
            'grade_level' => 'VII',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
        ]);

        $teachingClass = TeachingClass::query()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $guru->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'grade_level' => 'VII',
            'class_name' => 'VII-A',
            'homeroom_teacher_name' => 'Wali Kelas VII-A',
            'is_grade_shared_with_admin' => false,
        ]);

        return [$guru, $admin, $teachingClass];
    }

    public function test_workspace_pages_render_cetak_bundel_lengkap_button_with_valid_alpine_attributes(): void
    {
        [$guru, $admin, $teachingClass] = $this->setupTestClass();

        $routes = [
            route('journals.index', $teachingClass),
            route('attendances.index', $teachingClass),
            route('grades.index', $teachingClass),
            route('classes.settings', $teachingClass),
        ];

        foreach ($routes as $url) {
            $res = $this->actingAs($guru)->get($url);
            $res->assertOk();

            // Memastikan tombol Cetak Bundel Lengkap muncul
            $res->assertSee('Cetak Bundel Lengkap');

            // Memastikan tidak ada quote rusak/terpotong yang merusak Alpine x-data
            $content = $res->getContent();
            $this->assertStringNotContainsString('Klik \"OK\"', $content);
            $this->assertStringContainsString('bundleModal', $content);
            $this->assertStringContainsString('openBundleModal()', $content);
        }
    }

    public function test_guru_can_export_bundle_pdf_and_excel(): void
    {
        [$guru, , $teachingClass] = $this->setupTestClass();

        $pdfRes = $this->actingAs($guru)->get(route('exports.bundle.pdf', [
            'teachingClass' => $teachingClass,
            'month' => 11,
            'year' => 2026,
        ]));
        $pdfRes->assertOk();
        $this->assertSame('application/pdf', $pdfRes->headers->get('content-type'));

        $excelRes = $this->actingAs($guru)->get(route('exports.bundle.excel', [
            'teachingClass' => $teachingClass,
            'month' => 11,
            'year' => 2026,
        ]));
        $excelRes->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $excelRes->headers->get('content-type'));
    }

    public function test_admin_monitoring_sees_supervision_notice_in_modal_when_grade_not_shared(): void
    {
        [, $admin, $teachingClass] = $this->setupTestClass();

        $res = $this->actingAs($admin)->get(route('journals.index', $teachingClass));
        $res->assertOk();
        $res->assertSee('Catatan Supervisi');
        $res->assertSee('Guru pengampu belum membuka akses buku nilai untuk kelas ini');
    }
}
