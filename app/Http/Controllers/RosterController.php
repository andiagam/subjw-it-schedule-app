<?php

namespace App\Http\Controllers;

use App\Models\DayTask;
use App\Models\RosterEntry;
use App\Models\User;
use App\Services\RosterExportService;
use App\Services\RosterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RosterController extends Controller
{
    public function index(Request $request, RosterService $roster): View
    {
        $start = $roster->monthStart($request->query('month'), $request->query('year'));
        $users = $roster->activeUsers();
        $entries = $roster->entriesForMonth($start);
        $monthEnd = $start->endOfMonth()->toDateString();
        $today = CarbonImmutable::now()->toDateString();

        $tasks = DayTask::query()
            ->with('author')
            ->whereBetween('date', [$start->toDateString(), $monthEnd])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (DayTask $task) => $task->date->toDateString());

        $upcoming = DayTask::query()
            ->with('author')
            ->where('is_done', false)
            ->whereDate('date', '>=', $today)
            ->where(function ($query) use ($start, $monthEnd) {
                $query->whereDate('date', '<', $start->toDateString())
                    ->orWhereDate('date', '>', $monthEnd);
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return view('roster.index', [
            'start' => $start,
            'previous' => $start->subMonth(),
            'next' => $start->addMonth(),
            'days' => $roster->days($start),
            'users' => $users,
            'entries' => $entries,
            'tasks' => $tasks,
            'upcoming' => $upcoming,
            'leadingBlankDays' => $start->dayOfWeekIso - 1,
        ]);
    }

    public function upsert(Request $request, RosterService $roster): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['SHIFT', 'OFF', 'AL', 'DP', 'SL', 'PH'])],
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

    public function autofill(Request $request, RosterService $roster): RedirectResponse
    {
        $start = $roster->monthStart($request->input('month'), $request->input('year'));
        $created = $roster->autofill($start);
        $message = $created === 0
            ? 'Every day this month is already filled. Public holidays were skipped.'
            : 'Filled '.$created.' empty cells. Existing entries and public holidays were left unchanged.';

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
