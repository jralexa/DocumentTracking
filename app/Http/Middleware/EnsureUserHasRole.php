<?php

namespace App\Http\Middleware;

use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($roles === []) {
            return $next($request);
        }

        $normalizedRoles = array_map(static fn (string $role): string => strtolower($role), $roles);
        $allowedRoles = array_filter(
            UserRole::cases(),
            static fn (UserRole $role): bool => in_array($role->value, $normalizedRoles, true)
        );

        if (! $user->hasAnyRole($allowedRoles)) {
            abort(403);
        }

        return $next($request);
    }
}
