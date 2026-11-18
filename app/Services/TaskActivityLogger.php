<?php

namespace App\Services;

use App\Models\TaskActivityLog;
use App\Models\User;

class TaskActivityLogger
{
    public function record(?int $taskId, string $title, string $date, string $action, User $actor, ?string $note = null): TaskActivityLog
    {
        return TaskActivityLog::query()->create([
            'day_task_id' => $taskId,
            'task_title' => $title,
            'task_date' => $date,
            'action' => $action,
            'actor_name' => $actor->name,
            'actor_username' => $actor->username,
            'note' => $note,
        ]);
    }
}
