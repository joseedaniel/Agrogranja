<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PerfilControllerTest extends TestCase
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/perfil');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_profile_view(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/perfil');
        $response->assertStatus(200);
        $response->assertViewIs('pages.perfil');
        $response->assertViewHas('user');
        $response->assertViewHas('stats');
    }

    public function test_index_passes_correct_user_data(): void
    {
        $user = $this->createUser(['nombre' => 'Farmer Joe']);
        $response = $this->actingAsUser($user)->get('/perfil');
        $viewUser = $response->viewData('user');
        $this->assertEquals('Farmer Joe', $viewUser->nombre);
    }

    public function test_index_includes_record_counts_in_stats(): void
    {
        $user = $this->createUser();

        DB::table('cultivos')->insert([
            'usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'Test',
            'fecha_siembra' => '2024-01-01', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAsUser($user)->get('/perfil');
        $stats = $response->viewData('stats');

        $this->assertArrayHasKey('cultivos', $stats);
        $this->assertArrayHasKey('gastos', $stats);
        $this->assertArrayHasKey('tareas', $stats);
        $this->assertArrayHasKey('ingresos', $stats);
        $this->assertEquals(1, $stats['cultivos']);
    }

    public function test_index_accepts_tab_parameter(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/perfil?tab=seguridad');
        $response->assertViewHas('tab', 'seguridad');
    }

    public function test_index_defaults_to_perfil_tab(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/perfil');
        $response->assertViewHas('tab', 'perfil');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $response = $this->post('/perfil', ['nombre' => 'Test']);
        $response->assertRedirect(route('login'));
    }

    public function test_update_requires_nombre(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/perfil', []);
        $response->assertSessionHasErrors('nombre');
    }

    public function test_update_saves_profile_changes(): void
    {
        $user = $this->createUser(['nombre' => 'Old Name']);

        $response = $this->actingAsUser($user)->post('/perfil', [
            'nombre'      => 'New Name',
            'finca'       => 'Nueva Finca',
            'departamento'=> 'Valle',
            'municipio'   => 'Cali',
            'telefono'    => '3201234567',
        ]);

        $response->assertRedirect(route('perfil.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('usuarios', [
            'id'           => $user->id,
            'nombre'       => 'New Name',
            'nombre_finca' => 'Nueva Finca',
            'departamento' => 'Valle',
        ]);
    }

    public function test_update_refreshes_session_nombre(): void
    {
        $user = $this->createUser(['nombre' => 'Old Name']);

        $this->actingAsUser($user)->post('/perfil', ['nombre' => 'Updated Name']);

        $this->assertEquals('Updated Name', session('usuario_nombre'));
    }

    // ── Change Password ───────────────────────────────────────────────────────

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->post('/perfil/password', []);
        $response->assertRedirect(route('login'));
    }

    public function test_change_password_requires_password_actual(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/perfil/password', [
            'password_nueva'      => 'newpassword',
            'password_confirmar'  => 'newpassword',
        ]);
        $response->assertSessionHasErrors('password_actual');
    }

    public function test_change_password_requires_password_nueva(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/perfil/password', [
            'password_actual'    => 'password123',
            'password_confirmar' => 'newpassword',
        ]);
        $response->assertSessionHasErrors('password_nueva');
    }

    public function test_change_password_requires_password_nueva_min_6_chars(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/perfil/password', [
            'password_actual'    => 'password123',
            'password_nueva'     => 'abc',
            'password_confirmar' => 'abc',
        ]);
        $response->assertSessionHasErrors('password_nueva');
    }

    public function test_change_password_requires_passwords_to_match(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/perfil/password', [
            'password_actual'    => 'password123',
            'password_nueva'     => 'newpassword1',
            'password_confirmar' => 'newpassword2',
        ]);
        $response->assertSessionHasErrors('password_confirmar');
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('correctpassword')]);

        $response = $this->actingAsUser($user)->post('/perfil/password', [
            'password_actual'    => 'wrongpassword',
            'password_nueva'     => 'newpassword123',
            'password_confirmar' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('password_actual');
        // Password should not change
        $dbUser = DB::table('usuarios')->where('id', $user->id)->first();
        $this->assertTrue(Hash::check('correctpassword', $dbUser->password));
    }

    public function test_change_password_succeeds_with_correct_current_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAsUser($user)->post('/perfil/password', [
            'password_actual'    => 'oldpassword',
            'password_nueva'     => 'newpassword123',
            'password_confirmar' => 'newpassword123',
        ]);

        $response->assertRedirect(route('perfil.index', ['tab' => 'seguridad']));
        $response->assertSessionHas('msgType', 'success');

        $dbUser = DB::table('usuarios')->where('id', $user->id)->first();
        $this->assertTrue(Hash::check('newpassword123', $dbUser->password));
        $this->assertFalse(Hash::check('oldpassword', $dbUser->password));
    }
}
