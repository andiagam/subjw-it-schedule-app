<?php

namespace App\Http\Controllers;

use App\Models\DayTask;
use App\Models\User;
use App\Services\RosterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DayTaskController extends Controller
{
    public function store(Request $request, RosterService $roster): RedirectResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('rosterUser');

        $data = $this->validated($request);
        $date = CarbonImmutable::parse($data['date'])->startOfDay();

        DayTask::query()->create([
            'date' => $date->toDateString(),
            'title' => trim($data['title']),
            'kind' => $data['kind'],
            'user_id' => $user->id,
            'is_done' => false,
        ]);

        if ($data['kind'] === 'holiday') {
            $roster->applyPublicHoliday($date->toDateString());
        }

        return redirect()
            ->route('roster.index', ['month' => $date->month, 'year' => $date->year])
            ->with('status', 'Added to the calendar.');
    }

    public function update(Request $request, DayTask $dayTask, RosterService $roster): RedirectResponse
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

        return redirect()
            ->route('roster.index', ['month' => $date->month, 'year' => $date->year])
            ->with('status', 'Calendar updated.');
    }

    public function destroy(DayTask $dayTask): RedirectResponse
    {
        $date = $dayTask->date;
        $dayTask->delete();

        return redirect()
            ->route('roster.index', ['month' => $date->month, 'year' => $date->year])
            ->with('status', 'Removed from the calendar.');
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
