<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    /**
     * Override setUpTraits to run migrations BEFORE DatabaseTransactions starts its
     * wrapping transaction (migrate:fresh calls VACUUM which cannot run inside a transaction).
     */
    protected function setUpTraits(): array
    {
        // Ensure the SQLite test database file exists
        $dbPath = config('database.connections.sqlite.database');
        if ($dbPath && $dbPath !== ':memory:' && !file_exists($dbPath)) {
            touch($dbPath);
        }

        // Run migrate:fresh once per test run, before any DB transaction starts
        if (!RefreshDatabaseState::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            RefreshDatabaseState::$migrated = true;
        }

        return parent::setUpTraits();
    }

    /**
     * Create a test user and return their record.
     */
    protected function createUser(array $overrides = []): object
    {
        $data = array_merge([
            'nombre'                => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => Hash::make('password123'),
            'nombre_finca'          => 'Finca Test',
            'departamento'          => 'Antioquia',
            'municipio'             => 'Medellín',
            'telefono'              => '3001234567',
            'onboarding_completado' => 1,
            'activo'                => 1,
            'created_at'            => now(),
            'updated_at'            => now(),
        ], $overrides);

        $id = DB::table('usuarios')->insertGetId($data);
        return DB::table('usuarios')->where('id', $id)->first();
    }

    /**
     * Simulate an authenticated session for a given user.
     */
    protected function actingAsUser(object $user): static
    {
        return $this->withSession([
            'usuario_id'     => $user->id,
            'usuario_nombre' => $user->nombre,
        ]);
    }

    /**
     * Skip this test when the DB connection is not MySQL (some queries use MySQL-specific functions).
     */
    protected function skipIfNotMySQL(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('This test requires a MySQL database (uses MySQL-specific SQL functions).');
        }
    }
}
