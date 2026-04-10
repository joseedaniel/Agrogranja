<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class GastoControllerTest extends TestCase
{
    private function createCultivo(int $userId): object
    {
        $id = DB::table('cultivos')->insertGetId([
            'usuario_id'    => $userId,
            'tipo'          => 'Maíz',
            'nombre'        => 'Lote Test',
            'fecha_siembra' => '2024-01-01',
            'estado'        => 'activo',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return DB::table('cultivos')->where('id', $id)->first();
    }

    private function createGasto(int $userId, array $overrides = []): object
    {
        $id = DB::table('gastos')->insertGetId(array_merge([
            'usuario_id'  => $userId,
            'categoria'   => 'Semillas',
            'descripcion' => 'Semillas de maíz',
            'valor'       => 50000,
            'fecha'       => now()->toDateString(),
            'created_at'  => now(),
        ], $overrides));
        return DB::table('gastos')->where('id', $id)->first();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/gastos');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_view_for_authenticated_user(): void
    {
        $this->skipIfNotMySQL();

        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/gastos');
        $response->assertStatus(200);
        $response->assertViewIs('pages.gastos');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/gastos', []);
        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_categoria(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/gastos', [
            'descripcion' => 'Semillas',
            'valor'       => 50000,
        ]);
        $response->assertSessionHasErrors('categoria');
    }

    public function test_store_requires_descripcion(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/gastos', [
            'categoria' => 'Semillas',
            'valor'     => 50000,
        ]);
        $response->assertSessionHasErrors('descripcion');
    }

    public function test_store_requires_valor(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/gastos', [
            'categoria'   => 'Semillas',
            'descripcion' => 'Compra semillas',
        ]);
        $response->assertSessionHasErrors('valor');
    }

    public function test_store_requires_valor_numeric(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/gastos', [
            'categoria'   => 'Semillas',
            'descripcion' => 'Compra semillas',
            'valor'       => 'not-a-number',
        ]);
        $response->assertSessionHasErrors('valor');
    }

    public function test_store_creates_gasto_and_redirects(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/gastos', [
            'categoria'   => 'Fertilizantes',
            'descripcion' => 'Urea 50kg',
            'valor'       => 120000,
            'fecha'       => '2024-05-10',
            'proveedor'   => 'Agrotienda',
        ]);

        $response->assertRedirect(route('gastos.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('gastos', [
            'usuario_id'  => $user->id,
            'categoria'   => 'Fertilizantes',
            'descripcion' => 'Urea 50kg',
            'valor'       => 120000,
        ]);
    }

    public function test_store_assigns_gasto_to_current_user(): void
    {
        $user1 = $this->createUser(['email' => 'u1@example.com']);
        $user2 = $this->createUser(['email' => 'u2@example.com']);

        $this->actingAsUser($user1)->post('/gastos', [
            'categoria'   => 'Combustible',
            'descripcion' => 'Gasolina',
            'valor'       => 30000,
        ]);

        $this->assertDatabaseHas('gastos', ['usuario_id' => $user1->id, 'descripcion' => 'Gasolina']);
        $this->assertDatabaseMissing('gastos', ['usuario_id' => $user2->id, 'descripcion' => 'Gasolina']);
    }

    public function test_store_can_link_to_cultivo(): void
    {
        $user = $this->createUser();
        $cultivo = $this->createCultivo($user->id);

        $this->actingAsUser($user)->post('/gastos', [
            'categoria'   => 'Semillas',
            'descripcion' => 'Semillas premium',
            'valor'       => 75000,
            'cultivo_id'  => $cultivo->id,
        ]);

        $this->assertDatabaseHas('gastos', [
            'usuario_id'  => $user->id,
            'cultivo_id'  => $cultivo->id,
            'descripcion' => 'Semillas premium',
        ]);
    }

    public function test_store_sets_null_for_empty_cultivo_id(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/gastos', [
            'categoria'   => 'Semillas',
            'descripcion' => 'Sin cultivo',
            'valor'       => 10000,
            'cultivo_id'  => '',
        ]);

        $gasto = DB::table('gastos')->where('usuario_id', $user->id)->first();
        $this->assertNull($gasto->cultivo_id);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $response = $this->post('/gastos/1', []);
        $response->assertRedirect(route('login'));
    }

    public function test_update_requires_categoria(): void
    {
        $user = $this->createUser();
        $gasto = $this->createGasto($user->id);

        $response = $this->actingAsUser($user)->post("/gastos/{$gasto->id}", [
            'descripcion' => 'Updated',
            'valor'       => 60000,
        ]);
        $response->assertSessionHasErrors('categoria');
    }

    public function test_update_modifies_own_gasto(): void
    {
        $user = $this->createUser();
        $gasto = $this->createGasto($user->id);

        $response = $this->actingAsUser($user)->post("/gastos/{$gasto->id}", [
            'categoria'   => 'Herramientas',
            'descripcion' => 'Machetes',
            'valor'       => 80000,
            'fecha'       => '2024-06-01',
        ]);

        $response->assertRedirect(route('gastos.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('gastos', [
            'id'          => $gasto->id,
            'categoria'   => 'Herramientas',
            'descripcion' => 'Machetes',
            'valor'       => 80000,
        ]);
    }

    public function test_update_cannot_modify_another_users_gasto(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $gasto = $this->createGasto($owner->id, ['descripcion' => 'Original']);

        $this->actingAsUser($attacker)->post("/gastos/{$gasto->id}", [
            'categoria'   => 'Otros',
            'descripcion' => 'Hacked',
            'valor'       => 1,
        ]);

        $this->assertDatabaseHas('gastos', ['id' => $gasto->id, 'descripcion' => 'Original']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->post('/gastos/1/delete');
        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_own_gasto(): void
    {
        $user = $this->createUser();
        $gasto = $this->createGasto($user->id);

        $response = $this->actingAsUser($user)->post("/gastos/{$gasto->id}/delete");

        $response->assertRedirect(route('gastos.index'));
        $response->assertSessionHas('msgType', 'warning');
        $this->assertDatabaseMissing('gastos', ['id' => $gasto->id]);
    }

    public function test_destroy_cannot_delete_another_users_gasto(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $gasto = $this->createGasto($owner->id);

        $this->actingAsUser($attacker)->post("/gastos/{$gasto->id}/delete");

        $this->assertDatabaseHas('gastos', ['id' => $gasto->id]);
    }
}
