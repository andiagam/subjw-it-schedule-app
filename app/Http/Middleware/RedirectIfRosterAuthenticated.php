<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfRosterAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('roster_user_id');
        $user = $id ? User::query()->whereKey($id)->where('is_active', true)->first() : null;

        if ($user) {
            return redirect()->route('roster.index');
        }

        $request->session()->forget(['roster_user_id', 'impersonator_id']);

        return $next($request);
    }
}
