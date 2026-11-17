<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function () {
            if (app()->bound('roster.nav.shared')) {
                return;
            }

            app()->instance('roster.nav.shared', true);

            $user = request()->attributes->get('rosterUser');

            if (! $user instanceof User) {
                $userId = session('roster_user_id');
                $user = $userId
                    ? User::query()->whereKey($userId)->where('is_active', true)->first()
                    : null;
            }

            $impersonatorId = session('impersonator_id');
            $impersonator = $impersonatorId
                ? User::query()->whereKey($impersonatorId)->where('is_active', true)->where('role', Role::Manager->value)->first()
                : null;

            $canSwitch = ($user?->isManager() && $impersonator === null) || $impersonator !== null;
            $sharedUsers = View::shared('switchUsers');
            $sharedPending = View::shared('pendingRequestCount');

            View::share([
                'currentUser' => $user,
                'impersonator' => $impersonator,
                'canSwitchUser' => $canSwitch,
                'switchUsers' => $canSwitch
                    ? ($sharedUsers instanceof Collection
                        ? $sharedUsers
                        : User::query()->where('is_active', true)->orderedByHierarchy()->get())
                    : collect(),
                'pendingRequestCount' => $user?->isManager()
                    ? ($sharedPending ?? LeaveRequest::query()->where('status', 'PENDING')->count())
                    : 0,
            ]);
        });
    }
}
