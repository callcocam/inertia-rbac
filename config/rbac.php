<?php

/*
|--------------------------------------------------------------------------
| Configuração do pacote callcocam/inertia-rbac
|--------------------------------------------------------------------------
|
| ATENÇÃO: tabelas, colunas, models base, cache, teams core e guards são
| controlados pelo config/permission.php do Spatie. Este arquivo cobre
| SOMENTE o que é específico deste pacote (views Vue, rollout, roles
| protegidas, redirects e rotas). Não duplique aqui o que o Spatie já resolve.
|
*/

return [
    // Liga/desliga a checagem real de permissões nas policies.
    // false = tudo liberado (útil no rollout gradual em produção).
    'enabled' => env('RBAC_ENABLED', true),

    // Guard padrão usado por roles/permissions.
    'guard' => env('RBAC_GUARD', 'web'),

    // Conexão de banco onde ficam roles/permissions/pivots.
    // null = conexão padrão do app. Preencha para isolar (ex.: 'landlord').
    'connection' => env('RBAC_CONNECTION', null),

    // Suporte a teams (multi-tenant). Ligado aqui, o provider espelha para
    // config('permission.teams') e config('permission.column_names.team_foreign_key').
    'teams' => [
        'enabled' => env('RBAC_TEAMS', false),
        'foreign_key' => env('RBAC_TEAMS_FK', 'tenant_id'),
    ],

    // Roles de sistema (por system_name) que NÃO podem ser apagadas/renomeadas livremente.
    'protected_roles' => ['super-admin', 'admin'],

    // Permissões de sistema (por name) que não podem ser apagadas.
    'protected_permissions' => [],

    // system_name da role que recebe Gate::before (acesso total).
    'super_admin_role' => 'super-admin',

    // Catálogo de permissões DO APP, mesclado a PermissionName::all().
    // Ex.: ['products.viewAny', 'products.create', 'orders.view'].
    // Deixe vazio para usar só as permissões do pacote (roles/permissions).
    'permissions' => [],

    // Mapa opcional recurso => type usado por RbacType::fromName().
    // Ex.: ['products' => 'catalog', 'orders' => 'sales'].
    'type_map' => [],

    // Caminhos dos componentes Vue renderizados pelos controllers.
    // O PROJETO cria esses componentes; aqui só apontamos onde estão.
    'views' => [
        'roles' => [
            'index' => 'rbac/roles/Index',
            'form' => 'rbac/roles/Form',
        ],
        'permissions' => [
            'index' => 'rbac/permissions/Index',
            'form' => 'rbac/permissions/Form',
        ],
    ],

    // Prefixo, nome e middleware das rotas.
    'routes' => [
        'prefix' => 'admin',
        'name' => 'rbac.',
        'middleware' => ['web', 'auth'],
    ],

    // Onde os controllers redirecionam após store/update/destroy.
    'redirect' => [
        'roles' => 'rbac.roles.index',
        'permissions' => 'rbac.permissions.index',
    ],

    // Paginação padrão das telas de index.
    'per_page' => 15,
];
