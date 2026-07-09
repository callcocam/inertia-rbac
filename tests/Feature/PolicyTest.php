<?php

declare(strict_types=1);

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;

function makeUser(string $email = 'user@example.com'): User
{
    return User::create([
        'name' => 'Fulano',
        'email' => $email,
        'password' => bcrypt('secret'),
    ]);
}

it('libera tudo quando o RBAC está desligado', function (): void {
    config(['rbac.enabled' => false]);

    $user = makeUser();

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', Permission::class))->toBeTrue();
});

it('nega usuário sem permissão quando o RBAC está ligado', function (): void {
    $user = makeUser();

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeFalse();
});

it('permite usuário que possui a permissão', function (): void {
    Permission::create(['name' => 'roles.viewAny', 'type' => 'system', 'guard_name' => 'web']);

    $user = makeUser();
    $user->givePermissionTo('roles.viewAny');

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue();
});

it('super-admin faz tudo via Gate::before (por system_name)', function (): void {
    $role = Role::create([
        'name' => 'Super Administrador',
        'system_name' => 'super-admin',
        'type' => 'system',
        'guard_name' => 'web',
    ]);

    $user = makeUser();
    $user->assignRole($role);

    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', Role::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', Permission::class))->toBeTrue();
});
