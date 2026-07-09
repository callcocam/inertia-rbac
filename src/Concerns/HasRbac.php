<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Traits\HasRoles;

/**
 * Trait para o model User do app.
 *
 * Reexporta HasRoles (Spatie) + HasUlids. No modo single-database é tudo o que
 * o app precisa. No modo teams cross-database, o projeto pode sobrescrever
 * roles()/permissions() para fixar conexão/pivot (ver README).
 */
trait HasRbac
{
    use HasRoles;
    use HasUlids;

    /**
     * Verifica se o usuário possui a role de super-admin configurada,
     * comparando por system_name (slug estável), NÃO pelo name do Spatie.
     */
    public function isSuperAdmin(): bool
    {
        $superName = config('rbac.super_admin_role');

        return $this->roles()
            ->where('system_name', $superName)
            ->exists();
    }
}
