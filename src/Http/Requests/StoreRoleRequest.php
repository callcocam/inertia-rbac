<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Requests;

use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Support\RbacType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da criação de papéis. Regras escopadas por guard e type do config.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roles = config('permission.table_names.roles');
        $permissions = config('permission.table_names.permissions');
        $guard = config('rbac.guard');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique($roles, 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
            'type' => ['required', 'string', Rule::in(RbacType::all())],
            'system_name' => [
                'nullable', 'string', 'max:255',
                Rule::unique($roles, 'system_name'),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'string', 'distinct',
                Rule::exists($permissions, 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
        ];
    }
}
