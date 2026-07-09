<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Requests;

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Support\RbacType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da criação de permissões. name unique escopado por guard.
 */
class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissions = config('permission.table_names.permissions');
        $guard = config('rbac.guard');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique($permissions, 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
            'type' => ['required', 'string', Rule::in(RbacType::all())],
            'short_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
