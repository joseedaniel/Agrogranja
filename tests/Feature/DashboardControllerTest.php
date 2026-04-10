<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DashboardControllerTest extends TestCase
{
    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_dashboard_for_authenticated_user(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('pages.dashboard');
    }

    public function test_index_passes_user_data_to_view(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser(['nombre' => 'Agricultor Test']);
        $response = $this->actingAsUser($user)->get('/dashboard');
        $viewUser = $response->viewData('user');
        $this->assertEquals('Agricultor Test', $viewUser->nombre);
    }

    public function test_index_passes_stats_to_view(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/dashboard');

        $response->assertViewHas('cultivosActivos');
        $response->assertViewHas('gastosMes');
        $response->assertViewHas('ingresosMes');
        $response->assertViewHas('tareasPend');
        $response->assertViewHas('tareasHoy');
        $response->assertViewHas('recentCultivos');
    }

    public function test_index_counts_only_active_cultivos(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        DB::table('cultivos')->insert([
            ['usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'Activo', 'fecha_siembra' => '2024-01-01', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()],
            ['usuario_id' => $user->id, 'tipo' => 'Yuca', 'nombre' => 'Cosechado', 'fecha_siembra' => '2024-01-01', 'estado' => 'cosechado', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAsUser($user)->get('/dashboard');
        $this->assertEquals(1, $response->viewData('cultivosActivos'));
    }
}
