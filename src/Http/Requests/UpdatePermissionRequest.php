<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Requests;

use Callcocam\InertiaRbac\Models\Permission;
use Callcocam\InertiaRbac\Support\RbacType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da edição de permissões. O name (slug) é IMUTÁVEL: só type,
 * short_name e description podem mudar.
 */
class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');

        return $this->user()?->can('update', $permission instanceof Permission ? $permission : Permission::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(RbacType::all())],
            'short_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
