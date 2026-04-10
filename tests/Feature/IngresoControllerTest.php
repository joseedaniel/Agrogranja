<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class IngresoControllerTest extends TestCase
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

    private function createIngreso(int $userId, array $overrides = []): object
    {
        $id = DB::table('ingresos')->insertGetId(array_merge([
            'usuario_id'  => $userId,
            'descripcion' => 'Venta de maíz',
            'valor_total' => 300000,
            'fecha'       => now()->toDateString(),
            'created_at'  => now(),
        ], $overrides));
        return DB::table('ingresos')->where('id', $id)->first();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/ingresos');
        $response->assertRedirect(route('login'));
    }

    public function test_index_shows_view_for_authenticated_user(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->get('/ingresos');
        $response->assertStatus(200);
        $response->assertViewIs('pages.ingresos');
        $response->assertViewHas('ingresos');
        $response->assertViewHas('totalMes');
        $response->assertViewHas('totalAnio');
        $response->assertViewHas('cultivos');
    }

    public function test_index_shows_only_current_users_ingresos(): void
    {
        $user1 = $this->createUser(['email' => 'u1@example.com']);
        $user2 = $this->createUser(['email' => 'u2@example.com']);

        $this->createIngreso($user2->id, ['descripcion' => 'Venta de user2']);

        $response = $this->actingAsUser($user1)->get('/ingresos');
        $ingresos = $response->viewData('ingresos');
        $this->assertCount(0, $ingresos);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/ingresos', []);
        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_descripcion(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/ingresos', [
            'valor_total' => 100000,
        ]);
        $response->assertSessionHasErrors('descripcion');
    }

    public function test_store_requires_valor_total(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/ingresos', [
            'descripcion' => 'Venta',
        ]);
        $response->assertSessionHasErrors('valor_total');
    }

    public function test_store_requires_valor_total_numeric(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/ingresos', [
            'descripcion' => 'Venta',
            'valor_total' => 'not-a-number',
        ]);
        $response->assertSessionHasErrors('valor_total');
    }

    public function test_store_creates_ingreso_and_redirects(): void
    {
        $user = $this->createUser();
        $response = $this->actingAsUser($user)->post('/ingresos', [
            'descripcion' => 'Venta cosecha maíz',
            'valor_total' => 500000,
            'fecha'       => '2024-06-20',
            'comprador'   => 'Comprador SA',
        ]);

        $response->assertRedirect(route('ingresos.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('ingresos', [
            'usuario_id'  => $user->id,
            'descripcion' => 'Venta cosecha maíz',
            'valor_total' => 500000,
        ]);
    }

    public function test_store_calculates_total_from_quantity_and_unit_price(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/ingresos', [
            'descripcion'     => 'Venta por unidades',
            'cantidad'        => 100,
            'precio_unitario' => 2000,
            'valor_total'     => 999999, // Should be overridden by 100*2000=200000
        ]);

        $ingreso = DB::table('ingresos')->where('usuario_id', $user->id)->first();
        $this->assertEquals(200000, $ingreso->valor_total);
    }

    public function test_store_uses_valor_total_when_no_quantity_and_price(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user)->post('/ingresos', [
            'descripcion' => 'Venta directa',
            'valor_total' => 350000,
        ]);

        $ingreso = DB::table('ingresos')->where('usuario_id', $user->id)->first();
        $this->assertEquals(350000, $ingreso->valor_total);
    }

    public function test_store_assigns_to_current_user(): void
    {
        $user1 = $this->createUser(['email' => 'u1@example.com']);
        $user2 = $this->createUser(['email' => 'u2@example.com']);

        $this->actingAsUser($user1)->post('/ingresos', [
            'descripcion' => 'Mi venta',
            'valor_total' => 100000,
        ]);

        $this->assertDatabaseHas('ingresos', ['usuario_id' => $user1->id]);
        $this->assertDatabaseMissing('ingresos', ['usuario_id' => $user2->id]);
    }

    public function test_store_can_link_to_cultivo(): void
    {
        $user = $this->createUser();
        $cultivo = $this->createCultivo($user->id);

        $this->actingAsUser($user)->post('/ingresos', [
            'descripcion' => 'Venta de mi cultivo',
            'valor_total' => 200000,
            'cultivo_id'  => $cultivo->id,
        ]);

        $this->assertDatabaseHas('ingresos', [
            'usuario_id' => $user->id,
            'cultivo_id' => $cultivo->id,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $response = $this->post('/ingresos/1', []);
        $response->assertRedirect(route('login'));
    }

    public function test_update_modifies_own_ingreso(): void
    {
        $user = $this->createUser();
        $ingreso = $this->createIngreso($user->id);

        $response = $this->actingAsUser($user)->post("/ingresos/{$ingreso->id}", [
            'descripcion' => 'Venta actualizada',
            'valor_total' => 450000,
            'fecha'       => '2024-07-01',
        ]);

        $response->assertRedirect(route('ingresos.index'));
        $response->assertSessionHas('msgType', 'success');
        $this->assertDatabaseHas('ingresos', [
            'id'          => $ingreso->id,
            'descripcion' => 'Venta actualizada',
            'valor_total' => 450000,
        ]);
    }

    public function test_update_cannot_modify_another_users_ingreso(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $ingreso = $this->createIngreso($owner->id, ['descripcion' => 'Original']);

        $this->actingAsUser($attacker)->post("/ingresos/{$ingreso->id}", [
            'descripcion' => 'Hacked',
            'valor_total' => 1,
        ]);

        $this->assertDatabaseHas('ingresos', ['id' => $ingreso->id, 'descripcion' => 'Original']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->post('/ingresos/1/delete');
        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_own_ingreso(): void
    {
        $user = $this->createUser();
        $ingreso = $this->createIngreso($user->id);

        $response = $this->actingAsUser($user)->post("/ingresos/{$ingreso->id}/delete");

        $response->assertRedirect(route('ingresos.index'));
        $response->assertSessionHas('msgType', 'warning');
        $this->assertDatabaseMissing('ingresos', ['id' => $ingreso->id]);
    }

    public function test_destroy_cannot_delete_another_users_ingreso(): void
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $attacker = $this->createUser(['email' => 'attacker@example.com']);

        $ingreso = $this->createIngreso($owner->id);

        $this->actingAsUser($attacker)->post("/ingresos/{$ingreso->id}/delete");

        $this->assertDatabaseHas('ingresos', ['id' => $ingreso->id]);
    }
}
