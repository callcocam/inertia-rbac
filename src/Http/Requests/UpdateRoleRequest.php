<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Requests;

use Callcocam\InertiaRbac\Models\Role;
use Callcocam\InertiaRbac\Support\RbacType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da edição de papéis. name unique ignorando o próprio registro;
 * system_name é imutável (não é aceito no update).
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->user()?->can('update', $role instanceof Role ? $role : Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roles = config('permission.table_names.roles');
        $permissions = config('permission.table_names.permissions');
        $guard = config('rbac.guard');

        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique($roles, 'name')
                    ->where(fn ($q) => $q->where('guard_name', $guard))
                    ->ignore($role->getKey(), $role->getKeyName()),
            ],
            'type' => ['required', 'string', Rule::in(RbacType::all())],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'string', 'distinct',
                Rule::exists($permissions, 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
        ];
    }
}
