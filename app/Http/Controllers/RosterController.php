<?php

namespace App\Http\Controllers;

use App\Models\DayTask;
use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Models\TaskActivityLog;
use App\Models\User;
use App\Services\RosterExportService;
use App\Services\RosterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RosterController extends Controller
{
    public function index(Request $request, RosterService $roster): View
    {
        $start = $roster->monthStart($request->query('month'), $request->query('year'));
        $monthEnd = $start->endOfMonth();
        $people = $roster->peopleForRoster($start);
        $users = $people->filter(fn (User $user): bool => $roster->isVisibleInMonth($user, $start))->values();
        $entries = $roster->entriesForMonth($start);

        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $todayKey = $today->toDateString();
        $tomorrow = $today->addDay();
        $horizon = $today->addDays(30);
        $rangeStart = min($start->toDateString(), $todayKey);
        $rangeEnd = max($monthEnd->toDateString(), $todayKey);

        $calendar = DayTask::query()
            ->whereBetween('date', [$rangeStart, $rangeEnd])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $tasks = $calendar
            ->filter(fn (DayTask $task) => $task->date->greaterThanOrEqualTo($start) && $task->date->lessThanOrEqualTo($monthEnd))
            ->groupBy(fn (DayTask $task) => $task->date->toDateString());

        $upcoming = DayTask::query()
            ->whereBetween('date', [$tomorrow->toDateString(), $horizon->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $todayTasks = $calendar
            ->filter(fn (DayTask $task) => $task->kind === 'task' && $task->date->toDateString() === $todayKey)
            ->values();

        $todayEvents = $calendar
            ->filter(fn (DayTask $task) => $task->kind === 'event' && $task->date->toDateString() === $todayKey)
            ->values();

        $activityLogs = TaskActivityLog::query()->latest()->limit(30)->get();

        $currentUser = $request->attributes->get('rosterUser');

        ViewFacade::share([
            'switchUsers' => $people->filter(fn (User $user): bool => $user->is_active && ! $user->employmentHasEnded())->values(),
            'pendingRequestCount' => $currentUser instanceof User && $currentUser->isManager()
                ? LeaveRequest::query()->where('status', 'PENDING')->count()
                : 0,
        ]);

        return view('roster.index', [
            'start' => $start,
            'previous' => $start->subMonth(),
            'next' => $start->addMonth(),
            'days' => $roster->days($start),
            'users' => $users,
            'entries' => $entries,
            'tasks' => $tasks,
            'upcoming' => $upcoming,
            'todayTasks' => $todayTasks,
            'todayEvents' => $todayEvents,
            'upcomingStart' => $tomorrow->toDateString(),
            'upcomingEnd' => $horizon->toDateString(),
            'activityLogs' => $activityLogs,
            'leadingBlankDays' => $start->dayOfWeekIso - 1,
        ]);
    }

    public function upsert(Request $request, RosterService $roster): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['SHIFT', 'OFF', 'AL', 'DP', 'SL', 'PH', 'BLACK'])],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->whereKey($data['user_id'])->where('is_active', true)->first();

        if (! $user) {
            return response()->json(['message' => 'That employee is not active.'], 422);
        }

        $note = trim((string) ($data['note'] ?? ''));

        if ($data['status'] === 'SHIFT' && $note === '') {
            $note = '08:00 - 17:00';
        }

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        $entry = RosterEntry::query()->updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['status' => $data['status'], 'note' => $note !== '' ? $note : null],
        );

        $user->refresh();
        $summary = $roster->summaryFor($user, CarbonImmutable::parse($date)->startOfMonth());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Schedule updated.',
                'entry' => [
                    'user_id' => $entry->user_id,
                    'date' => $entry->date->toDateString(),
                    'status' => $entry->status,
                    'note' => $entry->note,
                ],
                'summary' => $summary,
            ]);
        }

        return back()->with('status', 'Schedule updated.');
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'dates' => ['required', 'array', 'min:1', 'max:31'],
            'dates.*' => ['required', 'date'],
            'status' => ['present', 'nullable', Rule::in(['SHIFT', 'OFF', 'AL', 'DP', 'SL', 'PH', 'BLACK'])],
        ]);

        $user = User::query()->whereKey($data['user_id'])->where('is_active', true)->first();

        if (! $user) {
            return response()->json(['message' => 'That employee is not active.'], 422);
        }

        $dates = collect($data['dates'])
            ->map(fn (string $date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();
        $status = $data['status'];

        DB::transaction(function () use ($user, $dates, $status) {
            if ($status === null) {
                RosterEntry::query()->where('user_id', $user->id)->whereIn('date', $dates)->delete();

                return;
            }

            $now = now();
            $rows = $dates->map(fn (string $date) => [
                'user_id' => $user->id,
                'date' => $date,
                'status' => $status,
                'note' => $status === 'SHIFT' ? '08:00 - 17:00' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            RosterEntry::query()->upsert($rows, ['user_id', 'date'], ['status', 'note', 'updated_at']);
        });

        return response()->json([
            'message' => 'Schedule updated.',
            'user_id' => $user->id,
            'dates' => $dates->all(),
            'status' => $status,
        ]);
    }

    public function autofill(Request $request, RosterService $roster): RedirectResponse
    {
        $start = $roster->monthStart($request->input('month'), $request->input('year'));
        $created = $roster->autofill($start);
        $message = $created === 0
            ? 'Nothing to fill. Leave, sick days, day pending, public holidays, and black shifts were left as they are.'
            : 'Updated '.$created.' days. Leave, sick days, day pending, public holidays, and black shifts stayed as they are. Days before a start date were left empty. Days after an end date were marked black.';

        return redirect()
            ->route('roster.index', ['month' => $start->month, 'year' => $start->year])
            ->with('status', $message);
    }

    public function export(Request $request, RosterService $roster, RosterExportService $export): StreamedResponse
    {
        $start = $roster->monthStart($request->query('month'), $request->query('year'));

        return $export->download($start);
    }
}
