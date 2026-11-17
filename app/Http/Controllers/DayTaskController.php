<?php

namespace App\Http\Controllers;

use App\Models\DayTask;
use App\Models\User;
use App\Services\RosterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DayTaskController extends Controller
{
    public function store(Request $request, RosterService $roster): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('rosterUser');

        $data = $this->validated($request);
        $date = CarbonImmutable::parse($data['date'])->startOfDay();

        $task = DayTask::query()->create([
            'date' => $date->toDateString(),
            'title' => trim($data['title']),
            'kind' => $data['kind'],
            'user_id' => $user->id,
            'is_done' => false,
        ]);

        if ($data['kind'] === 'holiday') {
            $roster->applyPublicHoliday($date->toDateString());
        }

        return $this->respond($request, $task, 'Added to the calendar.');
    }

    public function update(Request $request, DayTask $dayTask, RosterService $roster): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $date = CarbonImmutable::parse($data['date'])->startOfDay();

        $dayTask->update([
            'date' => $date->toDateString(),
            'title' => trim($data['title']),
            'kind' => $data['kind'],
            'is_done' => $request->boolean('is_done'),
        ]);

        if ($data['kind'] === 'holiday') {
            $roster->applyPublicHoliday($date->toDateString());
        }

        return $this->respond($request, $dayTask, 'Calendar updated.');
    }

    public function destroy(Request $request, DayTask $dayTask): RedirectResponse|JsonResponse
    {
        $date = $dayTask->date;
        $payload = $this->taskPayload($dayTask);
        $dayTask->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Removed from the calendar.',
                'task' => $payload,
            ]);
        }

        return redirect()
            ->route('roster.index', ['month' => $date->month, 'year' => $date->year])
            ->with('status', 'Removed from the calendar.');
    }

    private function respond(Request $request, DayTask $task, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'task' => $this->taskPayload($task),
            ]);
        }

        return redirect()
            ->route('roster.index', ['month' => $task->date->month, 'year' => $task->date->year])
            ->with('status', $message);
    }

    /**
     * @return array{id: int, date: string, title: string, kind: string, is_done: bool}
     */
    private function taskPayload(DayTask $task): array
    {
        return [
            'id' => $task->id,
            'date' => $task->date->toDateString(),
            'title' => $task->title,
            'kind' => $task->kind,
            'is_done' => (bool) $task->is_done,
        ];
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
