<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Support;

/**
 * Classificação de permissões por tipo/contexto.
 *
 * Serve para agrupar permissões na UI e para o seeder atribuir subconjuntos
 * a roles de sistema. O app pode registrar tipos extras via config('rbac.type_map').
 */
final class RbacType
{
    /** Tipo padrão para permissões de recursos comuns do app. */
    public const DEFAULT = 'default';

    /** Tipo das permissões de gestão do próprio RBAC (roles/permissions). */
    public const SYSTEM = 'system';

    /**
     * Todos os tipos conhecidos (built-in + os declarados pelo app em config).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $extra = array_values((array) config('rbac.type_map', []));

        return array_values(array_unique([
            self::DEFAULT,
            self::SYSTEM,
            ...$extra,
        ]));
    }

    /**
     * Deriva o tipo pelo recurso (prefixo antes do primeiro ponto) do nome.
     *
     * "roles.viewAny" e "permissions.create" caem em SYSTEM; um mapa opcional
     * em config('rbac.type_map') permite classificar recursos do app; o resto é DEFAULT.
     */
    public static function fromName(string $name): string
    {
        $resource = self::resourceOf($name);

        if (in_array($resource, ['roles', 'permissions'], true)) {
            return self::SYSTEM;
        }

        $map = (array) config('rbac.type_map', []);

        return $map[$resource] ?? self::DEFAULT;
    }

    /**
     * Extrai o recurso (parte antes do primeiro ponto) de um nome de permissão.
     */
    public static function resourceOf(string $name): string
    {
        return str_contains($name, '.')
            ? strstr($name, '.', true)
            : $name;
    }
}
