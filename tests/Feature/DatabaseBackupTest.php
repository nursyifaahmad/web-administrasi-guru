<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Bersihkan file backup uji
        $service = app(DatabaseBackupService::class);
        $dir = $service->getStorageDir();
        if (File::isDirectory($dir)) {
            File::cleanDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_admin_can_access_backup_dashboard_and_guru_is_forbidden(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin.backup@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Biasa',
            'email' => 'guru.backup@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        // 1. Guest diarahkan ke login
        $this->get(route('admin.backup.index'))->assertRedirect(route('login'));

        // 2. Guru biasa ditolak (403)
        $this->actingAs($guru)->get(route('admin.backup.index'))->assertStatus(403);

        // 3. Admin berhasil membuka dashboard (200)
        $res = $this->actingAs($admin)->get(route('admin.backup.index'));
        $res->assertOk();
        $res->assertSee('Backup & Restore Database');
        $res->assertSee('Buat Backup Baru Sekarang');
        $res->assertSee('Upload & Restore File');
    }

    public function test_admin_can_create_and_download_backup(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Backup Test',
            'email' => 'admin.create@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        Subject::query()->create([
            'name' => 'Informatika',
            'code' => 'INF',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        // Buat backup via POST
        $res = $this->actingAs($admin)->post(route('admin.backup.store'));
        $res->assertRedirect(route('admin.backup.index'));
        $res->assertSessionHas('success');

        /** @var DatabaseBackupService $service */
        $service = app(DatabaseBackupService::class);
        $backups = $service->listBackups();

        $this->assertNotEmpty($backups);
        $latest = $backups->first();
        $this->assertFileExists($latest['path']);
        $this->assertGreaterThan(0, $latest['size']);

        // Download backup via GET
        $dlRes = $this->actingAs($admin)->get(route('admin.backup.download', $latest['filename']));
        $dlRes->assertOk();
        $this->assertTrue(
            str_contains($dlRes->headers->get('Content-Disposition') ?? '', $latest['filename'])
        );
    }

    public function test_admin_can_restore_database_and_auto_safety_backup_is_created(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Restore Test',
            'email' => 'admin.restore@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $sub = Subject::query()->create([
            'name' => 'Matematika Asli',
            'code' => 'MTK',
            'default_kktp' => 75,
            'is_active' => true,
        ]);

        /** @var DatabaseBackupService $service */
        $service = app(DatabaseBackupService::class);
        $initialBackup = $service->createBackup('backup_kondisi_awal');

        // Modifikasi data: buat subject baru dan hapus subject lama
        Subject::query()->create([
            'name' => 'Pelajaran Palsu Sementara',
            'code' => 'TMP',
            'default_kktp' => 60,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('subjects', ['name' => 'Pelajaran Palsu Sementara']);

        // Restore dari file backup_kondisi_awal
        $restoreRes = $this->actingAs($admin)->post(route('admin.backup.restore'), [
            'source' => 'server',
            'filename' => $initialBackup['filename'],
            'confirm_restore' => 1,
        ]);

        $restoreRes->assertRedirect(route('admin.backup.index'));
        $restoreRes->assertSessionHas('success');

        // Pastikan 'Pelajaran Palsu Sementara' sudah hilang karena database dipulihkan
        $this->assertDatabaseMissing('subjects', ['name' => 'Pelajaran Palsu Sementara']);
        $this->assertDatabaseHas('subjects', ['name' => 'Matematika Asli']);

        // Pastikan safety backup otomatis tercipta di folder backups
        $allBackups = $service->listBackups();
        $hasSafetyBackup = $allBackups->contains(fn ($b) => str_starts_with($b['filename'], 'safety_auto_before_restore'));
        $this->assertTrue($hasSafetyBackup, 'Safety backup otomatis harus tercipta sebelum proses restore dijalankan.');
    }

    public function test_admin_can_delete_backup(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Delete Test',
            'email' => 'admin.del@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        /** @var DatabaseBackupService $service */
        $service = app(DatabaseBackupService::class);
        $backup = $service->createBackup('backup_to_delete');
        $this->assertFileExists($backup['path']);

        $delRes = $this->actingAs($admin)->delete(route('admin.backup.destroy', $backup['filename']));
        $delRes->assertRedirect(route('admin.backup.index'));
        $delRes->assertSessionHas('success');

        $this->assertFileDoesNotExist($backup['path']);
    }

    public function test_sidebar_includes_backup_menu_for_admin_only(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sidebar',
            'email' => 'admin.side@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $guru = User::query()->create([
            'name' => 'Guru Sidebar',
            'email' => 'guru.side@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        // Admin melihat menu backup di sidebar
        $adminRes = $this->actingAs($admin)->get(route('classes.index'));
        $adminRes->assertOk();
        $adminRes->assertSee(route('admin.backup.index'));
        $adminRes->assertSee('Backup &amp; Restore DB', false);

        // Guru tidak melihat menu backup di sidebar
        $guruRes = $this->actingAs($guru)->get(route('classes.index'));
        $guruRes->assertOk();
        $guruRes->assertDontSee(route('admin.backup.index'));
    }

    public function test_backup_list_correctly_formats_wib_timezone_from_filename_and_mtime(): void
    {
        /** @var DatabaseBackupService $service */
        $service = app(DatabaseBackupService::class);
        $storageDir = $service->getStorageDir();

        // Buat file dummy dengan pola nama backup jam 15:28:46
        $filename = 'backup_db_administrasi_guru_2026-11-09_15-28-46.sql.gz';
        $fullPath = $storageDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($fullPath, 'dummy-content');

        $backups = $service->listBackups();
        $target = $backups->firstWhere('filename', $filename);

        $this->assertNotNull($target);
        // Pastikan jam yang ditampilkan adalah 15:28:46 WIB, BUKAN 08:28:46
        $this->assertStringContainsString('15:28:46 WIB', $target['created_at_label']);
        $this->assertStringContainsString('09 November 2026', $target['created_at_label']);

        // Uji tampilan di dashboard admin
        $admin = User::query()->create([
            'name' => 'Admin Backup Timezone Test',
            'email' => 'admin.tz@sekolah.sch.id',
            'role' => 'admin',
            'password' => 'password',
            'is_active' => true,
        ]);

        $res = $this->actingAs($admin)->get(route('admin.backup.index'));
        $res->assertOk();
        $res->assertSee('15:28:46 WIB');
        $res->assertSee('09 November 2026');
        $res->assertDontSee('08:28:46 WIB');
    }
}
