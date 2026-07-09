<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac;

use Callcocam\InertiaRbac\Console\SyncPermissionsCommand;
use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Policies\PermissionPolicy;
use Callcocam\InertiaRbac\Policies\RolePolicy;
use Callcocam\InertiaRbac\Support\PermissionResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider do pacote.
 *
 * Alinha config('rbac.*') com config('permission.*') do Spatie (models, teams),
 * publica config/migrations/seeder/stubs, carrega as rotas, registra policies +
 * Gate::before de super-admin e o command rbac:sync.
 */
class InertiaRbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rbac.php', 'rbac');

        // Reaproveita a config do Spatie: apenas espelha o que é específico do RBAC.
        $this->alignSpatieConfig();

        $this->app->singleton(PermissionResolver::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/rbac.php');

        $this->registerPolicies();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->commands([SyncPermissionsCommand::class]);
        }
    }

    /**
     * Espelha config('rbac.*') para config('permission.*'), sem duplicar o resto.
     */
    protected function alignSpatieConfig(): void
    {
        config([
            'permission.models.role' => Role::class,
            'permission.models.permission' => Permission::class,
        ]);

        if (config('rbac.teams.enabled')) {
            config([
                'permission.teams' => true,
                'permission.column_names.team_foreign_key' => config('rbac.teams.foreign_key', 'tenant_id'),
            ]);
        }
    }

    /**
     * Registra as policies e o bypass de super-admin (por system_name, não hasRole).
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Gate::before(function (?Authenticatable $user): ?bool {
            if (! $user || ! method_exists($user, 'roles')) {
                return null;
            }

            $superName = config('rbac.super_admin_role');

            return $user->roles()->where('system_name', $superName)->exists() ? true : null;
        });
    }

    /**
     * Define os assets publicáveis por tag.
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/rbac.php' => config_path('rbac.php'),
        ], 'rbac-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_rbac_permission_tables.php.stub' => $this->migrationPath('create_rbac_permission_tables'),
        ], 'rbac-migrations');

        $this->publishes([
            __DIR__.'/../database/seeders/RbacSeeder.php' => database_path('seeders/RbacSeeder.php'),
        ], 'rbac-seeders');

        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/inertia-rbac'),
        ], 'rbac-stubs');
    }

    /**
     * Caminho de destino da migration publicada, com timestamp incremental.
     */
    protected function migrationPath(string $name): string
    {
        return database_path('migrations/'.date('Y_m_d_His').'_'.$name.'.php');
    }
}
