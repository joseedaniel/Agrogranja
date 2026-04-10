<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class CultivoControllerTest extends TestCase
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/cultivos');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_cultivos_for_authenticated_user(): void
    {
        $user = $this->createUser();
        DB::table('cultivos')->insert([
            'usuario_id'    => $user->id,
            'tipo'          => 'Maíz',
            'nombre'        => 'Lote Norte',
            'fecha_siembra' => '2024-01-15',
            'estado'        => 'activo',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->actingAsUser($user)->get('/cultivos');
        $response->assertStatus(200);
        $response->assertViewIs('pages.cultivos');
        $response->assertViewHas('cultivos');
    }

    public function test_index_does_not_show_other_users_cultivos(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com']);

        DB::table('cultivos')->insert([
            'usuario_id'    => $user2->id,
            'tipo'          => 'Yuca',
            'nombre'        => 'Lote de User2',
            'fecha_siembra' => '2024-01-15',
            'estado'        => 'activo',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->actingAsUser($user1)->get('/cultivos');
        $response->assertStatus(200);
        $cultivos = $response->viewData('cultivos');
        $this->assertCount(0, $cultivos);
    }

    public function test_index_filters_by_search_query(): void
    {
        $user = $this->createUser();
        DB::table('cultivos')->insert([
            ['usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'Lote Maíz', 'fecha_siembra' => '2024-01-01', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()],
            ['usuario_id' => $user->id, 'tipo' => 'Yuca', 'nombre' => 'Lote Yuca', 'fecha_siembra' => '2024-01-01', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAsUser($user)->get('/cultivos?q=Maíz');
        $response->assertStatus(200);
        $cultivos = $response->viewData('cultivos');
        $this->assertCount(1, $cultivos);
        $this->assertEquals('Lote Maíz', $cultivos->first()->nombre);
    }

    public function test_index_filters_by_estado(): void
    {
        $user = $this->createUser();
        DB::table('cultivos')->insert([
            ['usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'Activo', 'fecha_siembra' => '2024-01-01', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()],
            ['usuario_id' => $user->id, 'tipo' => 'Yuca', 'nombre' => 'Cosechado', 'fecha_siembra' => '2024-01-01', 'estado' => 'cosechado', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAsUser($user)->get('/cultivos?estado=activo');
        $cultivos = $response->viewData('cultivos');
        $this->assertCount(1, $cultivos);
        $this->assertEquals('activo', $cultivos->first()->estado);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/cultivos', ['tipo' => 'Maíz', 'nombre' => 'Lote 1']);
        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_tipo(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/cultivos', ['nombre' => 'Lote 1']);
        $response->assertSessionHasErrors('tipo');
    }

    public function test_store_requires_nombre(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/cultivos', ['tipo' => 'Maíz']);
        $response->assertSessionHasErrors('nombre');
    }

    public function test_store_creates_cultivo_and_redirects(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/cultivos', [
            'tipo'          => 'Maíz',
            'nombre'        => 'Lote Norte',
            'fecha_siembra' => '2024-03-15',
            'area'          => '2.5',
            'unidad'        => 'hectareas',
            'estado'        => 'activo',
            'notas'         => 'Buena tierra',
        ]);

        $response->assertRedirect(route('cultivos.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('cultivos', [
            'usuario_id' => $user->id,
            'tipo'       => 'Maíz',
            'nombre'     => 'Lote Norte',
        ]);
    }

    public function test_store_assigns_cultivo_to_current_user(): void
    {
        $user1 = $this->createUser(['email' => 'u1@example.com']);
        $user2 = $this->createUser(['email' => 'u2@example.com']);

        $this->actingAsUser($user1)->post('/cultivos', [
            'tipo'   => 'Plátano',
            'nombre' => 'Mi Plátano',
        ]);

        $this->assertDatabaseHas('cultivos', ['usuario_id' => $user1->id, 'nombre' => 'Mi Plátano']);
        $this->assertDatabaseMissing('cultivos', ['usuario_id' => $user2->id, 'nombre' => 'Mi Plátano']);
    }

    public function test_store_uses_default_values_when_optional_fields_absent(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/cultivos', [
            'tipo'   => 'Frijol',
            'nombre' => 'Lote Frijol',
        ]);

        $cultivo = DB::table('cultivos')->where('usuario_id', $user->id)->first();
        $this->assertEquals('hectareas', $cultivo->unidad);
        $this->assertEquals('activo', $cultivo->estado);
        $this->assertNull($cultivo->area);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $response = $this->post('/cultivos/1', ['tipo' => 'Maíz', 'nombre' => 'Updated']);
        $response->assertRedirect(route('login'));
    }

    public function test_update_requires_tipo(): void
    {
        $user = $this->createUser();
        $id = DB::table('cultivos')->insertGetId([
            'usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'Old',
            'fecha_siembra' => '2024-01-01', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAsUser($user)->post("/cultivos/{$id}", ['nombre' => 'New']);
        $response->assertSessionHasErrors('tipo');
    }

    public function test_update_modifies_own_cultivo(): void
    {
        $user = $this->createUser();
        $id = DB::table('cultivos')->insertGetId([
            'usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'Old Name',
            'fecha_siembra' => '2024-01-01', 'estado' => 'activo', 'unidad' => 'hectareas',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAsUser($user)->post("/cultivos/{$id}", [
            'tipo'          => 'Yuca',
            'nombre'        => 'New Name',
            'fecha_siembra' => '2024-06-01',
            'unidad'        => 'hectareas',
            'estado'        => 'cosechado',
        ]);

        $response->assertRedirect(route('cultivos.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('cultivos', ['id' => $id, 'nombre' => 'New Name', 'tipo' => 'Yuca']);
    }

    public function test_update_cannot_modify_another_users_cultivo(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $id = DB::table('cultivos')->insertGetId([
            'usuario_id' => $owner->id, 'tipo' => 'Maíz', 'nombre' => 'Owners Cultivo',
            'fecha_siembra' => '2024-01-01', 'estado' => 'activo', 'unidad' => 'hectareas',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUser($attacker)->post("/cultivos/{$id}", [
            'tipo'          => 'Yuca',
            'nombre'        => 'Hacked',
            'fecha_siembra' => '2024-06-01',
            'unidad'        => 'hectareas',
        ]);

        // Original data should be unchanged
        $this->assertDatabaseHas('cultivos', ['id' => $id, 'nombre' => 'Owners Cultivo']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->post('/cultivos/1/delete');
        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_own_cultivo(): void
    {
        $user = $this->createUser();
        $id = DB::table('cultivos')->insertGetId([
            'usuario_id' => $user->id, 'tipo' => 'Maíz', 'nombre' => 'To Delete',
            'fecha_siembra' => '2024-01-01', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAsUser($user)->post("/cultivos/{$id}/delete");

        $response->assertRedirect(route('cultivos.index'));
        $response->assertSessionHas('msgType', 'warning');
        $this->assertDatabaseMissing('cultivos', ['id' => $id]);
    }

    public function test_destroy_cannot_delete_another_users_cultivo(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $id = DB::table('cultivos')->insertGetId([
            'usuario_id' => $owner->id, 'tipo' => 'Maíz', 'nombre' => 'Protected',
            'fecha_siembra' => '2024-01-01', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUser($attacker)->post("/cultivos/{$id}/delete");

        $this->assertDatabaseHas('cultivos', ['id' => $id]);
    }
}
