<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'pin' => ['required', 'digits:4'],
        ]);

        $user = User::query()
            ->whereRaw('lower(username) = ?', [mb_strtolower(trim($credentials['username']))])
            ->first();

        if (! $user || ! $this->accountIsActive($user) || ! hash_equals((string) $user->pin, $credentials['pin'])) {
            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match an active account.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('roster_user_id', $user->id);
        $request->session()->forget('impersonator_id');

        return redirect()->intended(route('roster.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['roster_user_id', 'impersonator_id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchUser(Request $request): RedirectResponse
    {
        $actor = $request->attributes->get('rosterUser');
        $impersonator = $this->managerImpersonator();
        $isManagerSession = $actor instanceof User && $actor->isManager() && $impersonator === null;

        abort_unless($isManagerSession || $impersonator instanceof User, 403);

        $managerId = $impersonator?->id ?? $actor->id;

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $target = User::query()->whereKey($data['user_id'])->where('is_active', true)->first();

        if (! $target) {
            return back()->with('error', 'That account is not active.');
        }

        if ($target->id === $managerId) {
            $request->session()->forget('impersonator_id');
        } else {
            $request->session()->put('impersonator_id', $managerId);
        }

        $request->session()->put('roster_user_id', $target->id);

        return back()->with('status', 'Switched to '.$target->name.'.');
    }

    private function accountIsActive(User $user): bool
    {
        $value = $user->is_active;

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }

    private function managerImpersonator(): ?User
    {
        $id = session('impersonator_id');

        if (! $id) {
            return null;
        }

        return User::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where('role', Role::Manager->value)
            ->first();
    }
}
