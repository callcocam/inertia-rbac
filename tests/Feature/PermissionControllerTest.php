<?php

declare(strict_types=1);

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Support\PermissionName;
use Callcocam\InertiaRbac\Tests\Fixtures\User;

/** Cria um usuário super-admin (bypass via Gate::before). */
function admin(): User
{
    $role = Role::create([
        'name' => 'Super Administrador',
        'system_name' => 'super-admin',
        'type' => 'system',
        'guard_name' => 'web',
    ]);

    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('secret'),
    ]);

    return $user->assignRole($role);
}

it('sincroniza o catálogo criando as permissões faltantes', function (): void {
    expect(Permission::count())->toBe(0);

    $this->actingAs(admin())
        ->post(route('rbac.permissions.sync'))
        ->assertRedirect(route('rbac.permissions.index'));

    foreach (PermissionName::all() as $name) {
        $this->assertDatabaseHas(config('permission.table_names.permissions'), [
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
});

it('mantém o name imutável no update (só type/short_name/description mudam)', function (): void {
    $permission = Permission::create([
        'name' => 'posts.view',
        'type' => 'default',
        'guard_name' => 'web',
    ]);

    $this->actingAs(admin())
        ->put(route('rbac.permissions.update', $permission->getKey()), [
            'name' => 'posts.hacked',
            'type' => 'system',
            'short_name' => 'Ver posts',
            'description' => 'Permite ver posts.',
        ])
        ->assertRedirect(route('rbac.permissions.index'));

    $permission->refresh();

    expect($permission->name)->toBe('posts.view')
        ->and($permission->type)->toBe('system')
        ->and($permission->short_name)->toBe('Ver posts');
});
