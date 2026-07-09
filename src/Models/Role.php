<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role do pacote: estende a do Spatie adicionando ULID, SoftDeletes e as
 * colunas extras (type, system_name). A conexão vem sempre de config('rbac.connection').
 */
class Role extends SpatieRole
{
    use HasUlids;
    use SoftDeletes;

    /**
     * Atributos liberados para atribuição em massa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'type',
        'system_name',
        'name',
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
