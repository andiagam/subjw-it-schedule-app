<?php

namespace App\Services;

use App\Models\DayTask;
use App\Models\RosterEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RosterService
{
    public function monthStart(mixed $month, mixed $year): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $monthNumber = is_numeric($month) ? (int) $month : $now->month;
        $yearNumber = is_numeric($year) ? (int) $year : $now->year;

        if ($monthNumber < 1 || $monthNumber > 12 || $yearNumber < 2000 || $yearNumber > 2100) {
            return $now->startOfMonth();
        }

        return CarbonImmutable::create($yearNumber, $monthNumber, 1)->startOfDay();
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    public function days(CarbonImmutable $start): array
    {
        $days = [];
        $end = $start->endOfMonth();

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    public function templateStatus(CarbonImmutable $day): string
    {
        return $day->isWeekend() ? 'OFF' : 'SHIFT';
    }

    /**
     * @return Collection<int, User>
     */
    public function activeUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderByRaw("case when role = 'MANAGER' then 0 else 1 end")
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<string, RosterEntry>
     */
    public function entriesForMonth(CarbonImmutable $start): Collection
    {
        return RosterEntry::query()
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn (RosterEntry $entry) => $entry->user_id.'|'.$entry->date->toDateString());
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<string, RosterEntry>  $entries
     * @return array<int, array{SHIFT: int, OFF: int, AL: int, DP: int, SL: int, PH: int, al_balance: int, dp_balance: int}>
     */
    public function summaries(Collection $users, Collection $entries): array
    {
        $summaries = [];

        foreach ($users as $user) {
            $summaries[$user->id] = [
                'SHIFT' => 0,
                'OFF' => 0,
                'AL' => 0,
                'DP' => 0,
                'SL' => 0,
                'PH' => 0,
                'al_balance' => $user->al_balance,
                'dp_balance' => $user->dp_balance,
            ];
        }

        foreach ($entries as $entry) {
            if (! isset($summaries[$entry->user_id][$entry->status])) {
                continue;
            }

            $summaries[$entry->user_id][$entry->status]++;
        }

        return $summaries;
    }

    /**
     * @return array{SHIFT: int, OFF: int, AL: int, DP: int, SL: int, PH: int, al_balance: int, dp_balance: int}
     */
    public function summaryFor(User $user, CarbonImmutable $start): array
    {
        $counts = RosterEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'SHIFT' => (int) ($counts['SHIFT'] ?? 0),
            'OFF' => (int) ($counts['OFF'] ?? 0),
            'AL' => (int) ($counts['AL'] ?? 0),
            'DP' => (int) ($counts['DP'] ?? 0),
            'SL' => (int) ($counts['SL'] ?? 0),
            'PH' => (int) ($counts['PH'] ?? 0),
            'al_balance' => $user->al_balance,
            'dp_balance' => $user->dp_balance,
        ];
    }

    public function autofill(CarbonImmutable $start): int
    {
        $end = $start->endOfMonth()->toDateString();
        $holidays = $this->holidayDates($start);
        $existing = RosterEntry::query()
            ->whereBetween('date', [$start->toDateString(), $end])
            ->get(['user_id', 'date'])
            ->mapWithKeys(fn (RosterEntry $entry) => [$entry->user_id.'|'.$entry->date->toDateString() => true]);

        $created = 0;

        foreach ($this->activeUsers() as $user) {
            for ($day = $start; $day->lte($start->endOfMonth()); $day = $day->addDay()) {
                $key = $user->id.'|'.$day->toDateString();

                if ($existing->has($key) || isset($holidays[$day->toDateString()])) {
                    continue;
                }

                $status = $this->templateStatus($day);

                RosterEntry::query()->create([
                    'user_id' => $user->id,
                    'date' => $day->toDateString(),
                    'status' => $status,
                    'note' => $status === 'SHIFT' ? '08:00 - 17:00' : null,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array<string, true>
     */
    public function holidayDates(CarbonImmutable $start): array
    {
        return DayTask::query()
            ->where('kind', 'holiday')
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->get()
            ->mapWithKeys(fn (DayTask $task) => [$task->date->toDateString() => true])
            ->all();
    }

    /**
     * Mark a public holiday for the team. Leave that is already recorded stays as it is.
     */
    public function applyPublicHoliday(string $date): void
    {
        foreach ($this->activeUsers() as $user) {
            $entry = RosterEntry::query()
                ->where('user_id', $user->id)
                ->where('date', $date)
                ->first();

            if ($entry && in_array($entry->status, ['AL', 'DP', 'SL', 'OFF', 'PH'], true)) {
                continue;
            }

            RosterEntry::query()->updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                ['status' => 'PH', 'note' => null],
            );
        }
    }
}
