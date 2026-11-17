<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('rosterUser');

        if (! $user instanceof User || ! $user->isManager()) {
            abort(403);
        }

        return $next($request);
    }
}
