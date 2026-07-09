# PROMPT — Criar o pacote `callcocam/inertia-rbac`

> Cole este arquivo inteiro no início de uma **nova sessão do Claude Code**, aberta **dentro da pasta do novo pacote** (NÃO dentro do Plannerate). Ele descreve, passo a passo, um pacote Laravel reutilizável de RBAC (roles + permissions do Spatie) para apps **Inertia + Vue 3**, com **ULID**, no mesmo estilo do projeto Plannerate — mas genérico e publicável em outros projetos.

---

## 0. Antes de rodar — criar a pasta e abrir no VS Code

No terminal (fora do Claude), rode:

```bash
mkdir -p ~/projects/inertia-rbac && cd ~/projects/inertia-rbac && git init && code .
```

Depois abra o Claude Code **dentro de `~/projects/inertia-rbac`** e cole este prompt.

---

## 1. Objetivo e princípios

### ⭐ Regras de ouro (leia primeiro — valem para o pacote inteiro)

1. **Sempre as versões mais novas do ecossistema.** Alvo = **última versão estável do Laravel** (12+/13+, e o que vier depois) + **Inertia mais recente (v2+)** + **Vue 3 mais recente** + **PHP 8.3/8.4+**. Antes de escrever qualquer código, rode `composer show laravel/framework inertiajs/inertia-laravel spatie/laravel-permission` (ou consulte a doc oficial) e **use a API da versão realmente instalada**. Nada de padrões antigos (ex.: `$router->` legado, kernels antigos do Laravel 10). Se estiver criando do zero, puxe a última versão de cada dependência.
2. **Controllers ficam no PACOTE.** Toda a lógica de CRUD/sync (`RoleController`, `PermissionController`, Form Requests, Policies, Models) mora dentro de `callcocam/inertia-rbac` e é versionada/atualizada pelo pacote. O app **não** copia controller nenhum.
3. **Páginas Vue são criadas no PROJETO.** O pacote **não traz `.vue`**. O app consumidor cria os componentes (Index/Form) em `resources/js/pages/...`, no **visual/estilo do próprio projeto** (Tailwind, componentes de UI, layout — tudo do projeto).
4. **Visual do projeto, ações do pacote.** As páginas usam a aparência do projeto, mas as **ações/submits apontam para as rotas e controllers-model do pacote** (via actions Wayfinder das rotas nomeadas `rbac.*`). Ou seja: HTML/estilo = projeto; endpoint que processa = controller do pacote.
5. **Reaproveite a config do Spatie.** O `spatie/laravel-permission` já publica um `config/permission.php` cheio de coisa útil (`models`, `table_names`, `column_names`, `teams`, `cache`, guards). **Reutilize isso** — o `config/rbac.php` só adiciona o que é específico do pacote (views, rollout, roles protegidas, redirects). Não reinvente o que o Spatie já resolve. Ver §4.1.
6. **Wayfinder é a ponte frontend → rotas.** As páginas Vue **nunca** montam URL na mão nem usam string de rota. Todas as ações (index, create, store, edit, update, destroy, sync) são chamadas via **actions/route helpers gerados pelo `laravel/wayfinder`** a partir das rotas nomeadas `rbac.*` que o pacote registra. O pacote **garante rotas nomeadas e estáveis** para o Wayfinder introspectar; o app roda `php artisan wayfinder:generate --with-form` e importa os helpers tipados. Ver §11 e §16.6.

---

Você vai criar um **pacote Composer** chamado `callcocam/inertia-rbac` (namespace `Callcocam\InertiaRbac`) que entrega, prontos para instalar em qualquer app Laravel + Inertia + Vue:

- Catálogo de permissões como **fonte única de verdade** (uma classe com `const`), classificado por **tipo/contexto**.
- Models `Role`/`Permission` estendendo o Spatie, com **ULID**, colunas extras (`type`, `system_name`, `short_name`, `description`) e SoftDeletes.
- **Migrations config-driven com ULID** (id ULID, morph key ULID, teams opcional).
- **Policies** finas + trait de checagem + `Gate::before` de super-admin.
- **Controllers** `RoleController`/`PermissionController` (CRUD + `sync`) que renderizam **componentes Vue cujo caminho vem do config** (o pacote NÃO traz `.vue`).
- **Form Requests**, **rotas**, **middleware de contexto de team (opcional)**, **command de sync**, **seeder**.
- **Mostrar/esconder** botões e menu por permissão, via prop `can` server-driven (estilo Plannerate) + helper opcional.
- **README** com instruções completas de como o projeto consumidor cria as páginas Vue (Index/Form) e as actions Wayfinder, e configura os caminhos.

### Decisões de arquitetura já tomadas (não reabrir)

1. **Teams opcional via config.** O pacote funciona single-database por padrão. Se `config('rbac.teams.enabled') === true`, ativa `team_foreign_key` (default `tenant_id`) e o middleware de contexto. Nada de acoplar landlord/tenant cross-database — isso fica a cargo do app.
2. **Pacote não tem Vue.** Controllers fazem `Inertia::render(config('rbac.views.roles.index'), ...)`. Todos os caminhos de componente são configuráveis. O projeto cria as `.vue` no estilo dele.
3. **ULID em tudo** (`HasUlids`), guard padrão `web` (configurável).
4. **PT-BR** nos rótulos/descrições de permissões e mensagens; código com docblocks em PT-BR explicando cada função (o projeto usa esse padrão).

### Antes de escrever qualquer código

- Rode `search-docs`/consulte a doc da versão instalada do `spatie/laravel-permission` para confirmar nomes de config (`table_names`, `column_names`, `teams`, `team_resolver`) e a assinatura das migrations na versão alvo (**assuma Spatie Permission v6+**, mas confirme).
- Confirme versões alvo mirando **sempre o mais novo**: **PHP 8.3/8.4+**, **Laravel 12+/13+ (última estável)**, **Inertia v2+**, **Vue 3 (última)**. Rode `composer show` no ambiente para ver o que está instalado e programe contra essa API. Declare ranges amplos e à frente no `composer.json` (ex.: `"laravel/framework": "^12.0|^13.0"`), nunca travando numa versão antiga.

---

## 2. Estrutura do pacote

Crie exatamente esta árvore (ajuste só se a doc do Spatie exigir):

```
inertia-rbac/
├── composer.json
├── README.md
├── config/
│   └── rbac.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_rbac_permission_tables.php.stub
│   │   └── 0001_01_01_000001_add_metadata_to_permissions_table.php.stub
│   └── seeders/
│       └── RbacSeeder.php
├── routes/
│   └── rbac.php
├── src/
│   ├── InertiaRbacServiceProvider.php
│   ├── Support/
│   │   ├── PermissionName.php
│   │   ├── RbacType.php
│   │   └── PermissionResolver.php
│   ├── Models/
│   │   ├── Role.php
│   │   └── Permission.php
│   ├── Concerns/
│   │   ├── HasRbac.php              (trait pro model User do app)
│   │   └── ChecksRbacPermission.php (trait pras policies)
│   ├── Policies/
│   │   ├── RolePolicy.php
│   │   └── PermissionPolicy.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── RoleController.php
│   │   │   └── PermissionController.php
│   │   ├── Requests/
│   │   │   ├── StoreRoleRequest.php
│   │   │   ├── UpdateRoleRequest.php
│   │   │   ├── StorePermissionRequest.php
│   │   │   └── UpdatePermissionRequest.php
│   │   ├── Middleware/
│   │   │   └── SetPermissionTeamContext.php
│   │   └── Concerns/
│   │       └── InteractsWithDeferredIndex.php
│   └── Console/
│       └── SyncPermissionsCommand.php
├── stubs/                          (referência p/ o projeto — não são .vue reais)
│   ├── Index.vue.stub
│   └── Form.vue.stub
└── tests/
    ├── Pest.php
    ├── TestCase.php
    └── Feature/
        ├── RoleControllerTest.php
        ├── PermissionControllerTest.php
        └── PolicyTest.php
```

Use **Orchestra Testbench** para os testes do pacote.

---

## 3. `composer.json`

```jsonc
{
  "name": "callcocam/inertia-rbac",
  "description": "RBAC (roles & permissions) para Laravel + Inertia + Vue, com ULID, no estilo catálogo de permissões.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^8.3",
    "illuminate/contracts": "^12.0|^13.0",
    "inertiajs/inertia-laravel": "^2.0",
    "spatie/laravel-permission": "^6.0"
  },
  "require-dev": {
    "orchestra/testbench": "^10.0|^11.0",
    "pestphp/pest": "^3.0",
    "laravel/pint": "^1.0"
  },
  "autoload": {
    "psr-4": { "Callcocam\\InertiaRbac\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "Callcocam\\InertiaRbac\\Tests\\": "tests/" }
  },
  "extra": {
    "laravel": {
      "providers": ["Callcocam\\InertiaRbac\\InertiaRbacServiceProvider"]
    }
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

---

## 4. `config/rbac.php` — o coração da configurabilidade

Tudo que o projeto precisa customizar mora aqui: caminhos das views, guard, teams on/off, flag de rollout, roles de sistema, e o mapa de modelos.

```php
<?php

return [
    // Liga/desliga a checagem real de permissões nas policies.
    // false = tudo liberado (útil no rollout gradual). Estilo Plannerate.
    'enabled' => env('RBAC_ENABLED', true),

    // Guard padrão usado por roles/permissions.
    'guard' => 'web',

    // Conexão de banco onde ficam roles/permissions/pivots.
    // null = conexão padrão do app. Preencha quando quiser isolar (ex.: 'landlord').
    'connection' => env('RBAC_CONNECTION', null),

    // Suporte a teams (multi-tenant). Desligado por padrão.
    'teams' => [
        'enabled' => env('RBAC_TEAMS', false),
        'foreign_key' => 'tenant_id', // coluna de team nas tabelas do Spatie
    ],

    // Roles de sistema (por system_name) que NÃO podem ser apagadas/renomeadas livremente.
    'protected_roles' => ['super-admin', 'admin'],

    // Permissões de sistema que não podem ser apagadas.
    'protected_permissions' => [],

    // system_name da role que recebe Gate::before (acesso total).
    'super_admin_role' => 'super-admin',

    // Caminhos dos componentes Vue renderizados pelos controllers.
    // O PROJETO cria esses componentes; aqui só apontamos onde estão.
    'views' => [
        'roles' => [
            'index' => 'rbac/roles/Index',
            'form'  => 'rbac/roles/Form',
        ],
        'permissions' => [
            'index' => 'rbac/permissions/Index',
            'form'  => 'rbac/permissions/Form',
        ],
    ],

    // Prefixo, nome e middleware das rotas.
    'routes' => [
        'prefix' => 'admin',
        'name' => 'rbac.',
        'middleware' => ['web', 'auth'],
    ],

    // Onde os controllers redirecionam após store/update/destroy.
    // Use nomes de rota gerados pelo pacote.
    'redirect' => [
        'roles' => 'rbac.roles.index',
        'permissions' => 'rbac.permissions.index',
    ],

    // Paginação padrão das telas de index.
    'per_page' => 15,
];
```

**Regra:** todo controller/model/migration lê valores daqui (`config('rbac.*')`) — nada hardcoded.

### 4.1 Reaproveitar o `config/permission.php` do Spatie (NÃO reinventar)

O `spatie/laravel-permission` já publica um `config/permission.php` completo e testado. **Reutilize-o** em vez de duplicar. Ele já resolve, entre outros:

- `models.role` / `models.permission` → aponte para os models do pacote (§6).
- `table_names.*` (roles, permissions, model_has_roles, model_has_permissions, role_has_permissions) → as migrations do pacote (§7) leem **daqui**, não de nomes hardcoded.
- `column_names.*` (`role_pivot_key`, `permission_pivot_key`, `model_morph_key`, `team_foreign_key`) → use estes nas migrations e pivots.
- `teams` (bool) + `team_resolver` → o suporte a teams do pacote (§11) liga-se **por cima** disso, não em paralelo.
- `cache.*` (store, expiration, key) → deixe o Spatie cuidar do cache de permissões; só chame `forgetCachedPermissions()` após `sync`/seed.
- `use_passport_client_credentials`, `enable_wildcard_permission`, `display_permission_in_exception` → disponíveis se o app quiser.

**Divisão de responsabilidades:**

| Assunto | Onde configura |
|---|---|
| Tabelas, colunas, models, cache, teams core, guards | `config/permission.php` (Spatie) — **reutilizar** |
| Caminhos das views Vue, rollout (`enabled`), roles/permissions protegidas, super-admin, redirects, prefixo de rota, `per_page` | `config/rbac.php` (pacote) — **específico** |

**No Service Provider (§14):** no `boot`, leia `config('rbac.*')` e **ajuste programaticamente** o `config('permission.*')` correspondente (models, connection, `teams.enabled`, `column_names.team_foreign_key`) antes do Spatie registrar. Assim o app instala com o mínimo de setup manual e o pacote continua sendo a fonte única. Documente no README que, se o dev quiser, pode publicar e editar `config/permission.php` à mão — mas o default é o pacote alinhar os dois sozinho.

**Regra de ouro (repetindo §1.5):** se o Spatie já tem a config, use a dele; o `rbac.php` só cobre o que é do pacote.

---

## 5. `src/Support/` — catálogo de permissões (fonte única)

### `RbacType.php`

Classe `final` com as constantes de contexto e a classificação por prefixo. Comece só com um contexto genérico e deixe extensível:

```php
final class RbacType
{
    public const DEFAULT = 'default';
    // O app pode registrar tipos extras via config se quiser; comece simples.

    /** @return list<string> */
    public static function all(): array { return [self::DEFAULT]; }

    /** Deriva o tipo pelo prefixo do nome da permissão (ex.: "roles.viewAny" -> default). */
    public static function fromName(string $name): ?string { /* ... */ }
}
```

### `PermissionName.php`

O catálogo. **Fonte única** para seeder, command de sync e Form Requests. Padrão de nome: `{recurso}.{ação}` em dot-notation (CRUD: `viewAny/view/create/update/delete` + extras). Já inclua as permissões do próprio pacote (gerir roles e permissions):

```php
final class PermissionName
{
    public const ROLES_VIEW_ANY = 'roles.viewAny';
    public const ROLES_VIEW     = 'roles.view';
    public const ROLES_CREATE   = 'roles.create';
    public const ROLES_UPDATE   = 'roles.update';
    public const ROLES_DELETE   = 'roles.delete';

    public const PERMISSIONS_VIEW_ANY = 'permissions.viewAny';
    public const PERMISSIONS_VIEW     = 'permissions.view';
    public const PERMISSIONS_CREATE   = 'permissions.create';
    public const PERMISSIONS_UPDATE   = 'permissions.update';
    public const PERMISSIONS_DELETE   = 'permissions.delete';

    /** @return list<string> Todas as permissões conhecidas (single source of truth). */
    public static function all(): array { /* reflexão sobre as consts OU lista explícita */ }

    /** @return array<string, array{short_name:string, description:string}> */
    public static function metadata(): array { /* rótulos PT-BR derivados por CRUD + overrides */ }

    public static function typeFor(string $name): ?string { return RbacType::fromName($name); }
    public static function shortNameFor(string $name): ?string { /* ... */ }
    public static function descriptionFor(string $name): ?string { /* ... */ }
}
```

**Importante — extensibilidade:** o app consumidor terá permissões próprias (`products.*`, `orders.*` etc.). Ofereça um ponto de extensão: `PermissionName::all()` deve mesclar as consts do pacote com um array vindo de `config('rbac.permissions', [])` (ou um callback registrável no service provider). Documente isso no README. Assim o projeto declara o catálogo dele sem editar o pacote.

Inclua também `RESOURCES`/`ACTION_SHORT`/`ACTION_DESCRIPTION` (templates com placeholders) + `OVERRIDES` para gerar `metadata()` automaticamente via CRUD, com override manual pras permissões não-CRUD. (Mesmo desenho do Plannerate.)

### `PermissionResolver.php`

Ponte fina entre menu/UI e o Gate:

```php
class PermissionResolver
{
    /** Retorna true se o usuário pode a ability (via Gate). false se não houver usuário. */
    public function allows(?Authenticatable $user, string $ability, mixed $subject = null): bool
    {
        if (! $user) { return false; }
        return $subject !== null
            ? Gate::forUser($user)->check($ability, $subject)
            : Gate::forUser($user)->check($ability);
    }
}
```

---

## 6. `src/Models/`

### `Role.php`

```php
class Role extends \Spatie\Permission\Models\Role
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['tenant_id', 'type', 'system_name', 'name', 'guard_name'];

    /** Usa a conexão configurada (null = padrão do app). */
    public function getConnectionName(): ?string { return config('rbac.connection'); }
}
```

### `Permission.php`

```php
class Permission extends \Spatie\Permission\Models\Permission
{
    use HasUlids;

    protected $fillable = ['type', 'name', 'short_name', 'description', 'guard_name'];

    public function getConnectionName(): ?string { return config('rbac.connection'); }
}
```

`config/permission.php` (publicado pelo Spatie) deve apontar `models.role`/`models.permission` para essas classes — documente no README que o projeto precisa fazer isso (ou o service provider força via `PermissionRegistrar`).

### `src/Concerns/HasRbac.php`

Trait que o **User do app** usa. Basicamente reexporta `Spatie\Permission\Traits\HasRoles` + `HasUlids`, e — **somente quando teams estiver ligado E numa conexão diferente** — permite sobrescrever `roles()`/`permissions()` para fixar a conexão/pivot. Mantenha o caminho single-DB trivial (só `use HasRoles;`). Documente que, no modo teams cross-DB, o projeto pode precisar de um pivot próprio (aponte o exemplo, não force).

---

## 7. Migrations (stubs) — ULID + teams opcional

Publicáveis para o app (tag `rbac-migrations`). São **config-driven**: leem `config('permission.table_names')`, `config('permission.column_names')` e `config('rbac.teams.*')`.

Pontos obrigatórios (vs. Spatie stock):

- `permissions`: `ulid('id')->primary()`, `string('type', 50)`, `name`, `short_name` (nullable), `description` (nullable), `guard_name`, timestamps, `softDeletes()`, unique `['guard_name','name','type']`.
- `roles`: `ulid('id')->primary()`; **se teams ligado** `ulid(team_fk)->nullable()->index()`; `type`, `system_name` nullable+unique, `name`, `guard_name`, timestamps, softDeletes; unique incluindo o team_fk quando houver.
- `model_has_permissions` / `model_has_roles`: pivô com `ulid(pivot_key)`, `string('model_type')`, **`ulid('model_id')`** (morph key ULID, não uuid), `ulid(team_fk)->nullable()->index()` quando teams; unique composta com team; FKs cascade. **Sem** `id` auto-increment.
- `role_has_permissions`: `ulid(permission_id)`, `ulid(role_id)`, primary composta.
- SQL/índices especiais: sempre cheque o driver antes (`if (DB::connection($conn)->getDriverName() !== 'pgsql') { ... }`) porque os testes rodam em SQLite.

Segundo stub: `add_metadata_to_permissions` só existe se você preferir separar `short_name`/`description` — se já colocou na criação, pule.

---

## 8. Policies + checagem + `Gate::before`

### `src/Concerns/ChecksRbacPermission.php`

```php
trait ChecksRbacPermission
{
    /** Libera se RBAC desligado; senão true se o usuário tem QUALQUER uma das permissões. */
    protected function allowByContext(User $user, array|string $permissions): bool
    {
        if (! config('rbac.enabled', true)) { return true; }
        foreach ((array) $permissions as $permission) {
            if ($user->can($permission)) { return true; }
        }
        return false;
    }
}
```

> Se o app usa contexto landlord/tenant, ele pode estender esse trait no projeto para adicionar bypass de contexto. No pacote, mantenha simples.

### `RolePolicy` / `PermissionPolicy`

Cada método (`viewAny/view/create/update/delete`) retorna `$this->allowByContext($user, PermissionName::CONST)`. Tipagem: `?\Illuminate\Contracts\Auth\Authenticatable` como user (o pacote não conhece a classe User do app).

### Registro (no Service Provider)

```php
Gate::policy(Role::class, RolePolicy::class);
Gate::policy(Permission::class, PermissionPolicy::class);

// Super-admin faz tudo (por system_name configurável).
Gate::before(function ($user): ?bool {
    $superName = config('rbac.super_admin_role');
    return method_exists($user, 'roles') && $user->roles()
        ->where('system_name', $superName)->exists() ? true : null;
});
```

> **Atenção (bug real que já pegamos no Plannerate):** `hasRole('slug')` do Spatie compara contra `name`, não contra `system_name`. Como usamos `system_name` como slug estável, sempre cheque via `$user->roles()->where('system_name', ...)->exists()`, nunca `hasRole()`.

---

## 9. Controllers — renderizando view configurável

Ambos usam `AuthorizesRequests` e `InteractsWithDeferredIndex`. **View path sempre de `config('rbac.views.*')`.** Páginas retornam `Inertia::render(...)`; mutações retornam `redirect()->route(config('rbac.redirect.*'))` (ou `back()` em erro).

### `RoleController`

- `index`: `authorize('viewAny', Role::class)`; query escopada por guard (`config('rbac.guard')`) e, se teams, por `whereNull(team_fk)` para roles globais; `withCount('permissions')`; renderiza `config('rbac.views.roles.index')` com paginator deferido, filtros, e **prop `can`** (ver §12): `['can' => ['create' => $request->user()->can('create', Role::class)]]`. Cada linha carrega `is_protected` (system_name ∈ `config('rbac.protected_roles')`).
- `create`: `authorize('create', ...)`; renderiza `config('rbac.views.roles.form')` com `['role' => null, 'types' => RbacType::all(), 'permissions' => $this->availablePermissions()]`.
- `store(StoreRoleRequest)`: cria com guard/type, `syncPermissions($request->permissions)`, flash de toast, redirect.
- `edit(Role)`: valida que a role é gerenciável (guard certo, e team null se aplicável — aborta 404 senão), `authorize('update', $role)`, carrega `permissions:id,name`, renderiza o form.
- `update(UpdateRoleRequest, Role)`: roles protegidas só renomeiam; demais atualizam type + `syncPermissions`.
- `destroy(Role)`: bloqueia protegidas e roles em uso (checa join `model_has_roles`); erro → `back()->withErrors(...)`; sucesso → redirect.
- Helpers privados: `availablePermissions()` (name/type/short_name/description com fallback pra `PermissionName`), `typesForSelect()`.

### `PermissionController`

Mesmo shape + `sync()`:

- `sync()`: `authorize('create', Permission::class)`; faz diff de `PermissionName::all()` vs. DB; cria as faltantes (com team id null quando teams); atribui automaticamente às roles de sistema conforme `type`; preenche metadata vazia; `app(PermissionRegistrar::class)->forgetCachedPermissions()`; redirect com toast.
- No `index`, passe `missing_count` (consts que ainda não estão no banco) como prop pra UI sugerir o sync.
- `name` (slug) é **imutável** no update; só `type`, `short_name`, `description` mudam.

### `src/Http/Concerns/InteractsWithDeferredIndex.php`

Helper `renderDeferredIndex($component, $propName, Closure $resolver, array $props)` que envolve o paginator em `Inertia::defer($resolver)`. (Deferred props — a UI mostra skeleton enquanto carrega.)

---

## 10. Form Requests

- `StoreRoleRequest`/`UpdateRoleRequest`: `authorize()` via `can('create'|'update', ...)`. Regras: `type` ∈ `RbacType::all()`; `name` unique escopado por `guard_name` + `type` (+ team null quando teams), com `->ignore($role->id)` no update; `permissions.*` `exists` na tabela de permissions pra aquele guard+type, `distinct`. `system_name` imutável no update.
- `StorePermissionRequest`/`UpdatePermissionRequest`: `name` unique escopado (imutável no update); `short_name` nullable max:150; `description` nullable max:1000.

Use `config('permission.table_names.*')` e `config('rbac.guard')` nas regras `unique`/`exists`.

---

## 11. Rotas + middleware

### `routes/rbac.php`

```php
Route::prefix(config('rbac.routes.prefix'))
    ->middleware(config('rbac.routes.middleware'))
    ->group(function () {
        // sync ANTES do resource pra não ser capturado por permissions/{permission}
        Route::post('permissions/sync', [PermissionController::class, 'sync'])
            ->name('rbac.permissions.sync');
        Route::resource('roles', RoleController::class)->except(['show'])->names('rbac.roles');
        Route::resource('permissions', PermissionController::class)->except(['show'])->names('rbac.permissions');
    });
```

Se teams ligado, adicione `SetPermissionTeamContext::class` ao grupo (via config).

> **Rotas pensadas para o Wayfinder (obrigatório).** Toda rota é **nomeada** (`rbac.roles.*`, `rbac.permissions.*`, `rbac.permissions.sync`) e usa métodos de controller convencionais/tipados — é isso que o `laravel/wayfinder` introspecta para gerar os helpers do frontend. Regras para não quebrar o gerador:
> - Sempre `->name(...)`; nunca deixe rota anônima.
> - Assinaturas com type-hints (`Role $role`, `StoreRoleRequest $request`) para o Wayfinder inferir parâmetros.
> - `permissions/sync` **antes** do `Route::resource` (já indicado acima) para não colidir com `permissions/{permission}`.
> - Os nomes/prefixos vêm de `config('rbac.routes.*')`, mas mantenha os **sufixos** (`roles.index`, etc.) estáveis, pois os imports Wayfinder no app dependem deles.

### `SetPermissionTeamContext` (só faz algo quando teams ligado)

Lê o team atual do app (resolver configurável — default lê do usuário ou de um binding), chama `setPermissionsTeamId($teamId)` e `unsetRelation('roles')/('permissions')` no user pra não vazar cache entre contextos. **Documente que o resolver de team é responsabilidade do app** (ex.: fecha por multitenancy). No modo single-DB, o middleware é no-op.

---

## 12. Mostrar/esconder na UI (aparecer/esconder)

Estilo Plannerate = **server-driven**, sem vazar a lista de permissões pro cliente. Dois mecanismos, ambos documentados no README:

**(a) Prop `can` por página (para botões).** Cada `index` passa `'can' => ['create' => $user->can('create', Role::class)]`. Na Vue: `<NewButton v-if="props.can.create" />`. Ações destrutivas por linha: `v-if="!row.is_protected"`.

**(b) Gating de menu (recomendado).** O app decide os itens de menu server-side chamando `PermissionResolver::allows($user, $ability, $subject)` e **omite** os itens não permitidos antes de mandar pro Inertia. O pacote exporta `PermissionResolver` pra isso. Documente o padrão; não imponha uma estrutura de menu.

**(c) Opcional — helper de conveniência.** Para projetos que preferem checar no cliente, ofereça (documentado, opt-in) um middleware `ShareRbacAbilities` que compartilha `auth.can` (mapa `ability => bool` só dos recursos RBAC) — **não** a lista crua de permissões. E um mini-composable `useCan()` no README (código de exemplo, não `.vue` no pacote). Deixe claro que o default é server-driven.

---

## 13. Command + Seeder

- `SyncPermissionsCommand` (`php artisan rbac:sync`): mesma lógica do `PermissionController::sync`, usável em deploy. Cria faltantes, atribui às roles de sistema, limpa cache.
- `RbacSeeder`: itera `PermissionName::all()` com `firstOrCreate`; cria roles de sistema por `system_name` (`super-admin` recebe `syncPermissions(PermissionName::all())`; demais recebem subconjuntos curados por `type`); usa `setPermissionsTeamId(null)` quando teams; limpa cache no fim. Publicável para o app estender.

---

## 14. Service Provider

`InertiaRbacServiceProvider` deve:

- `mergeConfigFrom(.../rbac.php, 'rbac')`.
- No `boot`: publicar config (`rbac-config`), migrations (`rbac-migrations`), seeder (`rbac-seeders`) e os stubs Vue de referência (`rbac-stubs`).
- `loadRoutesFrom(routes/rbac.php)`.
- Registrar policies + `Gate::before` (§8).
- Registrar o command.
- Forçar (se `config('rbac.connection')`/teams setados) os valores em `config('permission.*')` no boot, pra o Spatie usar os models/colunas/teams certos — OU documentar que o app ajusta `config/permission.php`. Prefira: **ajustar programaticamente no boot** lendo `config('rbac.*')`, pra reduzir setup manual.
- Bind singletons de `PermissionResolver`.

---

## 15. Testes (Pest + Testbench)

Cubra o essencial (rode `php artisan test`):

- `PolicyTest`: com `rbac.enabled=false` tudo libera; com `true`, user sem permissão é negado, com permissão é permitido; super-admin (via `Gate::before`) faz tudo.
- `RoleControllerTest`: index rende o componente configurado (troque `config('rbac.views.roles.index')` no teste e verifique via `Inertia::assertComponent`); store cria + sincroniza permissões; destroy bloqueia role protegida e role em uso; update de role protegida só renomeia.
- `PermissionControllerTest`: `sync` cria as faltantes de `PermissionName::all()`; `name` imutável no update.
- Use SQLite `:memory:` no Testbench; guardas de driver pgsql nas migrations garantem que rode.

Cheque estilo com `vendor/bin/pint`.

---

## 16. README — instruções para o projeto consumidor

O README é entregável de primeira classe. Deve conter:

### 16.1 Instalação

```bash
composer require callcocam/inertia-rbac
php artisan vendor:publish --tag=rbac-config
php artisan vendor:publish --tag=rbac-migrations
# se o Spatie ainda não estiver publicado:
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
php artisan rbac:sync         # popula as permissões do catálogo
php artisan db:seed --class="Database\Seeders\RbacSeeder"  # roles de sistema
```

### 16.2 Preparar o User do app

```php
use Callcocam\InertiaRbac\Concerns\HasRbac;

class User extends Authenticatable
{
    use HasRbac; // HasRoles + HasUlids
}
```

### 16.3 Declarar as permissões do projeto (extensão do catálogo)

No `config/rbac.php`, aponte um catálogo próprio (ou registre um callback no AppServiceProvider) — ex.: `products.viewAny`, `products.create`, etc. Explique que `PermissionName::all()` mescla pacote + config. Depois `php artisan rbac:sync`.

### 16.4 Criar as Policies dos recursos do projeto

Mostre o padrão: uma policy por recurso, usando o trait do pacote:

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

E registrar (`Gate::policy(...)`). Lembre do `Gate::before` de super-admin já vir do pacote.

### 16.5 Criar as páginas Vue (Index/Form)

Explique claramente: **o pacote não traz `.vue`**. O projeto cria os componentes nos caminhos de `config('rbac.views.*')` (default `rbac/roles/Index`, `rbac/roles/Form`, `rbac/permissions/Index`, `rbac/permissions/Form`) em `resources/js/pages/`. Forneça no README um **exemplo mínimo funcional** (copie a essência de `stubs/Index.vue.stub` e `stubs/Form.vue.stub`), destacando:

- `Index.vue`: `defineProps<{ roles?: Paginator<Row>; filters; can: { create: boolean } }>()` (paginator é **deferred**, logo opcional → mostrar skeleton). Botão "Novo" com `v-if="props.can.create"`. Delete por linha com `v-if="!row.is_protected"`.
- `Form.vue`: um único componente pra create e edit (`isEdit = role !== null`); usa `<Form>` do Inertia com a action Wayfinder (`.form()`); picker de permissões com filtro por `type` + busca + select-all; `system_name` gerado do `name` e desabilitado no edit (imutável).
- Estilo/Tailwind fica 100% a cargo do projeto. Os stubs são só estrutura.

### 16.6 Gerar as actions com Wayfinder (forma oficial de ligar UI → rotas)

**Este é o mecanismo padrão do pacote.** As páginas Vue não montam URL na mão; elas importam helpers tipados gerados pelo `laravel/wayfinder` a partir das rotas nomeadas do pacote.

**Passo 1 — instalar o Wayfinder no app** (uma vez):

```bash
composer require laravel/wayfinder --dev
npm install -D @laravel/vite-plugin-wayfinder
```

**Passo 2 — plugin no `vite.config.ts`** (para regenerar em dev/build automaticamente):

```ts
import { wayfinder } from '@laravel/vite-plugin-wayfinder';

export default defineConfig({
  plugins: [
    // ...laravel(), vue()...
    wayfinder({ formVariants: true }), // gera as variantes .form()
  ],
});
```

**Passo 3 — gerar as actions** (também roda sozinho pelo plugin, mas dá pra forçar):

```bash
php artisan wayfinder:generate --with-form
```

Isso lê **todas as rotas registradas**, inclusive as do vendor (`rbac.*`), e escreve os helpers em `resources/js/actions/...` e `resources/js/routes/...`.

**Passo 4 — usar no Vue.** Import a partir do namespace do controller do pacote:

```ts
import RoleController from '@/actions/Callcocam/InertiaRbac/Http/Controllers/RoleController';
import PermissionController from '@/actions/Callcocam/InertiaRbac/Http/Controllers/PermissionController';

// Navegação / URLs:
RoleController.index.url();        // GET  index
RoleController.create.url();       // GET  create
RoleController.edit.url(role.id);  // GET  edit
RoleController.destroy.url(role.id);
PermissionController.sync.url();   // POST sync

// Submits com o componente <Form> do Inertia (variantes .form()):
// <Form v-bind="RoleController.store.form()"> ... </Form>          (create)
// <Form v-bind="RoleController.update.form(role.id)"> ... </Form>  (edit)
```

> **Fallback (documente as duas opções).** Se o `wayfinder:generate` não rastrear o controller do vendor no seu setup, há dois caminhos: (a) publicar/duplicar as rotas nomeadas no `routes/web.php` do app apontando para os controllers do pacote, e então gerar; ou (b) escrever o arquivo de actions manualmente com helpers de URL locais no mesmo formato `.url()/.form()`. O default esperado é o (Passo 3) funcionar direto, já que o pacote registra rotas nomeadas via `loadRoutesFrom`.

### 16.7 Mostrar/esconder no app

Recapitule §12: use a prop `can` que os controllers já mandam, e para o menu use `PermissionResolver::allows()` server-side pra omitir itens. Dê exemplo de um item de menu condicional.

### 16.8 Trocar caminhos de view / prefixo de rota / teams

Mostre editar `config/rbac.php`: `views.*` (apontar pros componentes do projeto), `routes.prefix/name/middleware`, `teams.enabled`, `protected_roles`, `super_admin_role`, `enabled` (rollout).

---

## 17. Convenções (obrigatórias enquanto constrói)

- **Nada de texto hardcoded** onde faça sentido i18n; rótulos PT-BR nas permissões.
- **Docblocks PT-BR** em cada função pública explicando o que faz.
- **ULID** em toda entidade; guard e conexão sempre de config.
- **Nunca** hardcodar URL nem caminho de componente — sempre `config('rbac.*')`.
- Rode `vendor/bin/pint` antes de finalizar.
- Todo comportamento coberto por teste (Pest). Rode `php artisan test`.
- Ao terminar, **atualize o README** e faça um commit inicial claro.

## 18. Checklist final (o agente deve marcar tudo)

- [ ] `composer.json` mirando **as versões mais novas** (Laravel 12+/13+, Inertia v2+, PHP 8.3+) + PSR-4 + auto-discovery do provider.
- [ ] `config/rbac.php` com views/guard/connection/teams/protected/redirect/routes — **reaproveitando `config/permission.php` do Spatie** (§4.1), sem duplicar tabelas/colunas/models/cache.
- [ ] `Support/`: `PermissionName` (com extensão via config), `RbacType`, `PermissionResolver`.
- [ ] Models `Role`/`Permission` ULID + conexão de config.
- [ ] Trait `HasRbac` (User) + `ChecksRbacPermission` (policies).
- [ ] Migrations stub ULID + teams opcional + guardas de driver.
- [ ] Policies + `Gate::before` super-admin (via `system_name`, não `hasRole`).
- [ ] Controllers Role/Permission renderizando `config('rbac.views.*')` + prop `can` + `sync`.
- [ ] Form Requests com regras escopadas por guard/type/team.
- [ ] Rotas **nomeadas e estáveis** (`rbac.*`) prontas para o Wayfinder introspectar + middleware de team opcional.
- [ ] Service Provider: publish tags, rotas, policies, command, ajuste de `config/permission`.
- [ ] `SyncPermissionsCommand` + `RbacSeeder`.
- [ ] Testes Pest verdes (`php artisan test`) + Pint limpo.
- [ ] README completo (instalação → User → catálogo → policies → Vue → **Wayfinder (setup + generate + import)** → show/hide → config).
- [ ] Commit inicial.
- [ ] **Publicado no Packagist** (§19): repositório no GitHub, `composer validate` ok, tag `v0.1.0`, submit em packagist.org (ou webhook configurado).

---

## 19. Publicar no Packagist

Depois que o pacote estiver verde (testes + Pint) e commitado, disponibilize-o para `composer require`:

1. **Validar o manifesto:** `composer validate --strict` — corrija qualquer aviso (name, license, `require`, autoload).
2. **Repositório público no GitHub** com o mesmo nome do pacote:
   ```bash
   git add -A && git commit -m "feat: pacote inertia-rbac inicial"
   gh repo create callcocam/inertia-rbac --public --source=. --remote=origin --push
   ```
   (Se não usar `gh`, crie o repo manualmente e `git remote add origin ...` + `git push -u origin main`.)
3. **Tag SemVer** (Packagist versiona por tag Git):
   ```bash
   git tag v0.1.0 && git push origin v0.1.0
   ```
   Comece em `0.x` enquanto a API pode mudar; suba para `1.0.0` quando estabilizar.
4. **Submeter no Packagist:** logado em https://packagist.org, clique em **Submit**, cole a URL do repo (`https://github.com/callcocam/inertia-rbac`) e confirme. O Packagist lê o `composer.json` e importa as tags.
5. **Auto-update (recomendado):** configure o webhook do GitHub (Packagist mostra o passo em *"How to update packages"*) ou conecte a conta GitHub para que novos pushes/tags atualizem o Packagist automaticamente. Sem isso, use o botão **Update** manual.
6. **Conferir a instalação:** em outro projeto, `composer require callcocam/inertia-rbac` deve resolver. Enquanto não publicar, dá para testar localmente via `repositories` do tipo `path`/`vcs` no `composer.json` do app.

> **`minimum-stability`:** o exemplo usa `"dev"` só para desenvolver contra dependências não-estáveis. Para uma release publicável, prefira tags estáveis e considere remover/ajustar `minimum-stability` para não forçar isso nos consumidores.

---

**Comece confirmando as versões instaladas — sempre mirando as mais novas — (Spatie Permission, Inertia, Laravel via `composer show`), depois execute na ordem: 2 → 3 → 4 (incl. 4.1) → 5 → … → 18, e finalize publicando no Packagist (§19). Ao final, rode os testes e o Pint, e me mostre o resumo do que foi criado + como instalar no meu projeto.**
