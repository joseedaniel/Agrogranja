<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ReporteControllerTest extends TestCase
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/reportes');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_view_for_authenticated_user(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/reportes');
        $response->assertStatus(200);
        $response->assertViewIs('pages.reportes');
    }

    public function test_index_passes_current_year_by_default(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/reportes');
        $response->assertViewHas('anio', now()->year);
    }

    public function test_index_accepts_custom_year(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/reportes?anio=2023');
        $response->assertViewHas('anio', '2023');
    }

    public function test_index_passes_default_tab_resumen(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/reportes');
        $response->assertViewHas('tab', 'resumen');
    }

    public function test_index_accepts_custom_tab(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/reportes?tab=gastos');
        $response->assertViewHas('tab', 'gastos');
    }
}
