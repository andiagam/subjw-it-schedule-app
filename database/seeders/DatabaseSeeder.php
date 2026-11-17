<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\RosterEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $people = [
            ['name' => 'IT Manager', 'username' => 'manager', 'job_title' => 'IT Manager', 'role' => Role::Manager->value, 'pin' => '1234', 'al_balance' => 12, 'dp_balance' => 2],
            ['name' => 'IT Officer', 'username' => 'officer', 'job_title' => 'IT Officer', 'role' => Role::Staff->value, 'pin' => '1111', 'al_balance' => 12, 'dp_balance' => 1],
            ['name' => 'IT Trainee 1', 'username' => 'trainee1', 'job_title' => 'IT Trainee', 'role' => Role::Trainee->value, 'pin' => '2222', 'al_balance' => 6, 'dp_balance' => 0],
            ['name' => 'IT Trainee 2', 'username' => 'trainee2', 'job_title' => 'IT Trainee', 'role' => Role::Trainee->value, 'pin' => '3333', 'al_balance' => 6, 'dp_balance' => 0],
        ];

        $users = collect($people)->map(fn (array $person) => User::query()->create($person));

        $start = CarbonImmutable::now()->startOfMonth();
        $end = $start->endOfMonth();

        foreach ($users as $user) {
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $weekend = $day->isWeekend();

                RosterEntry::query()->create([
                    'user_id' => $user->id,
                    'date' => $day->toDateString(),
                    'status' => $weekend ? 'OFF' : 'SHIFT',
                    'note' => $weekend ? null : '08:00 - 17:00',
                ]);
            }
        }
    }
}
