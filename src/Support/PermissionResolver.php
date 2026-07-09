<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Ponte fina entre a UI (menu, botões) e o Gate.
 *
 * Usada server-side para decidir o que mostrar/esconder sem vazar a lista de
 * permissões para o cliente. Registrada como singleton no service provider.
 */
class PermissionResolver
{
    /**
     * Retorna true se o usuário pode a ability (via Gate). false se não houver usuário.
     */
    public function allows(?Authenticatable $user, string $ability, mixed $subject = null): bool
    {
        if (! $user) {
            return false;
        }

        $gate = Gate::forUser($user);

        return $subject !== null
            ? $gate->check($ability, $subject)
            : $gate->check($ability);
    }

    /**
     * Inverso de allows(): true quando o usuário NÃO pode a ability.
     */
    public function denies(?Authenticatable $user, string $ability, mixed $subject = null): bool
    {
        return ! $this->allows($user, $ability, $subject);
    }
}
