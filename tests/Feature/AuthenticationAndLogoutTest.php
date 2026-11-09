<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class AuthenticationAndLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_logout_via_post_and_is_redirected_to_login(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Penguji',
            'email' => 'guru@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Anda telah keluar dari sistem.');
        $this->assertGuest();
    }

    public function test_user_can_logout_via_get_request(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Penguji 2',
            'email' => 'guru2@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // Pengguna mengakses /logout via GET (misal refresh browser saat di URL /logout)
        $response = $this->get(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Anda telah keluar dari sistem.');
        $this->assertGuest();
    }

    public function test_guest_accessing_logout_redirects_cleanly_without_error(): void
    {
        // Kondisi ketika sesi sudah expired dan user dianggap guest
        $postResponse = $this->post(route('logout'));
        $postResponse->assertRedirect(route('login'));

        $getResponse = $this->get(route('logout'));
        $getResponse->assertRedirect(route('login'));
    }

    public function test_logout_is_exempt_from_csrf_verification(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Penguji 3',
            'email' => 'guru3@smpn2lemahabang.sch.id',
            'role' => 'guru',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Kirim request POST tanpa menyertakan _token sama sekali (simulasi sesi kedaluwarsa)
        $response = $this->withoutMiddleware(\App\Http\Middleware\ShareWorkspaceContext::class)
            ->call('POST', '/logout', [], [], [], [
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guru_login_and_home_route_redirect_to_schedules_index_jadwal_hari_ini(): void
    {
        $guru = User::query()->create([
            'name' => 'Pak Guru Penguji',
            'email' => 'guru.penguji@smpn2lemahabang.sch.id',
            'nip' => '198501012010011001',
            'role' => 'guru',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Penguji',
            'email' => 'admin.penguji@smpn2lemahabang.sch.id',
            'role' => 'admin',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        // 1. Uji Login Guru -> dialihkan ke menu Jadwal Hari Ini (schedules.index)
        $loginGuruResponse = $this->post(route('login.post'), [
            'login' => '198501012010011001',
            'password' => 'password123',
        ]);
        $loginGuruResponse->assertRedirect(route('schedules.index'));

        // 2. Akses root '/' saat sesi guru aktif -> dialihkan ke schedules.index
        $homeGuruResponse = $this->actingAs($guru)->get(route('home'));
        $homeGuruResponse->assertRedirect(route('schedules.index'));

        // 3. Akses '/login' saat sesi guru aktif -> dialihkan ke schedules.index
        $showLoginGuruResponse = $this->actingAs($guru)->get(route('login'));
        $showLoginGuruResponse->assertRedirect(route('schedules.index'));

        // 4. Pastikan halaman schedules.index dapat dibuka guru dan memuat menu Jadwal Hari Ini
        $scheduleView = $this->actingAs($guru)->get(route('schedules.index'));
        $scheduleView->assertOk();
        $scheduleView->assertSee('Jadwal Hari Ini');
        $scheduleView->assertSee('Jadwal KBM Hari Ini');

        // 5. Uji Admin tetap dialihkan ke admin.dashboard
        $homeAdminResponse = $this->actingAs($admin)->get(route('home'));
        $homeAdminResponse->assertRedirect(route('admin.dashboard'));
    }
}
