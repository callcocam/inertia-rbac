<?php

declare(strict_types=1);

namespace Callcocam\InertiaRbac\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Controller base do pacote: fornece authorize()/validate() sem depender do
 * App\Http\Controllers\Controller do projeto consumidor.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
