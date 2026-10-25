<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to users with one of the given roles, e.g. `role:admin`
 * or `role:admin,teacher`.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        abort_unless($request->user()?->hasRole(...$allowed), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
