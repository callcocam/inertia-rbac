<?php

declare(strict_types=1);

use Callcocam\InertiaRbac\Support\PermissionName;
use Callcocam\InertiaRbac\Support\RbacType;

it('mescla as permissões do pacote com as do app via config', function (): void {
    config(['rbac.permissions' => ['products.viewAny', 'products.create']]);

    $all = PermissionName::all();

    expect($all)->toContain(PermissionName::ROLES_VIEW_ANY)
        ->toContain('products.viewAny')
        ->toContain('products.create');
});

it('classifica roles/permissions como system e o resto como default', function (): void {
    expect(RbacType::fromName('roles.viewAny'))->toBe(RbacType::SYSTEM)
        ->and(RbacType::fromName('permissions.create'))->toBe(RbacType::SYSTEM)
        ->and(RbacType::fromName('products.view'))->toBe(RbacType::DEFAULT);
});

it('respeita o type_map do app', function (): void {
    config(['rbac.type_map' => ['products' => 'catalog']]);

    expect(RbacType::fromName('products.view'))->toBe('catalog')
        ->and(RbacType::all())->toContain('catalog');
});

it('gera metadata PT-BR por CRUD com override para sync', function (): void {
    expect(PermissionName::shortNameFor('roles.viewAny'))->toBe('Listar papéis')
        ->and(PermissionName::descriptionFor('roles.create'))->toBe('Permite criar papéis.')
        ->and(PermissionName::shortNameFor('permissions.sync'))->toBe('Sincronizar permissões');
});
