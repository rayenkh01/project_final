<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = User::normalizeRole($request->session()->get('active_role') ?? $request->user()?->role);
        $hasAccess = $request->user() || $request->session()->get('demo_authenticated');

        abort_unless(
            $hasAccess && in_array($role, $roles, true),
            403,
            'Accès non autorisé pour ce rôle.'
        );

        return $next($request);
    }
}
