<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'username', 'job_title', 'role', 'pin', 'al_balance', 'dp_balance', 'is_active', 'start_date', 'end_date', 'failed_login_attempts', 'is_locked'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $hidden = [
        'pin',
    ];

    protected function casts(): array
    {
        return [
            'al_balance' => 'integer',
            'dp_balance' => 'integer',
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'failed_login_attempts' => 'integer',
            'is_locked' => 'boolean',
        ];
    }

    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'reviewed_by');
    }

    public function dayTasks(): HasMany
    {
        return $this->hasMany(DayTask::class);
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager->value;
    }

    public function employmentHasEnded(?CarbonImmutable $today = null): bool
    {
        if ($this->end_date === null) {
            return false;
        }

        $today ??= CarbonImmutable::now('Asia/Jakarta')->startOfDay();

        return $this->end_date->toDateString() < $today->toDateString();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeCanSignIn(Builder $query): Builder
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $today);
            });
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOrderedByHierarchy(Builder $query): Builder
    {
        return $query
            ->orderByRaw(Role::orderSql())
            ->orderBy('name')
            ->orderBy('username');
    }
}
