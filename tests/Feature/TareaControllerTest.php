<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TareaControllerTest extends TestCase
{
    private function createTarea(int $userId, array $overrides = []): object
    {
        $id = DB::table('tareas')->insertGetId(array_merge([
            'usuario_id' => $userId,
            'titulo'     => 'Regar cultivos',
            'tipo'       => 'riego',
            'fecha'      => now()->addDays(1)->toDateString(),
            'prioridad'  => 'media',
            'completada' => 0,
            'created_at' => now(),
        ], $overrides));
        return DB::table('tareas')->where('id', $id)->first();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/calendario');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_view_for_authenticated_user(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/calendario');
        $response->assertStatus(200);
        $response->assertViewIs('pages.calendario');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/tareas', []);
        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_titulo(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/tareas', [
            'tipo'  => 'riego',
            'fecha' => now()->toDateString(),
        ]);
        $response->assertSessionHasErrors('titulo');
    }

    public function test_store_creates_tarea_and_redirects(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/tareas', [
            'titulo'    => 'Fumigar lote norte',
            'tipo'      => 'fumigacion',
            'fecha'     => '2024-07-15',
            'hora'      => '08:00',
            'prioridad' => 'alta',
            'notas'     => 'Usar producto X',
        ]);

        $response->assertRedirect(route('calendario.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('tareas', [
            'usuario_id' => $user->id,
            'titulo'     => 'Fumigar lote norte',
            'tipo'       => 'fumigacion',
            'prioridad'  => 'alta',
        ]);
    }

    public function test_store_assigns_tarea_to_current_user(): void
    {
        $user1 = $this->createUser(['email' => 'u1@example.com']);
        $user2 = $this->createUser(['email' => 'u2@example.com']);

        $this->actingAsUser($user1)->post('/tareas', [
            'titulo' => 'Mi tarea',
            'fecha'  => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('tareas', ['usuario_id' => $user1->id, 'titulo' => 'Mi tarea']);
        $this->assertDatabaseMissing('tareas', ['usuario_id' => $user2->id, 'titulo' => 'Mi tarea']);
    }

    public function test_store_defaults_tipo_to_otro(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/tareas', [
            'titulo' => 'Tarea sin tipo',
        ]);

        $tarea = DB::table('tareas')->where('usuario_id', $user->id)->first();
        $this->assertEquals('otro', $tarea->tipo);
    }

    public function test_store_defaults_prioridad_to_media(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/tareas', [
            'titulo' => 'Tarea sin prioridad',
        ]);

        $tarea = DB::table('tareas')->where('usuario_id', $user->id)->first();
        $this->assertEquals('media', $tarea->prioridad);
    }

    public function test_store_sets_hora_to_null_when_empty(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/tareas', [
            'titulo' => 'Tarea sin hora',
            'hora'   => '',
        ]);

        $tarea = DB::table('tareas')->where('usuario_id', $user->id)->first();
        $this->assertNull($tarea->hora);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $response = $this->post('/tareas/1', []);
        $response->assertRedirect(route('login'));
    }

    public function test_update_requires_titulo(): void
    {
        $user = $this->createUser();
        $tarea = $this->createTarea($user->id);

        $response = $this->actingAsUser($user)->post("/tareas/{$tarea->id}", [
            'tipo'  => 'cosecha',
            'fecha' => '2024-08-01',
        ]);
        $response->assertSessionHasErrors('titulo');
    }

    public function test_update_modifies_own_tarea(): void
    {
        $user = $this->createUser();
        $tarea = $this->createTarea($user->id, ['titulo' => 'Old Title']);

        $response = $this->actingAsUser($user)->post("/tareas/{$tarea->id}", [
            'titulo'    => 'New Title',
            'tipo'      => 'cosecha',
            'fecha'     => '2024-09-01',
            'prioridad' => 'alta',
        ]);

        $response->assertRedirect(route('calendario.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('tareas', [
            'id'     => $tarea->id,
            'titulo' => 'New Title',
            'tipo'   => 'cosecha',
        ]);
    }

    public function test_update_cannot_modify_another_users_tarea(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $tarea = $this->createTarea($owner->id, ['titulo' => 'Original']);

        $this->actingAsUser($attacker)->post("/tareas/{$tarea->id}", [
            'titulo' => 'Hacked',
            'tipo'   => 'riego',
            'fecha'  => '2024-08-01',
        ]);

        $this->assertDatabaseHas('tareas', ['id' => $tarea->id, 'titulo' => 'Original']);
    }

    // ── Completar ─────────────────────────────────────────────────────────────

    public function test_completar_requires_authentication(): void
    {
        $response = $this->post('/tareas/1/completar');
        $response->assertRedirect(route('login'));
    }

    public function test_completar_marks_own_tarea_as_done(): void
    {
        $user = $this->createUser();
        $tarea = $this->createTarea($user->id, ['completada' => 0]);

        $response = $this->actingAsUser($user)->post("/tareas/{$tarea->id}/completar");

        $response->assertRedirect(route('calendario.index'));
        $response->assertSessionHas('msgType', 'success');

        $updated = DB::table('tareas')->where('id', $tarea->id)->first();
        $this->assertEquals(1, $updated->completada);
        $this->assertNotNull($updated->fecha_completada);
    }

    public function test_completar_cannot_mark_another_users_tarea(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $tarea = $this->createTarea($owner->id, ['completada' => 0]);

        $this->actingAsUser($attacker)->post("/tareas/{$tarea->id}/completar");

        $updated = DB::table('tareas')->where('id', $tarea->id)->first();
        $this->assertEquals(0, $updated->completada);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->post('/tareas/1/delete');
        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_own_tarea(): void
    {
        $user = $this->createUser();
        $tarea = $this->createTarea($user->id);

        $response = $this->actingAsUser($user)->post("/tareas/{$tarea->id}/delete");

        $response->assertRedirect(route('calendario.index'));
        $response->assertSessionHas('msgType', 'warning');
        $this->assertDatabaseMissing('tareas', ['id' => $tarea->id]);
    }

    public function test_destroy_cannot_delete_another_users_tarea(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $tarea = $this->createTarea($owner->id);

        $this->actingAsUser($attacker)->post("/tareas/{$tarea->id}/delete");

        $this->assertDatabaseHas('tareas', ['id' => $tarea->id]);
    }
}
