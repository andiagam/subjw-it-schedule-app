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

    public function dayCount(): int
    {
        return Dates::inclusiveDays(
            $this->start_date->toDateString(),
            $this->end_date->toDateString(),
        );
    }
}
