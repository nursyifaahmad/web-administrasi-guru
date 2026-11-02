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
    }
}
