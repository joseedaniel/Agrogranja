<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AnimalControllerTest extends TestCase
{
    private function createAnimal(int $userId, array $overrides = []): object
    {
        $id = DB::table('animales')->insertGetId(array_merge([
            'usuario_id'    => $userId,
            'especie'       => 'Gallinas',
            'nombre_lote'   => 'Lote A',
            'cantidad'      => 50,
            'estado'        => 'activo',
            'unidad_peso'   => 'kg',
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $overrides));
        return DB::table('animales')->where('id', $id)->first();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/animales');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_view_for_authenticated_user(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/animales');
        $response->assertStatus(200);
        $response->assertViewIs('pages.animales');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/animales', []);
        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_especie(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/animales', []);
        $response->assertSessionHasErrors('especie');
    }

    public function test_store_creates_animal_and_redirects(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/animales', [
            'especie'       => 'Cerdos',
            'nombre_lote'   => 'Lote Cerdos B',
            'cantidad'      => 20,
            'estado'        => 'activo',
            'peso_promedio' => 80,
            'unidad_peso'   => 'kg',
            'notas'         => 'Raza Landrace',
        ]);

        $response->assertRedirect(route('animales.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('animales', [
            'usuario_id' => $user->id,
            'especie'    => 'Cerdos',
            'cantidad'   => 20,
        ]);
    }

    public function test_store_assigns_animal_to_current_user(): void
    {
        $user1 = $this->createUser(['email' => 'u1@example.com']);
        $user2 = $this->createUser(['email' => 'u2@example.com']);

        $this->actingAsUser($user1)->post('/animales', [
            'especie'  => 'Cabras',
            'cantidad' => 10,
        ]);

        $this->assertDatabaseHas('animales', ['usuario_id' => $user1->id, 'especie' => 'Cabras']);
        $this->assertDatabaseMissing('animales', ['usuario_id' => $user2->id, 'especie' => 'Cabras']);
    }

    public function test_store_defaults_cantidad_to_one_when_absent(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/animales', [
            'especie' => 'Caballos',
        ]);

        $animal = DB::table('animales')->where('usuario_id', $user->id)->first();
        $this->assertEquals(1, $animal->cantidad);
    }

    public function test_store_defaults_estado_to_activo(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/animales', [
            'especie' => 'Peces',
        ]);

        $animal = DB::table('animales')->where('usuario_id', $user->id)->first();
        $this->assertEquals('activo', $animal->estado);
    }

    public function test_store_defaults_unidad_peso_to_kg(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/animales', [
            'especie' => 'Patos',
        ]);

        $animal = DB::table('animales')->where('usuario_id', $user->id)->first();
        $this->assertEquals('kg', $animal->unidad_peso);
    }

    public function test_store_sets_peso_promedio_to_null_when_empty(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/animales', [
            'especie'       => 'Ovejas',
            'peso_promedio' => '',
        ]);

        $animal = DB::table('animales')->where('usuario_id', $user->id)->first();
        $this->assertNull($animal->peso_promedio);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $response = $this->post('/animales/1', []);
        $response->assertRedirect(route('login'));
    }

    public function test_update_requires_especie(): void
    {
        $user = $this->createUser();
        $animal = $this->createAnimal($user->id);

        $response = $this->actingAsUser($user)->post("/animales/{$animal->id}", [
            'cantidad' => 30,
        ]);
        $response->assertSessionHasErrors('especie');
    }

    public function test_update_modifies_own_animal(): void
    {
        $user = $this->createUser();
        $animal = $this->createAnimal($user->id, ['especie' => 'Gallinas', 'cantidad' => 50]);

        $response = $this->actingAsUser($user)->post("/animales/{$animal->id}", [
            'especie'     => 'Gallinas',
            'cantidad'    => 75,
            'estado'      => 'vendido',
            'unidad_peso' => 'kg',
        ]);

        $response->assertRedirect(route('animales.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('animales', [
            'id'       => $animal->id,
            'cantidad' => 75,
            'estado'   => 'vendido',
        ]);
    }

    public function test_update_cannot_modify_another_users_animal(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $animal = $this->createAnimal($owner->id, ['especie' => 'Cerdos', 'cantidad' => 20]);

        $this->actingAsUser($attacker)->post("/animales/{$animal->id}", [
            'especie'     => 'Cerdos',
            'cantidad'    => 999,
            'unidad_peso' => 'kg',
        ]);

        $this->assertDatabaseHas('animales', ['id' => $animal->id, 'cantidad' => 20]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->post('/animales/1/delete');
        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_own_animal(): void
    {
        $user = $this->createUser();
        $animal = $this->createAnimal($user->id);

        $response = $this->actingAsUser($user)->post("/animales/{$animal->id}/delete");

        $response->assertRedirect(route('animales.index'));
        $response->assertSessionHas('msgType', 'warning');
        $this->assertDatabaseMissing('animales', ['id' => $animal->id]);
    }

    public function test_destroy_cannot_delete_another_users_animal(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $animal = $this->createAnimal($owner->id);

        $this->actingAsUser($attacker)->post("/animales/{$animal->id}/delete");

        $this->assertDatabaseHas('animales', ['id' => $animal->id]);
    }
}
