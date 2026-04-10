<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthSessionMiddlewareTest extends TestCase
{
    private array $protectedRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->protectedRoutes = [
            ['GET',  '/dashboard'],
            ['GET',  '/cultivos'],
            ['GET',  '/gastos'],
            ['GET',  '/ingresos'],
            ['GET',  '/animales'],
            ['GET',  '/calendario'],
            ['GET',  '/reportes'],
            ['GET',  '/perfil'],
            ['GET',  '/onboarding'],
        ];
    }

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_cultivos(): void
    {
        $response = $this->get('/cultivos');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_gastos(): void
    {
        $response = $this->get('/gastos');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_ingresos(): void
    {
        $response = $this->get('/ingresos');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_animales(): void
    {
        $response = $this->get('/animales');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_calendario(): void
    {
        $response = $this->get('/calendario');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_reportes(): void
    {
        $response = $this->get('/reportes');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_perfil(): void
    {
        $response = $this->get('/perfil');
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_post_to_cultivos_store_redirects(): void
    {
        $response = $this->post('/cultivos', ['tipo' => 'Maíz', 'nombre' => 'Lote 1']);
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_post_to_gastos_store_redirects(): void
    {
        $response = $this->post('/gastos', []);
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_post_to_logout_still_redirects_to_login(): void
    {
        // Logout is public (no middleware), but session is empty so login redirect still happens
        $response = $this->post('/logout');
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_protected_routes(): void
    {
        $user = $this->createUser();

        // Cultivos index has no MySQL-specific SQL so it should work with SQLite
        $response = $this->actingAsUser($user)->get('/cultivos');
        $response->assertStatus(200);
    }
}
