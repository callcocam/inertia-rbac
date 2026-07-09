<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Policies;

use Callcocam\InertiaRbac\Concerns\ChecksRbacPermission;
use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Support\PermissionName;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy de Permission. Delega para allowByContext com a permissão do catálogo.
 * O bypass de super-admin é feito globalmente por Gate::before (ver ServiceProvider).
 */
class PermissionPolicy
{
    use ChecksRbacPermission;

    /** Pode listar permissões? */
    public function viewAny(?Authenticatable $user): bool
    {
        return $this->allowByContext($user, PermissionName::PERMISSIONS_VIEW_ANY);
    }

    /** Pode visualizar uma permissão? */
    public function view(?Authenticatable $user, Permission $permission): bool
    {
        return $this->allowByContext($user, PermissionName::PERMISSIONS_VIEW);
    }

    /** Pode criar permissões (inclui o sync do catálogo)? */
    public function create(?Authenticatable $user): bool
    {
        return $this->allowByContext($user, PermissionName::PERMISSIONS_CREATE);
    }

    /** Pode editar uma permissão? */
    public function update(?Authenticatable $user, Permission $permission): bool
    {
        return $this->allowByContext($user, PermissionName::PERMISSIONS_UPDATE);
    }

    /** Pode excluir uma permissão? */
    public function delete(?Authenticatable $user, Permission $permission): bool
    {
        return $this->allowByContext($user, PermissionName::PERMISSIONS_DELETE);
    }
}
