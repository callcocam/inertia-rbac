<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Controllers;

use Callcocam\InertiaRbac\Http\Concerns\InteractsWithDeferredIndex;
use Callcocam\InertiaRbac\Http\Requests\StoreRoleRequest;
use Callcocam\InertiaRbac\Http\Requests\UpdateRoleRequest;
use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Support\PermissionName;
use Callcocam\InertiaRbac\Support\RbacType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de papéis (roles). Renderiza componentes Vue cujo caminho vem de
 * config('rbac.views.roles.*') — o pacote não traz .vue. Mutações redirecionam
 * para config('rbac.redirect.roles').
 */
class RoleController extends Controller
{
    use InteractsWithDeferredIndex;

    /** Lista paginada de papéis (paginator deferido) + prop can. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $filters = ['search' => $request->string('search')->value() ?: null];

        return $this->renderDeferredIndex(
            config('rbac.views.roles.index'),
            'roles',
            fn (): LengthAwarePaginator => $this->rolesPaginator($filters),
            [
                'filters' => $filters,
                'can' => [
                    'create' => $request->user()?->can('create', Role::class) ?? false,
                ],
            ],
        );
    }

    /** Formulário de criação. */
    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render(config('rbac.views.roles.form'), [
            'role' => null,
            'types' => RbacType::all(),
            'permissions' => $this->availablePermissions(),
        ]);
    }

    /** Persiste um novo papel e sincroniza suas permissões. */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'system_name' => $data['system_name'] ?? null,
            'type' => $data['type'],
            'guard_name' => config('rbac.guard'),
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route(config('rbac.redirect.roles'))
            ->with('success', 'Papel criado com sucesso.');
    }

    /** Formulário de edição de um papel gerenciável. */
    public function edit(Role $role): Response
    {
        $this->ensureManageable($role);
        $this->authorize('update', $role);

        $role->load('permissions:id,name');

        return Inertia::render(config('rbac.views.roles.form'), [
            'role' => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'system_name' => $role->system_name,
                'type' => $role->type,
                'is_protected' => $this->isProtected($role),
                'permissions' => $role->permissions->pluck('name'),
            ],
            'types' => RbacType::all(),
            'permissions' => $this->availablePermissions(),
        ]);
    }

    /** Atualiza um papel. Papéis protegidos só podem ser renomeados. */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureManageable($role);

        $data = $request->validated();

        if ($this->isProtected($role)) {
            $role->update(['name' => $data['name']]);
        } else {
            $role->update([
                'name' => $data['name'],
                'type' => $data['type'],
            ]);
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return redirect()
            ->route(config('rbac.redirect.roles'))
            ->with('success', 'Papel atualizado com sucesso.');
    }

    /** Exclui um papel, bloqueando papéis protegidos ou em uso. */
    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureManageable($role);
        $this->authorize('delete', $role);

        if ($this->isProtected($role)) {
            return back()->withErrors(['role' => 'Este papel é protegido e não pode ser excluído.']);
        }

        if ($this->roleInUse($role)) {
            return back()->withErrors(['role' => 'Este papel está atribuído a usuários e não pode ser excluído.']);
        }

        $role->delete();

        return redirect()
            ->route(config('rbac.redirect.roles'))
            ->with('success', 'Papel excluído com sucesso.');
    }

    /** Monta o paginator de papéis escopado por guard (e team global quando teams). */
    protected function rolesPaginator(array $filters): LengthAwarePaginator
    {
        $protected = (array) config('rbac.protected_roles', []);

        return Role::query()
            ->where('guard_name', config('rbac.guard'))
            ->when(
                config('rbac.teams.enabled'),
                fn ($q) => $q->whereNull(config('permission.column_names.team_foreign_key'))
            )
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
            )
            ->withCount('permissions')
            ->orderBy('name')
            ->paginate(config('rbac.per_page', 15))
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'system_name' => $role->system_name,
                'type' => $role->type,
                'permissions_count' => $role->permissions_count,
                'is_protected' => in_array($role->system_name, $protected, true),
                'created_at' => $role->created_at?->toDateTimeString(),
            ]);
    }

    /** Catálogo de permissões disponíveis para o picker do formulário. */
    protected function availablePermissions(): array
    {
        return Permission::query()
            ->where('guard_name', config('rbac.guard'))
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'short_name', 'description'])
            ->map(fn (Permission $permission): array => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'type' => $permission->type,
                'short_name' => $permission->short_name ?: PermissionName::shortNameFor($permission->name),
                'description' => $permission->description ?: PermissionName::descriptionFor($permission->name),
            ])
            ->all();
    }

    /** true se o papel é protegido (system_name em config('rbac.protected_roles')). */
    protected function isProtected(Role $role): bool
    {
        return in_array($role->system_name, (array) config('rbac.protected_roles', []), true);
    }

    /** Garante que o papel pertence ao guard atual (e ao contexto global quando teams). */
    protected function ensureManageable(Role $role): void
    {
        abort_unless($role->guard_name === config('rbac.guard'), 404);

        if (config('rbac.teams.enabled')) {
            $teamForeignKey = config('permission.column_names.team_foreign_key');
            abort_unless($role->getAttribute($teamForeignKey) === null, 404);
        }
    }

    /** true se o papel está atribuído a algum modelo (não pode ser excluído). */
    protected function roleInUse(Role $role): bool
    {
        $pivot = config('permission.table_names.model_has_roles');
        $key = config('permission.column_names.role_pivot_key') ?? 'role_id';

        return DB::connection(config('rbac.connection'))
            ->table($pivot)
            ->where($key, $role->getKey())
            ->exists();
    }
}
