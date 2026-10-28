<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage: ->middleware('role:admin') or ->middleware('role:admin,auditor').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => Role::fromName($role), $roles);

        abort_unless(in_array($request->user()?->otorisasi, $allowed, true), 403);

        return $next($request);
    }
}
