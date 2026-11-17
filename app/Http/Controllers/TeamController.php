<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Pin;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('team.index', [
            'members' => User::query()
                ->where('is_active', true)
                ->orderedByHierarchy()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        User::query()->create([
            ...$data,
            'is_active' => true,
        ]);

        return redirect()->route('team.index')->with('status', $data['name'].' added to the team.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->isManager() && $data['role'] !== Role::Manager->value && $this->otherActiveManagers($user) < 1) {
            return back()->with('error', 'Assign another active manager before changing this role.')->withInput();
        }

        $user->update($data);

        return redirect()->route('team.index')->with('status', $data['name'].' updated.');
    }

    public function archive(Request $request, User $user): RedirectResponse
    {
        $actor = $request->attributes->get('rosterUser');

        if ($actor instanceof User && $actor->id === $user->id) {
            return back()->with('error', 'You cannot archive your own account.');
        }

        if ($user->isManager() && $this->otherActiveManagers($user) < 1) {
            return back()->with('error', 'The last active manager cannot be archived.');
        }

        $user->forceFill([
            'is_active' => false,
            'end_date' => $user->end_date?->toDateString() ?? CarbonImmutable::now('Asia/Jakarta')->toDateString(),
        ])->save();

        return redirect()->route('team.index')->with('status', $user->name.' archived.');
    }

    public function unlock(User $user): RedirectResponse
    {
        $user->forceFill([
            'is_locked' => false,
            'failed_login_attempts' => 0,
        ])->save();

        return redirect()->route('team.index')->with('status', $user->name.' unlocked.');
    }

    public function resetPin(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'regex:/^\d{6,}$/', 'confirmed'],
        ]);

        $user->forceFill([
            'pin' => Pin::hash($data['pin']),
            'is_locked' => false,
            'failed_login_attempts' => 0,
        ])->save();

        return redirect()->route('team.index')->with('status', 'PIN reset for '.$user->name.'.');
    }

    /**
     * @return array{name: string, username: string, job_title: string, role: string, al_balance: int, dp_balance: int, start_date: ?string, end_date: ?string, pin?: string}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'job_title' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(Role::class)],
            'pin' => [$user ? 'nullable' : 'required', 'regex:/^\d{6,}$/'],
            'al_balance' => ['required', 'integer', 'min:0', 'max:999'],
            'dp_balance' => ['required', 'integer', 'min:0', 'max:999'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $data['name'] = trim($data['name']);
        $data['job_title'] = trim($data['job_title']);
        $data['al_balance'] = (int) $data['al_balance'];
        $data['dp_balance'] = (int) $data['dp_balance'];
        $data['start_date'] = $data['start_date'] ?? null;
        $data['end_date'] = $data['end_date'] ?? null;

        if ($data['start_date'] !== null && $data['end_date'] !== null && $data['end_date'] < $data['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        if (filled($data['pin'] ?? null)) {
            $data['pin'] = Pin::hash($data['pin']);
        } else {
            unset($data['pin']);
        }

        return $data;
    }

    private function otherActiveManagers(User $user): int
    {
        return User::query()
            ->where('role', Role::Manager->value)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->count();
    }
}
