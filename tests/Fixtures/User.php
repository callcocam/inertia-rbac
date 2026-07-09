<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Tests\Fixtures;

use Callcocam\InertiaRbac\Concerns\HasRbac;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * User de teste com ULID e o trait HasRbac (HasRoles + HasUlids).
 */
class User extends Authenticatable
{
    use HasRbac;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
