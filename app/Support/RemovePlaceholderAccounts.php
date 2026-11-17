<?php

namespace App\Support;

use App\Models\RosterEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemovePlaceholderAccounts
{
    /**
     * Delete inactive placeholder accounts. Real people such as Andi Agam, Bintang,
     * Bernard, and Kholis stay, even when their job title uses the same words.
     */
    public function __invoke(): void
    {
        $ids = User::query()
            ->where('is_active', false)
            ->whereIn('name', ['IT Manager', 'IT Officer', 'IT Trainee', 'IT Trainee 1', 'IT Trainee 2'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            RosterEntry::query()->whereIn('user_id', $ids)->delete();
            DB::table('leave_requests')->whereIn('user_id', $ids)->delete();
            DB::table('leave_requests')->whereIn('reviewed_by', $ids)->update(['reviewed_by' => null]);
            DB::table('day_tasks')->whereIn('user_id', $ids)->update(['user_id' => null]);
            User::query()->whereIn('id', $ids)->delete();
        });
    }
}
