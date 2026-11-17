<?php

namespace App\Services;

use App\Models\DayTask;
use App\Models\RosterEntry;
use App\Models\User;
use App\Support\IndonesianPublicHolidays;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function templateStatus(User $user, CarbonImmutable $day): string
    {
        if ($user->username === 'tnbin839') {
            return in_array($day->dayOfWeekIso, [4, 5], true) ? 'OFF' : 'SHIFT';
        }

        return $day->isWeekend() ? 'OFF' : 'SHIFT';
    }

    /**
     * Employees whose row belongs on this month's roster and export.
     *
     * @return Collection<int, User>
     */
    public function usersForMonth(CarbonImmutable $start): Collection
    {
        return $this->monthQuery($start)->orderedByHierarchy()->get();
    }

    /**
     * Month rows plus anyone who can still sign in, so the switcher stays usable
     * while the table itself stays limited to this month.
     *
     * @return Collection<int, User>
     */
    public function peopleForRoster(CarbonImmutable $start): Collection
    {
        return $this->monthQuery($start)
            ->orWhere(fn (Builder $query) => $query->canSignIn())
            ->orderedByHierarchy()
            ->get();
    }

    public function isVisibleInMonth(User $user, CarbonImmutable $start): bool
    {
        if (! $user->is_active && $user->end_date === null) {
            return true;
        }

        $monthStart = $start->toDateString();
        $monthEnd = $start->endOfMonth()->toDateString();
        $started = $user->start_date === null || $user->start_date->toDateString() <= $monthEnd;
        $notFinishedBefore = $user->end_date === null || $user->end_date->toDateString() >= $monthStart;

        return $started && $notFinishedBefore;
    }

    /**
     * @return Builder<User>
     */
    private function monthQuery(CarbonImmutable $start): Builder
    {
        $monthStart = $start->toDateString();
        $monthEnd = $start->endOfMonth()->toDateString();
        $history = RosterEntry::query()
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->select('user_id');

        return User::query()->where(function (Builder $query) use ($monthStart, $monthEnd, $history): void {
            $query->where(function (Builder $employed) use ($monthStart, $monthEnd): void {
                $employed
                    ->where(function (Builder $end) use ($monthStart): void {
                        $end->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
                    })
                    ->where(function (Builder $begin) use ($monthEnd): void {
                        $begin->whereNull('start_date')->orWhere('start_date', '<=', $monthEnd);
                    })
                    ->where(function (Builder $visible): void {
                        $visible->where('is_active', true)->orWhereNotNull('end_date');
                    });
            })->orWhere(function (Builder $archived) use ($history): void {
                $archived
                    ->where('is_active', false)
                    ->whereNull('end_date')
                    ->whereIn('id', $history);
            });
        });
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
        $end = $start->endOfMonth();
        $holidays = $this->holidayDates($start);
        $existing = RosterEntry::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (RosterEntry $entry) => $entry->user_id.'|'.$entry->date->toDateString());

        $inserts = [];
        $shiftIds = [];
        $offIds = [];
        $blackIds = [];
        $now = now()->toDateTimeString();
        $changed = 0;

        $monthStart = $start->toDateString();
        $monthEnd = $end->toDateString();

        foreach ($this->usersForMonth($start) as $user) {
            if (! $user->is_active && $user->end_date === null) {
                continue;
            }

            $startDate = $user->start_date?->toDateString();
            $endDate = $user->end_date?->toDateString();

            if (($endDate !== null && $endDate < $monthStart) || ($startDate !== null && $startDate > $monthEnd)) {
                continue;
            }

            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $date = $day->toDateString();

                if ($startDate !== null && $date < $startDate) {
                    continue;
                }

                $pastEnd = $endDate !== null && $date > $endDate;

                if (! $pastEnd && isset($holidays[$date])) {
                    continue;
                }

                $status = $pastEnd ? 'BLACK' : $this->templateStatus($user, $day);
                $entry = $existing->get($user->id.'|'.$date);

                if ($entry instanceof RosterEntry) {
                    if ($entry->status === 'BLACK' || $entry->status === $status) {
                        continue;
                    }

                    if (in_array($entry->status, ['AL', 'DP', 'SL', 'PH'], true)) {
                        continue;
                    }

                    if ($status === 'SHIFT') {
                        $shiftIds[] = $entry->id;
                    } elseif ($status === 'BLACK') {
                        $blackIds[] = $entry->id;
                    } else {
                        $offIds[] = $entry->id;
                    }

                    $changed++;

                    continue;
                }

                $inserts[] = [
                    'user_id' => $user->id,
                    'date' => $date,
                    'status' => $status,
                    'note' => $status === 'SHIFT' ? '08:00 - 17:00' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $changed++;
            }
        }

        DB::transaction(function () use ($inserts, $shiftIds, $offIds, $blackIds, $now) {
            foreach (array_chunk($inserts, 100) as $rows) {
                RosterEntry::query()->upsert($rows, ['user_id', 'date'], ['status', 'note', 'updated_at']);
            }

            foreach (array_chunk($shiftIds, 100) as $ids) {
                RosterEntry::query()->whereIn('id', $ids)->update([
                    'status' => 'SHIFT',
                    'note' => '08:00 - 17:00',
                    'updated_at' => $now,
                ]);
            }

            foreach (array_chunk($offIds, 100) as $ids) {
                RosterEntry::query()->whereIn('id', $ids)->update([
                    'status' => 'OFF',
                    'note' => null,
                    'updated_at' => $now,
                ]);
            }

            foreach (array_chunk($blackIds, 100) as $ids) {
                RosterEntry::query()->whereIn('id', $ids)->update([
                    'status' => 'BLACK',
                    'note' => null,
                    'updated_at' => $now,
                ]);
            }
        });

        return $changed;
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
     * Insert official public holidays that are not stored yet.
     * Only those new dates are marked PH. Leave and existing days off stay put.
     */
    public function seedIndonesianPublicHolidays(): void
    {
        $dates = IndonesianPublicHolidays::dates();
        $keys = array_keys($dates);
        sort($keys);

        $existing = DayTask::query()
            ->where('kind', 'holiday')
            ->whereBetween('date', [$keys[0], $keys[array_key_last($keys)]])
            ->pluck('date')
            ->map(fn (mixed $date) => $date instanceof CarbonImmutable ? $date->toDateString() : (string) $date)
            ->flip();

        $now = now()->toDateTimeString();
        $storedFalse = DB::connection()->getDriverName() === 'pgsql' ? DB::raw('false') : 0;
        $inserts = [];

        foreach ($dates as $date => $title) {
            if ($existing->has($date)) {
                continue;
            }

            $inserts[] = [
                'date' => $date,
                'title' => $title,
                'kind' => 'holiday',
                'user_id' => null,
                'is_done' => $storedFalse,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($inserts === []) {
            return;
        }

        DayTask::query()->insert($inserts);
        $this->applyPublicHolidays(array_column($inserts, 'date'));
    }

    /**
     * Mark a public holiday for the team. Leave that is already recorded stays as it is.
     */
    public function applyPublicHoliday(string $date): void
    {
        $this->applyPublicHolidays([$date]);
    }

    /**
     * @param  array<int, string>  $dates
     */
    public function applyPublicHolidays(array $dates): void
    {
        $dates = array_values(array_unique($dates));

        if ($dates === []) {
            return;
        }

        $users = User::query()
            ->where(function (Builder $query): void {
                $query->where('is_active', true)->orWhereNotNull('end_date');
            })
            ->get(['id', 'is_active', 'start_date', 'end_date']);

        if ($users->isEmpty()) {
            return;
        }

        $entries = RosterEntry::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereIn('date', $dates)
            ->get(['id', 'user_id', 'date', 'status']);

        $byKey = $entries->keyBy(fn (RosterEntry $entry) => $entry->user_id.'|'.$entry->date->toDateString());
        $preserve = ['AL', 'DP', 'SL', 'OFF', 'PH', 'BLACK'];
        $toUpdate = [];
        $toInsert = [];
        $now = now()->toDateTimeString();

        foreach ($users as $user) {
            $startDate = $user->start_date?->toDateString();
            $endDate = $user->end_date?->toDateString();

            foreach ($dates as $date) {
                if ($startDate !== null && $date < $startDate) {
                    continue;
                }

                if ($endDate !== null && $date > $endDate) {
                    continue;
                }

                $entry = $byKey->get($user->id.'|'.$date);

                if ($entry && in_array($entry->status, $preserve, true)) {
                    continue;
                }

                if ($entry) {
                    $toUpdate[] = $entry->id;

                    continue;
                }

                $toInsert[] = [
                    'user_id' => $user->id,
                    'date' => $date,
                    'status' => 'PH',
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($toUpdate, 100) as $ids) {
            RosterEntry::query()->whereIn('id', $ids)->update([
                'status' => 'PH',
                'note' => null,
                'updated_at' => $now,
            ]);
        }

        foreach (array_chunk($toInsert, 100) as $rows) {
            RosterEntry::query()->insert($rows);
        }
    }
}
