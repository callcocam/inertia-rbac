<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Support;

use ReflectionClass;

/**
 * Catálogo de permissões — fonte única de verdade.
 *
 * Consumido pelo seeder, pelo command rbac:sync, pelos Form Requests e pela UI.
 * Padrão de nome: "{recurso}.{ação}" em dot-notation. As permissões do próprio
 * pacote (gestão de roles/permissions) vivem nas constantes abaixo; o app declara
 * as dele em config('rbac.permissions') — PermissionName::all() mescla as duas fontes.
 */
final class PermissionName
{
    public const ROLES_VIEW_ANY = 'roles.viewAny';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_CREATE = 'roles.create';

    public const ROLES_UPDATE = 'roles.update';

    public const ROLES_DELETE = 'roles.delete';

    public const PERMISSIONS_VIEW_ANY = 'permissions.viewAny';

    public const PERMISSIONS_VIEW = 'permissions.view';

    public const PERMISSIONS_CREATE = 'permissions.create';

    public const PERMISSIONS_UPDATE = 'permissions.update';

    public const PERMISSIONS_DELETE = 'permissions.delete';

    /**
     * Rótulos PT-BR curtos por ação CRUD, usados para gerar metadata automaticamente.
     *
     * @var array<string, string>
     */
    private const ACTION_SHORT = [
        'viewAny' => 'Listar',
        'view' => 'Visualizar',
        'create' => 'Criar',
        'update' => 'Editar',
        'delete' => 'Excluir',
        'restore' => 'Restaurar',
        'forceDelete' => 'Excluir definitivamente',
        'sync' => 'Sincronizar',
    ];

    /**
     * Verbos PT-BR por ação CRUD para compor a descrição longa.
     *
     * @var array<string, string>
     */
    private const ACTION_VERB = [
        'viewAny' => 'listar',
        'view' => 'visualizar',
        'create' => 'criar',
        'update' => 'editar',
        'delete' => 'excluir',
        'restore' => 'restaurar',
        'forceDelete' => 'excluir definitivamente',
        'sync' => 'sincronizar',
    ];

    /**
     * Rótulos PT-BR por recurso conhecido (fallback = o próprio slug do recurso).
     *
     * @var array<string, string>
     */
    private const RESOURCE_LABELS = [
        'roles' => 'papéis',
        'permissions' => 'permissões',
    ];

    /**
     * Overrides manuais de metadata para permissões que fogem do padrão CRUD.
     *
     * @var array<string, array{short_name: string, description: string}>
     */
    private const OVERRIDES = [
        'permissions.sync' => [
            'short_name' => 'Sincronizar permissões',
            'description' => 'Permite sincronizar o catálogo de permissões com o banco de dados.',
        ],
    ];

    /**
     * Todas as permissões conhecidas (constantes do pacote + config do app), sem duplicatas.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $fromPackage = array_values((new ReflectionClass(self::class))->getConstants());
        $fromPackage = array_filter($fromPackage, static fn ($v): bool => is_string($v) && str_contains($v, '.'));

        $fromApp = array_filter((array) config('rbac.permissions', []), 'is_string');

        return array_values(array_unique([...$fromPackage, ...$fromApp]));
    }

    /**
     * Metadata (short_name + description) de cada permissão, derivada por CRUD com overrides.
     *
     * @return array<string, array{type: string, short_name: string, description: string}>
     */
    public static function metadata(): array
    {
        $meta = [];

        foreach (self::all() as $name) {
            $meta[$name] = [
                'type' => self::typeFor($name),
                'short_name' => self::shortNameFor($name),
                'description' => self::descriptionFor($name),
            ];
        }

        return $meta;
    }

    /** Tipo/contexto da permissão (delega para RbacType). */
    public static function typeFor(string $name): string
    {
        return RbacType::fromName($name);
    }

    /** Rótulo curto PT-BR (ex.: "Listar papéis"). */
    public static function shortNameFor(string $name): string
    {
        if (isset(self::OVERRIDES[$name])) {
            return self::OVERRIDES[$name]['short_name'];
        }

        [$resource, $action] = self::split($name);

        $actionLabel = self::ACTION_SHORT[$action] ?? ucfirst($action);
        $resourceLabel = self::RESOURCE_LABELS[$resource] ?? str_replace(['_', '-'], ' ', $resource);

        return trim("{$actionLabel} {$resourceLabel}");
    }

    /** Descrição longa PT-BR (ex.: "Permite listar papéis."). */
    public static function descriptionFor(string $name): string
    {
        if (isset(self::OVERRIDES[$name])) {
            return self::OVERRIDES[$name]['description'];
        }

        [$resource, $action] = self::split($name);

        $verb = self::ACTION_VERB[$action] ?? mb_strtolower($action);
        $resourceLabel = self::RESOURCE_LABELS[$resource] ?? str_replace(['_', '-'], ' ', $resource);

        return "Permite {$verb} {$resourceLabel}.";
    }

    /**
     * Quebra "recurso.acao" em [recurso, acao]; sem ponto, acao vira string vazia.
     *
     * @return array{0: string, 1: string}
     */
    private static function split(string $name): array
    {
        if (! str_contains($name, '.')) {
            return [$name, ''];
        }

        return [strstr($name, '.', true), substr(strstr($name, '.'), 1)];
    }
}
