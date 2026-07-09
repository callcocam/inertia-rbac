<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission do pacote: estende a do Spatie adicionando ULID, SoftDeletes e as
 * colunas extras (type, short_name, description). Conexão vem de config('rbac.connection').
 */
class Permission extends SpatiePermission
{
    use HasUlids;
    use SoftDeletes;

    /**
     * Atributos liberados para atribuição em massa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'short_name',
        'description',
        'guard_name',
    ];

    /**
     * Usa a conexão configurada para o RBAC (null = conexão padrão do app).
     */
    public function getConnectionName(): ?string
    {
        return config('rbac.connection');
    }
}
