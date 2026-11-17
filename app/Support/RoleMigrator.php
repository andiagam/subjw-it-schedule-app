<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Support\Facades\DB;

class RoleMigrator
{
    public static function apply(): void
    {
        DB::table('users')
            ->where(function ($query) {
                $query->whereRaw('lower(job_title) = ?', ['it trainee'])
                    ->orWhereIn('username', ['trainee1', 'trainee2']);
            })
            ->update(['role' => Role::Trainee->value]);

        DB::table('users')
            ->where('role', '!=', Role::Trainee->value)
            ->where(function ($query) {
                $query->whereIn('role', ['MANAGER', 'Manager', Role::Manager->value])
                    ->orWhereRaw('lower(job_title) = ?', ['it manager']);
            })
            ->update(['role' => Role::Manager->value]);

        DB::table('users')
            ->whereIn('role', ['STAFF', 'Staff'])
            ->update(['role' => Role::Staff->value]);
    }
}
