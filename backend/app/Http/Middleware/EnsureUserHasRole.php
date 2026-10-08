<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(
            $request->user() && $request->user()->role === UserRole::from($role),
            403,
            'Akses ditolak untuk peran ini.'
        );

        return $next($request);
    }
}
