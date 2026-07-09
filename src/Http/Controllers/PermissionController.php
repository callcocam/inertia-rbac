<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Controllers;

use Callcocam\InertiaRbac\Http\Concerns\InteractsWithDeferredIndex;
use Callcocam\InertiaRbac\Http\Requests\StorePermissionRequest;
use Callcocam\InertiaRbac\Http\Requests\UpdatePermissionRequest;
use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Support\PermissionName;
use Callcocam\InertiaRbac\Support\RbacType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * CRUD de permissões + sync do catálogo. O name (slug) é imutável no update.
 * Renderiza componentes de config('rbac.views.permissions.*').
 */
class PermissionController extends Controller
{
    use InteractsWithDeferredIndex;

    /** Lista paginada de permissões + contagem de faltantes do catálogo. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        $filters = [
            'search' => $request->string('search')->value() ?: null,
            'type' => $request->string('type')->value() ?: null,
        ];

        return $this->renderDeferredIndex(
            config('rbac.views.permissions.index'),
            'permissions',
            fn (): LengthAwarePaginator => $this->permissionsPaginator($filters),
            [
                'filters' => $filters,
                'types' => RbacType::all(),
                'missing_count' => $this->missingCount(),
                'can' => [
                    'create' => $request->user()?->can('create', Permission::class) ?? false,
                ],
            ],
        );
    }

    /** Formulário de criação. */
    public function create(): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render(config('rbac.views.permissions.form'), [
            'permission' => null,
            'types' => RbacType::all(),
        ]);
    }

    /** Persiste uma nova permissão. */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Permission::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'short_name' => $data['short_name'] ?? PermissionName::shortNameFor($data['name']),
            'description' => $data['description'] ?? PermissionName::descriptionFor($data['name']),
            'guard_name' => config('rbac.guard'),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route(config('rbac.redirect.permissions'))
            ->with('success', 'Permissão criada com sucesso.');
    }

    /** Formulário de edição. */
    public function edit(Permission $permission): Response
    {
        $this->ensureManageable($permission);
        $this->authorize('update', $permission);

        return Inertia::render(config('rbac.views.permissions.form'), [
            'permission' => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'type' => $permission->type,
                'short_name' => $permission->short_name,
                'description' => $permission->description,
                'is_protected' => $this->isProtected($permission),
            ],
            'types' => RbacType::all(),
        ]);
    }

    /** Atualiza a permissão. O name é imutável: só type/short_name/description. */
    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->ensureManageable($permission);

        $data = $request->validated();

        $permission->update([
            'type' => $data['type'],
            'short_name' => $data['short_name'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route(config('rbac.redirect.permissions'))
            ->with('success', 'Permissão atualizada com sucesso.');
    }

    /** Exclui uma permissão, bloqueando as protegidas. */
    public function destroy(Permission $permission): RedirectResponse
    {
        $this->ensureManageable($permission);
        $this->authorize('delete', $permission);

        if ($this->isProtected($permission)) {
            return back()->withErrors(['permission' => 'Esta permissão é protegida e não pode ser excluída.']);
        }

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route(config('rbac.redirect.permissions'))
            ->with('success', 'Permissão excluída com sucesso.');
    }

    /**
     * Sincroniza o catálogo (PermissionName::all()) com o banco: cria as faltantes
     * com type/metadata e limpa o cache do Spatie.
     */
    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $guard = config('rbac.guard');
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();

        $created = 0;
        foreach (PermissionName::all() as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            Permission::create([
                'name' => $name,
                'type' => PermissionName::typeFor($name),
                'short_name' => PermissionName::shortNameFor($name),
                'description' => PermissionName::descriptionFor($name),
                'guard_name' => $guard,
            ]);

            $created++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route(config('rbac.redirect.permissions'))
            ->with('success', "{$created} permissão(ões) sincronizada(s) do catálogo.");
    }

    /** Paginator de permissões escopado por guard, com filtros de busca e tipo. */
    protected function permissionsPaginator(array $filters): LengthAwarePaginator
    {
        $protected = (array) config('rbac.protected_permissions', []);

        return Permission::query()
            ->where('guard_name', config('rbac.guard'))
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(fn ($qq) => $qq
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%"))
            )
            ->when(
                $filters['type'] ?? null,
                fn ($q, $type) => $q->where('type', $type)
            )
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(config('rbac.per_page', 15))
            ->withQueryString()
            ->through(fn (Permission $permission): array => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'type' => $permission->type,
                'short_name' => $permission->short_name,
                'description' => $permission->description,
                'is_protected' => in_array($permission->name, $protected, true),
            ]);
    }

    /** Quantas permissões do catálogo ainda não existem no banco. */
    protected function missingCount(): int
    {
        $existing = Permission::query()
            ->where('guard_name', config('rbac.guard'))
            ->pluck('name')
            ->all();

        return count(array_diff(PermissionName::all(), $existing));
    }

    /** true se a permissão é protegida (name em config('rbac.protected_permissions')). */
    protected function isProtected(Permission $permission): bool
    {
        return in_array($permission->name, (array) config('rbac.protected_permissions', []), true);
    }

    /** Garante que a permissão pertence ao guard atual. */
    protected function ensureManageable(Permission $permission): void
    {
        abort_unless($permission->guard_name === config('rbac.guard'), 404);
    }
}
