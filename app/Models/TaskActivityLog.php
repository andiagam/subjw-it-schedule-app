<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['day_task_id', 'task_title', 'task_date', 'action', 'actor_name', 'actor_username', 'note'])]
class TaskActivityLog extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(DayTask::class, 'day_task_id');
    }

    protected function taskDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn (mixed $value) => blank($value) ? null : CarbonImmutable::parse($value)->toDateString(),
        );
    }

    /**
     * @return array{action: string, actor_name: string, task_title: string, task_date: string, when: string, note: ?string}
     */
    public function toActivityArray(): array
    {
        return [
            'action' => $this->action,
            'actor_name' => $this->actor_name,
            'task_title' => $this->task_title,
            'task_date' => $this->task_date?->toDateString() ?? '',
            'when' => $this->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') ?? '',
            'note' => $this->note,
        ];
    }
}
