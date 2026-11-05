<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sistem Administrasi Guru Terpadu');
        $response->assertSee('favicon.svg');
        $response->assertSee('favicon.ico');
    }

    public function test_authenticated_page_renders_custom_favicon(): void
    {
        $year = \App\Models\AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
        $teacher = \App\Models\User::query()->create([
            'name' => 'Guru Penguji',
            'email' => 'guru.test@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)
            ->withSession([
                'active_academic_year_id' => $year->id,
            ])
            ->get(route('classes.index'));

        $response->assertStatus(200);
        $response->assertSee('favicon.svg');
        $response->assertSee('favicon-32x32.png');
        $response->assertSee('apple-touch-icon.png');
        $response->assertSee('Waktu Sistem Realtime (WIB)');
        $response->assertSee('timeFormatted');
        $response->assertSee('dateFormatted');
    }

    public function test_sidebar_and_login_page_render_smpn2_lemahabang_logo_instead_of_pemda(): void
    {
        $pemdaDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $school = \App\Models\SchoolSetting::current();
        $school->update([
            'logo_left_data' => $pemdaDataUri,
        ]);

        // Login page should display school logo, not Pemda logo
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('alt="Logo ' . $school->school_name . '"', false);
        $response->assertDontSee($pemdaDataUri);

        // Authenticated sidebar should also display school logo and not Pemda logo
        $year = \App\Models\AcademicYear::query()->create([
            'year_name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
        $teacher = \App\Models\User::query()->create([
            'name' => 'Guru Penguji 2',
            'email' => 'guru.test2@sekolah.sch.id',
            'role' => 'guru',
            'password' => 'password',
            'is_active' => true,
        ]);

        $authResponse = $this->actingAs($teacher)
            ->withSession([
                'active_academic_year_id' => $year->id,
            ])
            ->get(route('classes.index'));

        $authResponse->assertStatus(200);
        $authResponse->assertSee('alt="Logo ' . $school->school_name . '"', false);
        $authResponse->assertDontSee($pemdaDataUri);

        // Even when logo_right_data is null, static fallback is used and Pemda logo is never shown
        $school->update(['logo_right_data' => null]);
        $fallbackResponse = $this->get(route('classes.index'));
        $fallbackResponse->assertStatus(200);
        $fallbackResponse->assertSee('logo-smpn2-lemahabang.png');
        $fallbackResponse->assertDontSee($pemdaDataUri);
    }
}
