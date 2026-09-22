<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Request;
use App\Support\Response;
use Closure;

interface Middleware
{
    /**
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response;
}
