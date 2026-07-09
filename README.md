# callcocam/inertia-rbac

RBAC (roles & permissions) para **Laravel + Inertia + Vue**, construído sobre o
[`spatie/laravel-permission`](https://github.com/spatie/laravel-permission) com:

- **ULID** em roles, permissions, pivots e morph keys.
- **Catálogo de permissões como fonte única** (`PermissionName`), com metadata PT-BR.
- **Controllers no pacote** (CRUD + `sync`) — o app **não** copia controller.
- **Páginas Vue no projeto** — o pacote **não traz `.vue`**; você cria os componentes
  no visual do seu app e as ações usam os controllers do pacote via **Wayfinder**.
- **Teams (multi-tenant) opcional**, reaproveitando a config de teams do Spatie.
- **Rollout gradual** (`rbac.enabled`) e **super-admin** por `system_name`.

> Filosofia: **visual do projeto, ações do pacote.** O HTML/estilo é 100% seu; o
> endpoint que processa cada ação é o controller do pacote.

## Requisitos

- PHP **8.3+**
- Laravel **12+/13+**
- Inertia **v2+** (`@inertiajs/vue3`)
- `spatie/laravel-permission` **^8.0** (instalado como dependência)

---

## Instalação

```bash
composer require callcocam/inertia-rbac

# Config do pacote (só o específico do RBAC — o resto reusa o config do Spatie)
php artisan vendor:publish --tag=rbac-config

# Migrations com ULID (config-driven)
php artisan vendor:publish --tag=rbac-migrations

# (opcional) publicar o config do Spatie, se quiser editá-lo à mão
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag=permission-config

php artisan migrate

php artisan rbac:sync   # popula as permissões do catálogo

# roles de sistema (super-admin, admin) — publique o seeder e rode:
php artisan vendor:publish --tag=rbac-seeders
php artisan db:seed --class="Database\Seeders\RbacSeeder"
```

### Reaproveitando a config do Spatie

O pacote alinha automaticamente o `config/permission.php` do Spatie no boot:

- `permission.models.role` → `Callcocam\InertiaRbac\Models\Role`
- `permission.models.permission` → `Callcocam\InertiaRbac\Models\Permission`
- quando `rbac.teams.enabled = true`: liga `permission.teams` e define
  `permission.column_names.team_foreign_key` a partir de `rbac.teams.foreign_key`.

Ou seja: **tabelas, colunas, cache, guards e teams core continuam no Spatie**; o
`config/rbac.php` só cobre views Vue, rollout, roles protegidas, redirects e rotas.

---

## Preparar o model User

```php
use Callcocam\InertiaRbac\Concerns\HasRbac;

class User extends Authenticatable
{
    use HasRbac; // HasRoles (Spatie) + HasUlids + isSuperAdmin()
}
```

> O `id` do User deve ser **ULID** (as morph keys do RBAC são ULID).

---

## Declarar as permissões do seu app

O catálogo do pacote traz só as permissões de gestão (`roles.*`, `permissions.*`).
As suas você declara em `config/rbac.php` — `PermissionName::all()` mescla as duas:

```php
// config/rbac.php
'permissions' => [
    'products.viewAny', 'products.view', 'products.create', 'products.update', 'products.delete',
    'orders.viewAny', 'orders.view',
],

// opcional: classificar recursos por tipo/contexto
'type_map' => ['products' => 'catalog', 'orders' => 'sales'],
```

Depois rode `php artisan rbac:sync` (ou o botão de sync na tela de permissões).

---

## Policies dos recursos do seu app

Use o trait do pacote, que já respeita o rollout (`rbac.enabled`) e o super-admin:

```php
use Callcocam\InertiaRbac\Concerns\ChecksRbacPermission;

class ProductPolicy
{
    use ChecksRbacPermission;

    public function viewAny($user): bool { return $this->allowByContext($user, 'products.viewAny'); }
    public function create($user): bool  { return $this->allowByContext($user, 'products.create'); }
    // ...
}
```

O `Gate::before` de super-admin (por `system_name`) já é registrado pelo pacote.

---

## Páginas Vue (criadas no seu projeto)

O pacote **não traz `.vue`**. Crie os componentes nos caminhos de
`config('rbac.views.*')` (default abaixo) em `resources/js/pages/`:

| Config | Componente default |
|---|---|
| `rbac.views.roles.index` | `rbac/roles/Index` |
| `rbac.views.roles.form` | `rbac/roles/Form` |
| `rbac.views.permissions.index` | `rbac/permissions/Index` |
| `rbac.views.permissions.form` | `rbac/permissions/Form` |

Publique os stubs de referência e adapte ao visual do seu app:

```bash
php artisan vendor:publish --tag=rbac-stubs
# copia para base_path('stubs/inertia-rbac/...') — use como ponto de partida
```

Pontos-chave dos stubs:

- **Index**: paginator chega como **prop deferida** (`<Deferred>` + skeleton). Botão
  "Novo" com `v-if="can.create"`. Excluir por linha só com `v-if="!row.is_protected"`.
- **Form**: um único componente para create e edit; `system_name`/`name` imutáveis no
  edit; picker de permissões por `type`.

---

## Wayfinder — ligando a UI às rotas do pacote

As ações **nunca** montam URL na mão: usam helpers gerados pelo
[`laravel/wayfinder`](https://github.com/laravel/wayfinder) a partir das rotas
nomeadas do pacote (`rbac.roles.*`, `rbac.permissions.*`).

```bash
composer require laravel/wayfinder --dev
npm install -D @laravel/vite-plugin-wayfinder
```

```ts
// vite.config.ts
import { wayfinder } from '@laravel/vite-plugin-wayfinder'

export default defineConfig({
  plugins: [/* laravel(), vue(), */ wayfinder({ formVariants: true })],
})
```

```bash
php artisan wayfinder:generate --with-form
```

```ts
import RoleController from '@/actions/Callcocam/InertiaRbac/Http/Controllers/RoleController'

RoleController.index.url()          // GET  index
RoleController.edit.url(role.id)    // GET  edit
// <Form v-bind="RoleController.store.form()">          (create)
// <Form v-bind="RoleController.update.form(role.id)">  (edit)
```

> Se o gerador não rastrear os controllers do vendor, veja o fallback na seção
> "Wayfinder" do arquivo `PROMPT.md`.

---

## Mostrar/esconder (server-driven)

1. **Botões por página**: cada `index` já envia `can: { create: boolean }`. Use
   `v-if="can.create"`. Ações de linha: `v-if="!row.is_protected"`.
2. **Menu**: decida os itens no servidor com o `PermissionResolver`:

```php
use Callcocam\InertiaRbac\Support\PermissionResolver;
use Callcocam\InertiaRbac\Models\Role;

$resolver = app(PermissionResolver::class);

$menu = array_values(array_filter([
    $resolver->allows($user, 'viewAny', Role::class)
        ? ['label' => 'Papéis', 'href' => RoleController::index()] : null,
]));
```

Assim os itens não autorizados nem chegam ao cliente.

---

## Configuração (`config/rbac.php`)

| Chave | Para quê |
|---|---|
| `enabled` | Liga/desliga a checagem (rollout gradual). |
| `guard` | Guard das roles/permissions. |
| `connection` | Conexão isolada (ex.: `landlord`), ou `null`. |
| `teams.enabled` / `teams.foreign_key` | Multi-tenant (espelha para o Spatie). |
| `protected_roles` / `protected_permissions` | Não podem ser apagadas/renomeadas. |
| `super_admin_role` | `system_name` da role com `Gate::before`. |
| `permissions` / `type_map` | Catálogo do app + classificação por tipo. |
| `views.*` | Caminhos dos componentes Vue. |
| `routes.*` / `redirect.*` | Prefixo, nome, middleware e redirects. |
| `per_page` | Paginação das telas. |

---

## Teams (multi-tenant)

```php
'teams' => ['enabled' => true, 'foreign_key' => 'tenant_id'],
```

Isso liga o middleware `SetPermissionTeamContext` no grupo de rotas e espelha
`permission.teams`/`team_foreign_key`. A **resolução do team atual é do app** —
por padrão o middleware lê a FK do usuário; você pode definir um callable em
`rbac.teams.resolver`.

---

## Testes

```bash
composer test        # Pest + Orchestra Testbench (SQLite em memória)
composer format      # Laravel Pint
```

## Licença

MIT. Veja [LICENSE.md](LICENSE.md).
