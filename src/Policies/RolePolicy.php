<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Policies;

use Callcocam\InertiaRbac\Concerns\ChecksRbacPermission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Support\PermissionName;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy de Role. Cada método delega para allowByContext com a permissão do catálogo.
 * O bypass de super-admin é feito globalmente por Gate::before (ver ServiceProvider).
 */
class RolePolicy
{
    use ChecksRbacPermission;

    /** Pode listar papéis? */
    public function viewAny(?Authenticatable $user): bool
    {
        return $this->allowByContext($user, PermissionName::ROLES_VIEW_ANY);
    }

    /** Pode visualizar um papel? */
    public function view(?Authenticatable $user, Role $role): bool
    {
        return $this->allowByContext($user, PermissionName::ROLES_VIEW);
    }

    /** Pode criar papéis? */
    public function create(?Authenticatable $user): bool
    {
        return $this->allowByContext($user, PermissionName::ROLES_CREATE);
    }

    /** Pode editar um papel? */
    public function update(?Authenticatable $user, Role $role): bool
    {
        return $this->allowByContext($user, PermissionName::ROLES_UPDATE);
    }

    /** Pode excluir um papel? */
    public function delete(?Authenticatable $user, Role $role): bool
    {
        return $this->allowByContext($user, PermissionName::ROLES_DELETE);
    }
}
