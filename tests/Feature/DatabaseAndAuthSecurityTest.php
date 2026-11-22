<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DatabaseAndAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('app/backups');
        RateLimiter::clear('login:guru@smpn2lemahabang.sch.id|127.0.0.1');
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->backupDir)) {
            foreach (File::glob($this->backupDir . '/backup_*.sql*') as $file) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_http_responses_include_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::query()->create([
            'name' => 'Guru Uji Rate Limit',
            'email' => 'guru@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $fail = $this->post(route('login.post'), [
                'login' => 'guru@smpn2lemahabang.sch.id',
                'password' => 'wrong-pass',
            ]);
            $fail->assertSessionHasErrors('login');
            $this->assertGuest();
        }

        // Percobaan ke-6 tetap diblokir walaupun menggunakan password yang benar.
        $blocked = $this->post(route('login.post'), [
            'login' => 'guru@smpn2lemahabang.sch.id',
            'password' => 'SecretPass123!',
        ]);

        $blocked->assertSessionHasErrors('login');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            session('errors')->first('login')
        );
        $this->assertGuest();
    }

    public function test_legacy_irfan_shortcut_and_password_cross_alias_are_rejected(): void
    {
        User::query()->create([
            'name' => 'Irfan',
            'email' => 'irfan.guru@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('PasswordUnik99!'),
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Guru Biasa',
            'email' => 'guru.biasa@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('guru123'),
            'is_active' => true,
        ]);

        // Shortcut substring 'irfan' tidak boleh lagi bisa login.
        $irfanAttempt = $this->post(route('login.post'), [
            'login' => 'irfan',
            'password' => 'PasswordUnik99!',
        ]);
        $irfanAttempt->assertSessionHasErrors('login');
        $this->assertGuest();

        // Cross-alias 'password' untuk user ber-password 'guru123' tidak boleh diterima.
        $aliasAttempt = $this->post(route('login.post'), [
            'login' => 'guru.biasa@smpn2lemahabang.sch.id',
            'password' => 'password',
        ]);
        $aliasAttempt->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_non_admin_cannot_access_create_download_or_restore_database_backups(): void
    {
        $guru = User::query()->create([
            'name' => 'Guru Biasa',
            'email' => 'guru.nonadmin@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($guru)->get(route('admin.backup.index'))->assertForbidden();
        $this->actingAs($guru)->post(route('admin.backup.store'))->assertForbidden();
        $this->actingAs($guru)->post(route('admin.backup.restore'), [
            'source' => 'server',
            'filename' => 'backup_fake.sql',
            'confirm_restore' => 1,
            'current_password' => 'password',
        ])->assertForbidden();
    }

    public function test_database_restore_requires_valid_current_password(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin.restore@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => bcrypt('AdminPass2026!'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.backup.store'))->assertSessionHas('success');

        $backups = app(DatabaseBackupService::class)->listBackups();
        $this->assertNotEmpty($backups);
        $filename = $backups[0]['filename'];

        // Tanpa password konfirmasi -> ditolak.
        $missingPwd = $this->actingAs($admin)->post(route('admin.backup.restore'), [
            'source' => 'server',
            'filename' => $filename,
            'confirm_restore' => 1,
        ]);
        $missingPwd->assertSessionHasErrors('current_password');

        // Dengan password salah -> ditolak.
        $wrongPwd = $this->actingAs($admin)->post(route('admin.backup.restore'), [
            'source' => 'server',
            'filename' => $filename,
            'confirm_restore' => 1,
            'current_password' => 'SalahTotal123!',
        ]);
        $wrongPwd->assertSessionHasErrors('current_password');
    }

    public function test_database_restore_rejects_dangerous_sql_directives_and_php_tags(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sekolah',
            'email' => 'admin.sqli@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $dangerousPayloads = [
            "SELECT 'shell' INTO OUTFILE '/var/www/html/public/shell.php';",
            "SELECT LOAD_FILE('/etc/passwd');",
            "<?php system('id'); ?>",
        ];

        foreach ($dangerousPayloads as $index => $payload) {
            $upload = UploadedFile::fake()->createWithContent("evil_{$index}.sql", $payload);
            $response = $this->actingAs($admin)->post(route('admin.backup.restore'), [
                'source' => 'upload',
                'backup_file' => $upload,
                'confirm_restore' => 1,
                'current_password' => 'password',
            ]);

            $response->assertSessionHas('error');
        }
    }

    public function test_database_download_includes_nosniff_and_no_store_headers(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Unduh',
            'email' => 'admin.download@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.backup.store'))->assertSessionHas('success');
        $backups = app(DatabaseBackupService::class)->listBackups();
        $filename = $backups[0]['filename'];

        $download = $this->actingAs($admin)->get(route('admin.backup.download', $filename));
        $download->assertOk();
        $download->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
    }

    public function test_profile_rejects_svg_data_uri_svg_upload_and_short_password(): void
    {
        $guru = User::query()->create([
            'name' => 'Guru Profil',
            'email' => 'guru.profil@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        // SVG data URI ditolak.
        $svgUriRes = $this->actingAs($guru)->put(route('profile.update'), [
            'name' => $guru->name,
            'email' => $guru->email,
            'signature_data' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ]);
        $svgUriRes->assertSessionHasErrors('signature_data');

        // Upload file SVG ditolak.
        $svgFile = UploadedFile::fake()->createWithContent(
            'ttd.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );
        $svgFileRes = $this->actingAs($guru)->put(route('profile.update'), [
            'name' => $guru->name,
            'email' => $guru->email,
            'signature_file' => $svgFile,
        ]);
        $svgFileRes->assertSessionHasErrors('signature_file');

        // Password kurang dari 8 karakter pada ubah profil ditolak.
        $shortPassRes = $this->actingAs($guru)->put(route('profile.update'), [
            'name' => $guru->name,
            'email' => $guru->email,
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);
        $shortPassRes->assertSessionHasErrors('password');
    }
}
