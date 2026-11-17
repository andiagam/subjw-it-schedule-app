<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'pin' => ['required', 'regex:/^\d{4,}$/'],
        ]);

        $result = DB::transaction(function () use ($credentials) {
            $user = User::query()
                ->whereRaw('lower(username) = ?', [mb_strtolower(trim($credentials['username']))])
                ->lockForUpdate()
                ->first();

            if (! $user || ! $this->accountIsActive($user) || $user->employmentHasEnded()) {
                return ['error' => 'Those credentials do not match an active account.'];
            }

            if ($user->is_locked) {
                return ['error' => 'This account is locked. Ask an IT Manager to unlock it.'];
            }

            if (! Pin::matches($user, $credentials['pin'])) {
                $attempts = $user->failed_login_attempts + 1;
                $locked = $attempts >= 5;
                $user->forceFill([
                    'failed_login_attempts' => $attempts,
                    'is_locked' => $locked,
                ])->save();

                return ['error' => $locked
                    ? 'This account is locked. Ask an IT Manager to unlock it.'
                    : 'Those credentials do not match an active account.'];
            }

            $user->forceFill([
                'failed_login_attempts' => 0,
                'is_locked' => false,
                'pin' => Pin::isHashed((string) $user->pin) ? $user->pin : Pin::hash($credentials['pin']),
            ])->save();

            return ['user' => $user];
        });

        if (isset($result['error'])) {
            throw ValidationException::withMessages([
                'username' => $result['error'],
            ]);
        }

        /** @var User $user */
        $user = $result['user'];

        $request->session()->regenerate();
        $request->session()->put('roster_user_id', $user->id);
        $request->session()->forget('impersonator_id');

        return redirect()->intended(route('roster.index'));
    }

    public function updatePin(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('rosterUser');

        $data = $request->validate([
            'current_pin' => ['required', 'string'],
            'pin' => ['required', 'regex:/^\d{6,}$/', 'confirmed'],
        ]);

        if (! Pin::matches($user, $data['current_pin'])) {
            throw ValidationException::withMessages([
                'current_pin' => 'The current PIN does not match.',
            ]);
        }

        $user->forceFill(['pin' => Pin::hash($data['pin'])])->save();

        return back()->with('status', 'PIN updated.');
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

        $target = User::query()->whereKey($data['user_id'])->canSignIn()->first();

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
            ->canSignIn()
            ->where('role', Role::Manager->value)
            ->first();
    }
}
