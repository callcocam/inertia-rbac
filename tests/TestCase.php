<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Tests;

use Callcocam\InertiaRbac\InertiaRbacServiceProvider;
use Callcocam\InertiaRbac\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Providers necessários no ambiente de teste.
     */
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            InertiaServiceProvider::class,
            InertiaRbacServiceProvider::class,
        ];
    }

    /**
     * Ambiente: sqlite em memória, guard web apontando para o User de teste.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('rbac.enabled', true);
        $app['config']->set('rbac.guard', 'web');

        // O pacote não traz .vue; a UI vive no projeto. Não exigir o arquivo nos testes.
        $app['config']->set('inertia.testing.ensure_pages_exist', false);

        // Root view para o Inertia renderizar a página inicial (HTML) nos testes.
        $app['config']->set('view.paths', array_merge(
            (array) $app['config']->get('view.paths', []),
            [__DIR__.'/Fixtures/views'],
        ));
    }

    /**
     * Cria a tabela users (ULID) e roda a migration do pacote (o stub).
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $migration = include __DIR__.'/../database/migrations/create_rbac_permission_tables.php.stub';
        $migration->up();
    }
}
