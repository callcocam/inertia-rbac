<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Console;

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Support\PermissionName;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza o catálogo de permissões (PermissionName::all()) com o banco.
 * Usável em deploy. Cria as faltantes com type/metadata e limpa o cache.
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'rbac:sync {--guard= : Guard a sincronizar (padrão config rbac.guard)}';

    protected $description = 'Sincroniza o catálogo de permissões do RBAC com o banco de dados.';

    public function handle(PermissionRegistrar $registrar): int
    {
        $guard = $this->option('guard') ?: config('rbac.guard');

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

            $this->line("  <fg=green>+</> {$name}");
            $created++;
        }

        $registrar->forgetCachedPermissions();

        $this->info($created > 0
            ? "{$created} permissão(ões) criada(s) para o guard '{$guard}'."
            : "Nada a fazer: catálogo já sincronizado para o guard '{$guard}'.");

        return self::SUCCESS;
    }
}
