<?php

namespace App\Models;

use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'type',
    'start_date',
    'end_date',
    'selected_dates',
    'requested_status',
    'reason',
    'status',
    'manager_note',
    'credit_dp',
    'reviewed_by',
])]
class LeaveRequest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_dp' => 'boolean',
            'selected_dates' => 'array',
        ];
    }

    protected function startDate(): Attribute
    {
        return $this->calendarDate();
    }

    protected function endDate(): Attribute
    {
        return $this->calendarDate();
    }

    private function calendarDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn (mixed $value) => blank($value) ? null : CarbonImmutable::parse($value)->toDateString(),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        $selected = $this->selected_dates;

        if (is_array($selected) && $selected !== []) {
            $dates = array_values(array_unique(array_map(
                fn (mixed $date) => CarbonImmutable::parse($date)->toDateString(),
                $selected,
            )));
            sort($dates);

            return $dates;
        }

        if ($this->start_date === null || $this->end_date === null) {
            return [];
        }

        $dates = [];
        $cursor = CarbonImmutable::parse($this->start_date)->startOfDay();
        $end = CarbonImmutable::parse($this->end_date)->startOfDay();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    public function dayCount(): int
    {
        if ($this->type === 'SCHEDULE_CHANGE') {
            return count($this->dates());
        }

        return Dates::inclusiveDays(
            $this->start_date->toDateString(),
            $this->end_date->toDateString(),
        );
    }

    public function dateSummary(): string
    {
        $dates = $this->dates();

        if ($dates === []) {
            return '';
        }

        $parsed = array_map(fn (string $date) => CarbonImmutable::parse($date), $dates);
        $count = count($parsed);
        $label = $this->datesAreContiguous($dates)
            ? $this->rangeLabel($parsed)
            : $this->listLabel($parsed);

        return $label.' ('.$count.'d)';
    }

    public function requestedStatusLabel(): string
    {
        return $this->requested_status === 'SHIFT' ? 'ON' : 'OFF';
    }

    /**
     * @param  list<string>  $dates
     */
    private function datesAreContiguous(array $dates): bool
    {
        return Dates::inclusiveDays($dates[0], $dates[array_key_last($dates)]) === count($dates);
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     */
    private function rangeLabel(array $dates): string
    {
        $start = $dates[0];
        $end = $dates[array_key_last($dates)];

        if ($start->isSameDay($end)) {
            return $start->format('j M Y');
        }

        if ($start->year === $end->year) {
            return $start->format('j M').' – '.$end->format('j M Y');
        }

        return $start->format('j M Y').' – '.$end->format('j M Y');
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     */
    private function listLabel(array $dates): string
    {
        $years = array_unique(array_map(fn (CarbonImmutable $date) => $date->year, $dates));

        if (count($years) === 1) {
            $days = implode(', ', array_map(fn (CarbonImmutable $date) => $date->format('j M'), $dates));

            return $days.' '.$dates[0]->year;
        }

        return implode(', ', array_map(fn (CarbonImmutable $date) => $date->format('j M Y'), $dates));
    }
}
