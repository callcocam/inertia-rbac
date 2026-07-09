<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Trait de checagem para policies.
 *
 * Centraliza a regra de rollout (config('rbac.enabled')) e a verificação de
 * "qualquer uma das permissões". As policies do pacote (e as do app) usam isto.
 */
trait ChecksRbacPermission
{
    /**
     * Libera se o RBAC estiver desligado; caso contrário, retorna true quando o
     * usuário tem QUALQUER uma das permissões informadas.
     *
     * @param  array<int, string>|string  $permissions
     */
    protected function allowByContext(?Authenticatable $user, array|string $permissions): bool
    {
        if (! config('rbac.enabled', true)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $gate = Gate::forUser($user);

        foreach ((array) $permissions as $permission) {
            if ($gate->check($permission)) {
                return true;
            }
        }

        return false;
    }
}
