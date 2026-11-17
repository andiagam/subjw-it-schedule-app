<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'username', 'job_title', 'role', 'pin', 'al_balance', 'dp_balance', 'is_active'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'al_balance' => 'integer',
            'dp_balance' => 'integer',
            'is_active' => 'boolean',
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
        return $this->role === 'MANAGER';
    }
}
