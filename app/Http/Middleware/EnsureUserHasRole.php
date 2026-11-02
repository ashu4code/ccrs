<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Enforce that the authenticated user holds one of the required roles.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  UserRole string values, e.g. "role:regional_manager".
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $requiredRoles = array_map(function (string $role): UserRole {
            if ($role === 'regional_manager') {
                $role = 'relationship_manager';
            }
            return UserRole::from($role);
        }, $roles);

        if ($user === null || ! in_array($user->role, $requiredRoles, strict: true)) {
            abort(403);
        }

        return $next($request);
    }
}
