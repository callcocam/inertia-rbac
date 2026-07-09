<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Database\Seeders;

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Support\PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popula permissões do catálogo e as roles de sistema.
 *
 * super-admin recebe TODAS as permissões (embora também tenha bypass via
 * Gate::before); admin recebe um subconjunto curado. Publicável para o app estender.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $guard = config('rbac.guard');

        // Em modo teams, opera no contexto global (team null).
        if (config('rbac.teams.enabled')) {
            $registrar->setPermissionsTeamId(null);
        }

        // 1) Permissões do catálogo (idempotente).
        foreach (PermissionName::all() as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                [
                    'type' => PermissionName::typeFor($name),
                    'short_name' => PermissionName::shortNameFor($name),
                    'description' => PermissionName::descriptionFor($name),
                ],
            );
        }

        // 2) Roles de sistema por system_name.
        $superName = config('rbac.super_admin_role', 'super-admin');

        $superAdmin = Role::firstOrCreate(
            ['system_name' => $superName, 'guard_name' => $guard],
            ['name' => 'Super Administrador', 'type' => 'system'],
        );
        $superAdmin->syncPermissions(PermissionName::all());

        $admin = Role::firstOrCreate(
            ['system_name' => 'admin', 'guard_name' => $guard],
            ['name' => 'Administrador', 'type' => 'system'],
        );
        // admin gerencia papéis e permissões, mas não é onipotente por padrão.
        $admin->syncPermissions([
            PermissionName::ROLES_VIEW_ANY,
            PermissionName::ROLES_VIEW,
            PermissionName::ROLES_CREATE,
            PermissionName::ROLES_UPDATE,
            PermissionName::ROLES_DELETE,
            PermissionName::PERMISSIONS_VIEW_ANY,
            PermissionName::PERMISSIONS_VIEW,
        ]);

        $registrar->forgetCachedPermissions();
    }
}
