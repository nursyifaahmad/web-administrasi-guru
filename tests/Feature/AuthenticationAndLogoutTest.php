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
}
