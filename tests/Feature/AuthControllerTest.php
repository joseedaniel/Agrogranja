<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthControllerTest extends TestCase
{
    // ── Welcome ──────────────────────────────────────────────────────────────

    public function test_welcome_redirects_authenticated_user_to_dashboard(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_welcome_shows_view_for_guests(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewIs('auth.welcome');
    }

    // ── Show Login ────────────────────────────────────────────────────────────

    public function test_show_login_redirects_authenticated_user(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/login');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_show_login_shows_form_for_guests(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_requires_email(): void
    {
        $response = $this->post('/login', ['password' => 'password123']);
        $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_valid_email(): void
    {
        $response = $this->post('/login', ['email' => 'not-an-email', 'password' => 'password123']);
        $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_password(): void
    {
        $response = $this->post('/login', ['email' => 'test@example.com']);
        $response->assertSessionHasErrors('password');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $this->createUser(['email' => 'user@example.com']);
        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrongpassword']);
        $response->assertSessionHasErrors('email');
    }

    public function test_login_fails_for_non_existent_user(): void
    {
        $response = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password123']);
        $response->assertSessionHasErrors('email');
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->createUser(['email' => 'inactive@example.com', 'activo' => 0]);
        $response = $this->post('/login', ['email' => 'inactive@example.com', 'password' => 'password123']);
        $response->assertSessionHasErrors('email');
    }

    public function test_login_succeeds_with_correct_credentials_and_redirects_to_dashboard(): void
    {
        $this->createUser([
            'email'                 => 'user@example.com',
            'password'              => Hash::make('password123'),
            'onboarding_completado' => 1,
        ]);

        $response = $this->post('/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals('user@example.com', DB::table('usuarios')->where('email', 'user@example.com')->first()->email);
    }

    public function test_login_redirects_to_onboarding_if_not_completed(): void
    {
        $this->createUser([
            'email'                 => 'newuser@example.com',
            'password'              => Hash::make('password123'),
            'onboarding_completado' => 0,
        ]);

        $response = $this->post('/login', [
            'email'    => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('onboarding'));
    }

    public function test_login_stores_session_data(): void
    {
        $user = $this->createUser([
            'email'                 => 'user@example.com',
            'password'              => Hash::make('password123'),
            'onboarding_completado' => 1,
        ]);

        $this->post('/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals($user->id, session('usuario_id'));
        $this->assertEquals($user->nombre, session('usuario_nombre'));
    }

    public function test_login_is_case_insensitive_for_email(): void
    {
        $this->createUser([
            'email'                 => 'user@example.com',
            'password'              => Hash::make('password123'),
            'onboarding_completado' => 1,
        ]);

        $response = $this->post('/login', [
            'email'    => '  USER@EXAMPLE.COM  ',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    // ── Show Register ─────────────────────────────────────────────────────────

    public function test_show_register_redirects_authenticated_user(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/register');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_show_register_shows_form_for_guests(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function test_register_requires_nombre(): void
    {
        $response = $this->post('/register', [
            'email'    => 'new@example.com',
            'password' => 'password123',
        ]);
        $response->assertSessionHasErrors('nombre');
    }

    public function test_register_requires_nombre_min_2_chars(): void
    {
        $response = $this->post('/register', [
            'nombre'   => 'A',
            'email'    => 'new@example.com',
            'password' => 'password123',
        ]);
        $response->assertSessionHasErrors('nombre');
    }

    public function test_register_requires_email(): void
    {
        $response = $this->post('/register', [
            'nombre'   => 'Test User',
            'password' => 'password123',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_register_requires_valid_email(): void
    {
        $response = $this->post('/register', [
            'nombre'   => 'Test User',
            'email'    => 'invalid-email',
            'password' => 'password123',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_register_requires_password(): void
    {
        $response = $this->post('/register', [
            'nombre' => 'Test User',
            'email'  => 'new@example.com',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_register_requires_password_min_6_chars(): void
    {
        $response = $this->post('/register', [
            'nombre'   => 'Test User',
            'email'    => 'new@example.com',
            'password' => '123',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_register_fails_for_duplicate_email(): void
    {
        $this->createUser(['email' => 'existing@example.com']);

        $response = $this->post('/register', [
            'nombre'   => 'Another User',
            'email'    => 'existing@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals(1, DB::table('usuarios')->where('email', 'existing@example.com')->count());
    }

    public function test_register_creates_user_and_redirects_to_onboarding(): void
    {
        $response = $this->post('/register', [
            'nombre'      => 'New Farmer',
            'email'       => 'farmer@example.com',
            'password'    => 'password123',
            'finca'       => 'Mi Finca',
            'departamento'=> 'Cundinamarca',
            'municipio'   => 'Bogotá',
            'telefono'    => '3109876543',
        ]);

        $response->assertRedirect(route('onboarding'));
        $this->assertDatabaseHas('usuarios', ['email' => 'farmer@example.com']);
    }

    public function test_register_stores_password_as_hash(): void
    {
        $this->post('/register', [
            'nombre'   => 'New User',
            'email'    => 'secure@example.com',
            'password' => 'plainpassword',
        ]);

        $user = DB::table('usuarios')->where('email', 'secure@example.com')->first();
        $this->assertNotEquals('plainpassword', $user->password);
        $this->assertTrue(Hash::check('plainpassword', $user->password));
    }

    public function test_register_sets_session_for_new_user(): void
    {
        $this->post('/register', [
            'nombre'   => 'Session User',
            'email'    => 'session@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotNull(session('usuario_id'));
        $this->assertEquals('Session User', session('usuario_nombre'));
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_clears_session_and_redirects(): void
    {
        $user = $this->createUser();

        $response = $this->actingAsUser($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertNull(session('usuario_id'));
    }

    // ── Onboarding ────────────────────────────────────────────────────────────

    public function test_onboarding_requires_authentication(): void
    {
        $response = $this->get('/onboarding');
        $response->assertRedirect(route('login'));
    }

    public function test_onboarding_shows_view(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/onboarding');
        $response->assertStatus(200);
        $response->assertViewIs('auth.onboarding');
    }

    public function test_onboarding_complete_marks_user_as_done_and_redirects(): void
    {
        $user = $this->createUser(['onboarding_completado' => 0]);

        $response = $this->actingAsUser($user)->post('/onboarding');

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(1, DB::table('usuarios')->where('id', $user->id)->value('onboarding_completado'));
    }
}
