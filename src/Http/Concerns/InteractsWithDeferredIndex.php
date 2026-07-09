<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Concerns;

use Closure;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajuda os controllers de index a devolver o paginator como prop DEFERIDA,
 * para a UI mostrar skeleton enquanto os dados carregam (Inertia deferred props).
 */
trait InteractsWithDeferredIndex
{
    /**
     * Renderiza um componente de index com uma prop deferida (o paginator/resolver)
     * mais as props imediatas (filtros, can, contadores).
     *
     * @param  array<string, mixed>  $props
     */
    protected function renderDeferredIndex(string $component, string $propName, Closure $resolver, array $props = []): Response
    {
        return Inertia::render($component, array_merge($props, [
            $propName => Inertia::defer($resolver),
        ]));
    }
}
