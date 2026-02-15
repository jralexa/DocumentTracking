<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs([
            'profile.edit',
            'profile.update',
            'password.update',
            'logout',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ])) {
            return $next($request);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Please change your temporary password before continuing.');
    }
}
