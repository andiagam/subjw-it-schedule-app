<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\RosterEntry;
use App\Models\User;
use App\Services\RosterService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(RosterService $roster): void
    {
        $people = [
            ['name' => 'Andi Agam', 'username' => 'aagam648', 'job_title' => 'IT Manager', 'role' => Role::Manager->value, 'pin' => '2208', 'al_balance' => 12, 'dp_balance' => 2],
            ['name' => 'Bintang', 'username' => 'tnbin839', 'job_title' => 'IT Officer', 'role' => Role::Staff->value, 'pin' => '1111', 'al_balance' => 12, 'dp_balance' => 1],
            ['name' => 'Kholis', 'username' => 'trainee1', 'job_title' => 'IT Trainee', 'role' => Role::Trainee->value, 'pin' => '2222', 'al_balance' => 6, 'dp_balance' => 0],
            ['name' => 'Bernard', 'username' => 'trainee2', 'job_title' => 'IT Trainee', 'role' => Role::Trainee->value, 'pin' => '3333', 'al_balance' => 6, 'dp_balance' => 0],
        ];

        $users = collect($people)->map(function (array $person) {
            $user = User::query()->firstOrNew(['username' => $person['username']]);
            $isNew = ! $user->exists;

            $user->fill([
                'name' => $person['name'],
                'job_title' => $person['job_title'],
                'role' => $person['role'],
                'pin' => $person['pin'],
                'is_active' => true,
            ]);

            if ($isNew) {
                $user->al_balance = $person['al_balance'];
                $user->dp_balance = $person['dp_balance'];
            }

            $user->save();

            return $user;
        });

        $start = CarbonImmutable::now()->startOfMonth();
        $end = $start->endOfMonth();

        foreach ($users as $user) {
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $status = $roster->templateStatus($user, $day);

                RosterEntry::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'date' => $day->toDateString(),
                    ],
                    [
                        'status' => $status,
                        'note' => $status === 'SHIFT' ? '08:00 - 17:00' : null,
                    ],
                );
            }
        }

        $this->call(IndonesianPublicHolidaySeeder::class);
    }
}
