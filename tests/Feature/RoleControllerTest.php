<?php

declare(strict_types=1);

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia as Assert;

/** Cria um usuário super-admin (bypass via Gate::before). */
function superAdmin(): User
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

it('renderiza o componente configurado no index com a prop can', function (): void {
    config(['rbac.views.roles.index' => 'custom/roles/Listing']);

    $this->actingAs(superAdmin())
        ->get(route('rbac.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('custom/roles/Listing')
            ->where('can.create', true)
            ->has('filters')
        );
});

it('carrega o paginator deferido de papéis em partial reload', function (): void {
    Role::create(['name' => 'Editor', 'type' => 'default', 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'rbac/roles/Index',
            'X-Inertia-Partial-Data' => 'roles',
        ])
        ->get(route('rbac.roles.index'))
        ->assertOk()
        ->assertJsonPath('component', 'rbac/roles/Index')
        ->assertJsonPath('props.roles.current_page', 1)
        ->assertJsonFragment(['name' => 'Editor']);
});

it('cria um papel e sincroniza suas permissões', function (): void {
    Permission::create(['name' => 'posts.view', 'type' => 'default', 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->post(route('rbac.roles.store'), [
            'name' => 'Editor',
            'type' => 'default',
            'permissions' => ['posts.view'],
        ])
        ->assertRedirect(route('rbac.roles.index'));

    $role = Role::where('name', 'Editor')->firstOrFail();

    expect($role->hasPermissionTo('posts.view'))->toBeTrue();
});

it('bloqueia a exclusão de um papel protegido', function (): void {
    config(['rbac.protected_roles' => ['admin']]);

    $role = Role::create([
        'name' => 'Administrador',
        'system_name' => 'admin',
        'type' => 'system',
        'guard_name' => 'web',
    ]);

    $this->actingAs(superAdmin())
        ->delete(route('rbac.roles.destroy', $role->getKey()))
        ->assertSessionHasErrors('role');

    expect(Role::whereKey($role->getKey())->exists())->toBeTrue();
});

it('bloqueia a exclusão de um papel em uso', function (): void {
    $role = Role::create(['name' => 'Membro', 'type' => 'default', 'guard_name' => 'web']);

    $member = User::create([
        'name' => 'Membro',
        'email' => 'member@example.com',
        'password' => bcrypt('secret'),
    ]);
    $member->assignRole($role);

    $this->actingAs(superAdmin())
        ->delete(route('rbac.roles.destroy', $role->getKey()))
        ->assertSessionHasErrors('role');

    expect(Role::whereKey($role->getKey())->exists())->toBeTrue();
});

it('ao atualizar um papel protegido, apenas renomeia (type não muda)', function (): void {
    config(['rbac.protected_roles' => ['admin']]);

    $role = Role::create([
        'name' => 'Administrador',
        'system_name' => 'admin',
        'type' => 'system',
        'guard_name' => 'web',
    ]);

    $this->actingAs(superAdmin())
        ->put(route('rbac.roles.update', $role->getKey()), [
            'name' => 'Administradores',
            'type' => 'default',
        ])
        ->assertRedirect(route('rbac.roles.index'));

    $role->refresh();

    expect($role->name)->toBe('Administradores')
        ->and($role->type)->toBe('system');
});
