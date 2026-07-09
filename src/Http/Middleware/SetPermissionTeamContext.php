<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o contexto de team (multi-tenant) para o Spatie.
 *
 * Só faz algo quando config('rbac.teams.enabled') é true; caso contrário é no-op.
 * O ID do team é resolvido por um callable configurável em config('rbac.teams.resolver')
 * ou, na ausência dele, pela coluna foreign_key no usuário autenticado. A resolução
 * do team é responsabilidade do app — aqui só a aplicamos ao registrar do Spatie.
 */
class SetPermissionTeamContext
{
    public function __construct(protected PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('rbac.teams.enabled')) {
            return $next($request);
        }

        $teamId = $this->resolveTeamId($request);

        $this->registrar->setPermissionsTeamId($teamId);

        // Evita vazar cache de roles/permissions entre contextos de team.
        $user = $request->user();
        if ($user) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }

        return $next($request);
    }

    /**
     * Resolve o ID do team atual (callable de config, ou a FK no usuário).
     */
    protected function resolveTeamId(Request $request): int|string|null
    {
        $resolver = config('rbac.teams.resolver');

        if (is_callable($resolver)) {
            return $resolver($request);
        }

        $foreignKey = config('rbac.teams.foreign_key', 'tenant_id');

        return $request->user()?->getAttribute($foreignKey);
    }
}
