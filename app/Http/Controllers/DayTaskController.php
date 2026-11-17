<?php

namespace App\Http\Controllers;

use App\Models\DayTask;
use App\Models\TaskActivityLog;
use App\Models\User;
use App\Services\RosterService;
use App\Services\TaskActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DayTaskController extends Controller
{
    public function store(Request $request, RosterService $roster, TaskActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $data = $this->validated($request);
        $date = CarbonImmutable::parse($data['date'])->startOfDay();

        $task = DayTask::query()->create([
            'date' => $date->toDateString(),
            'title' => trim($data['title']),
            'kind' => $data['kind'],
            'user_id' => $user->id,
            'is_done' => false,
            'created_by_name' => $user->name,
        ]);

        $logs = [];

        if ($data['kind'] === 'task') {
            $logs[] = $activity->record($task->id, $task->title, $task->date->toDateString(), 'added', $user);
        }

        if ($data['kind'] === 'holiday') {
            $roster->applyPublicHoliday($date->toDateString());
        }

        return $this->respond($request, $task, 'Added to the calendar.', $logs);
    }

    public function update(Request $request, DayTask $dayTask, RosterService $roster, TaskActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $data = $this->validated($request);
        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        $title = trim($data['title']);
        $isDone = $data['kind'] === 'task' && $request->boolean('is_done');
        $originalKind = $dayTask->kind;
        $originalDate = $dayTask->date->toDateString();
        $originalDone = (bool) $dayTask->is_done;
        $contentChanged = $dayTask->title !== $title || $originalDate !== $date->toDateString() || $originalKind !== $data['kind'];
        $doneChanged = $originalDone !== $isDone;

        $dayTask->update([
            'date' => $date->toDateString(),
            'title' => $title,
            'kind' => $data['kind'],
            'is_done' => $isDone,
            'updated_by_name' => $contentChanged ? $user->name : $dayTask->updated_by_name,
        ]);

        $logs = [];
        $tracked = $originalKind === 'task' || $data['kind'] === 'task';

        if ($tracked && $contentChanged) {
            $logs[] = $activity->record($dayTask->id, $dayTask->title, $dayTask->date->toDateString(), 'edited', $user);
        }

        if ($data['kind'] === 'task' && $doneChanged) {
            $logs[] = $activity->record(
                $dayTask->id,
                $dayTask->title,
                $dayTask->date->toDateString(),
                $isDone ? 'completed' : 'uncompleted',
                $user,
            );
        }

        if ($data['kind'] === 'holiday') {
            $roster->applyPublicHoliday($date->toDateString());
        }

        return $this->respond($request, $dayTask, 'Calendar updated.', $logs);
    }

    public function destroy(Request $request, DayTask $dayTask, TaskActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $date = $dayTask->date;
        $logs = [];

        if ($dayTask->kind === 'task') {
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
    private function respond(Request $request, DayTask $task, string $message, array $logs = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'task' => $this->taskPayload($task),
                'activity' => $this->activityPayload($logs),
            ]);
        }

        return redirect()
            ->route('roster.index', ['month' => $task->date->month, 'year' => $task->date->year])
            ->with('status', $message);
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
     * @return array{id: int, date: string, title: string, kind: string, is_done: bool, created_by_name: ?string, updated_by_name: ?string}
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
        ];
    }

    /**
     * @param  array<int, TaskActivityLog>  $logs
     * @return array<int, array{action: string, actor_name: string, task_title: string, task_date: string, when: string}>
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
        return $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['event', 'task', 'holiday'])],
            'is_done' => ['nullable', 'boolean'],
        ]);
    }
}
