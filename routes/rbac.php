<?php

use Callcocam\InertiaRbac\Http\Controllers\PermissionController;
use Callcocam\InertiaRbac\Http\Controllers\RoleController;
use Callcocam\InertiaRbac\Http\Middleware\SetPermissionTeamContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do RBAC (nomeadas para o Wayfinder introspectar)
|--------------------------------------------------------------------------
|
| Prefixo, middleware e nome vêm de config('rbac.routes.*'). Os SUFIXOS dos
| nomes (roles.index, permissions.sync, ...) são estáveis: os imports Wayfinder
| no app dependem deles. Quando teams está ligado, o middleware de contexto é
| adicionado ao grupo.
|
*/

$middleware = (array) config('rbac.routes.middleware', ['web', 'auth']);

if (config('rbac.teams.enabled')) {
    $middleware[] = SetPermissionTeamContext::class;
}

Route::prefix(config('rbac.routes.prefix', 'admin'))
    ->middleware($middleware)
    ->group(function () {
        // sync ANTES do resource, para não ser capturado por permissions/{permission}
        Route::post('permissions/sync', [PermissionController::class, 'sync'])
            ->name('rbac.permissions.sync');

        Route::resource('roles', RoleController::class)
            ->except(['show'])
            ->names('rbac.roles');

        Route::resource('permissions', PermissionController::class)
            ->except(['show'])
            ->names('rbac.permissions');
    });
