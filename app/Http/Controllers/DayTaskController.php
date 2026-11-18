<?php

namespace App\Http\Controllers;

use App\Models\DayTask;
use App\Models\TaskActivityLog;
use App\Models\User;
use App\Services\TaskActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DayTaskController extends Controller
{
    public function store(Request $request, TaskActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $data = $this->validated($request);
        [$start, $end] = $this->range($data);
        $tasks = [];
        $logs = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $task = DayTask::query()->create([
                'date' => $day->toDateString(),
                'title' => trim($data['title']),
                'kind' => $data['kind'],
                'user_id' => $user->id,
                'is_done' => false,
                'created_by_name' => $user->name,
            ]);
            $tasks[] = $task;

            if ($this->recordsActivity($data['kind'])) {
                $logs[] = $activity->record($task->id, $task->title, $task->date->toDateString(), 'added', $user);
            }
        }

        return $this->respond($request, $tasks[0], 'Added to the calendar.', $logs, $tasks);
    }

    public function update(Request $request, DayTask $dayTask, TaskActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $data = $this->validated($request);
        [$start, $end] = $this->range($data);
        $title = trim($data['title']);
        $isDone = $data['kind'] === 'task' && $request->boolean('is_done');
        $note = $data['kind'] === 'task' && $isDone ? $this->blankToNull($data['completion_note'] ?? null) : null;
        $originalKind = $dayTask->kind;
        $originalDate = $dayTask->date->toDateString();
        $originalDone = (bool) $dayTask->is_done;
        $originalNote = $this->blankToNull($dayTask->completion_note);
        $noteChanged = $originalDone && $isDone && $originalNote !== $note;
        $contentChanged = $dayTask->title !== $title || $originalDate !== $start->toDateString() || $originalKind !== $data['kind'] || $noteChanged;
        $doneChanged = $originalDone !== $isDone;

        $completion = [
            'completion_note' => null,
            'completed_by_name' => null,
            'completed_at' => null,
        ];

        if ($isDone && $originalDone) {
            $completion = [
                'completion_note' => $note,
                'completed_by_name' => $dayTask->completed_by_name,
                'completed_at' => $dayTask->completed_at,
            ];
        } elseif ($isDone) {
            $completion = [
                'completion_note' => $note,
                'completed_by_name' => $user->name,
                'completed_at' => now(),
            ];
        }

        $dayTask->update([
            'date' => $start->toDateString(),
            'title' => $title,
            'kind' => $data['kind'],
            'is_done' => $isDone,
            'updated_by_name' => $contentChanged ? $user->name : $dayTask->updated_by_name,
            ...$completion,
        ]);

        $tasks = [$dayTask];
        $logs = [];
        $tracked = $this->recordsActivity($originalKind) || $this->recordsActivity($data['kind']);

        if ($tracked && $contentChanged && ! $doneChanged) {
            $logs[] = $activity->record($dayTask->id, $dayTask->title, $dayTask->date->toDateString(), 'edited', $user, $noteChanged ? $note : null);
        } elseif ($tracked && $contentChanged) {
            $logs[] = $activity->record($dayTask->id, $dayTask->title, $dayTask->date->toDateString(), 'edited', $user);
        }

        if ($data['kind'] === 'task' && $doneChanged) {
            $logs[] = $activity->record(
                $dayTask->id,
                $dayTask->title,
                $dayTask->date->toDateString(),
                $isDone ? 'completed' : 'uncompleted',
                $user,
                $isDone ? $note : null,
            );
        }

        for ($day = $start->addDay(); $day->lte($end); $day = $day->addDay()) {
            $extra = DayTask::query()->create([
                'date' => $day->toDateString(),
                'title' => $title,
                'kind' => $data['kind'],
                'user_id' => $user->id,
                'is_done' => false,
                'created_by_name' => $user->name,
            ]);
            $tasks[] = $extra;

            if ($this->recordsActivity($data['kind'])) {
                $logs[] = $activity->record($extra->id, $extra->title, $extra->date->toDateString(), 'added', $user);
            }
        }

        return $this->respond($request, $dayTask, 'Calendar updated.', $logs, $tasks);
    }

    public function destroy(Request $request, DayTask $dayTask, TaskActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $date = $dayTask->date;
        $logs = [];

        if ($this->recordsActivity($dayTask->kind)) {
            $logs[] = $activity->record($dayTask->id, $dayTask->title, $date->toDateString(), 'removed', $user);
        }

        $payload = $this->taskPayload($dayTask);
        $dayTask->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Removed from the calendar.',
                'task' => $payload,
                'activity' => $this->activityPayload($logs),
            ]);
        }

        return redirect()
            ->route('roster.index', ['month' => $date->month, 'year' => $date->year])
            ->with('status', 'Removed from the calendar.');
    }

    /**
     * @param  array<int, TaskActivityLog>  $logs
     */
    private function respond(Request $request, DayTask $task, string $message, array $logs = [], array $tasks = []): RedirectResponse|JsonResponse
    {
        $tasks = $tasks === [] ? [$task] : $tasks;

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'task' => $this->taskPayload($task),
                'tasks' => array_map(fn (DayTask $item) => $this->taskPayload($item), $tasks),
                'activity' => $this->activityPayload($logs),
            ]);
        }

        return redirect()
            ->route('roster.index', ['month' => $task->date->month, 'year' => $task->date->year])
            ->with('status', $message);
    }

    private function recordsActivity(string $kind): bool
    {
        return $kind === 'task' || $kind === 'event';
    }

    private function actor(Request $request): User
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user;
        }

        /** @var User $rosterUser */
        $rosterUser = $request->attributes->get('rosterUser');

        return $rosterUser;
    }

    /**
     * @return array{id: int, date: string, title: string, kind: string, is_done: bool, created_by_name: ?string, updated_by_name: ?string, completion_note: ?string, completed_by_name: ?string}
     */
    private function taskPayload(DayTask $task): array
    {
        return [
            'id' => $task->id,
            'date' => $task->date->toDateString(),
            'title' => $task->title,
            'kind' => $task->kind,
            'is_done' => (bool) $task->is_done,
            'created_by_name' => $task->created_by_name,
            'updated_by_name' => $task->updated_by_name,
            'completion_note' => $task->completion_note,
            'completed_by_name' => $task->completed_by_name,
        ];
    }

    /**
     * @param  array<int, TaskActivityLog>  $logs
     * @return array<int, array{action: string, actor_name: string, task_title: string, task_date: string, when: string, note: ?string}>
     */
    private function activityPayload(array $logs): array
    {
        return array_map(fn (TaskActivityLog $log) => $log->toActivityArray(), $logs);
    }

    /**
     * @return array{date: string, title: string, kind: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['event', 'task'])],
            'is_done' => ['nullable', 'boolean'],
            'completion_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->range($data);

        return $data;
    }

    /**
     * @param  array{date: string, end_date?: string|null}  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(array $data): array
    {
        $start = CarbonImmutable::parse($data['date'])->startOfDay();
        $end = CarbonImmutable::parse($data['end_date'] ?? $data['date'])->startOfDay();

        if ($start->diffInDays($end) > 30) {
            throw ValidationException::withMessages([
                'end_date' => 'A calendar item can cover at most 31 days.',
            ]);
        }

        return [$start, $end];
    }

    private function blankToNull(mixed $value): ?string
    {
        $note = trim((string) ($value ?? ''));

        return $note === '' ? null : $note;
    }
}
