<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRosterAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('roster_user_id');
        $user = $id ? User::query()->whereKey($id)->where('is_active', true)->first() : null;

        if (! $user) {
            $request->session()->forget(['roster_user_id', 'impersonator_id']);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        $request->attributes->set('rosterUser', $user);
        Auth::setUser($user);

        return $next($request);
    }
}
