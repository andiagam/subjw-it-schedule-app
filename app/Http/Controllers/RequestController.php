<?php

namespace App\Http\Controllers;

use App\Exceptions\ApprovalException;
use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Models\User;
use App\Services\LeaveApprovalService;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->attributes->get('rosterUser');

        $pending = $user->isManager()
            ? LeaveRequest::query()->with('user')->where('status', 'PENDING')->orderBy('created_at')->get()
            : collect();

        $history = $user->isManager()
            ? LeaveRequest::query()->with(['user', 'reviewer'])->latest()->limit(100)->get()
            : LeaveRequest::query()->with('reviewer')->where('user_id', $user->id)->latest()->get();

        return view('requests.index', [
            'pending' => $pending,
            'history' => $history,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('rosterUser');

        $data = $request->validate([
            'type' => ['required', Rule::in(['AL', 'DP', 'SL', 'SCHEDULE_CHANGE'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'requested_status' => ['nullable', Rule::in(['SHIFT', 'OFF'])],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $start = CarbonImmutable::parse($data['start_date'])->toDateString();
        $end = CarbonImmutable::parse($data['end_date'])->toDateString();
        $selectedDates = null;

        if ($data['type'] === 'SCHEDULE_CHANGE') {
            $selectedDates = $this->scheduleDates($request, $start, $end);
            $start = $selectedDates[0];
            $end = $selectedDates[array_key_last($selectedDates)];

            if (! in_array($data['requested_status'] ?? null, ['SHIFT', 'OFF'], true)) {
                throw ValidationException::withMessages([
                    'requested_status' => 'Choose ON or OFF for a schedule change.',
                ]);
            }

            $conflicts = $this->conflictingDates($user, $selectedDates);

            if ($conflicts !== []) {
                throw ValidationException::withMessages([
                    'selected_dates' => 'These dates already have a pending or approved request: '.$this->shortDates($conflicts).'.',
                ]);
            }
        } else {
            $days = Dates::inclusiveDays($start, $end);

            if ($days > 31) {
                throw ValidationException::withMessages([
                    'end_date' => 'A request can cover at most 31 days.',
                ]);
            }

            if ($data['type'] === 'AL' && $days > $user->al_balance) {
                throw ValidationException::withMessages([
                    'end_date' => "This request is {$days} days, and only {$user->al_balance} annual leave days remain.",
                ]);
            }

            if ($data['type'] === 'DP' && $days > $user->dp_balance) {
                throw ValidationException::withMessages([
                    'end_date' => "This request is {$days} days, and only {$user->dp_balance} day pending days remain.",
                ]);
            }
        }

        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'start_date' => $start,
            'end_date' => $end,
            'selected_dates' => $selectedDates,
            'requested_status' => $data['type'] === 'SCHEDULE_CHANGE' ? $data['requested_status'] : null,
            'reason' => trim($data['reason']),
            'status' => 'PENDING',
        ]);

        return back()->with('status', 'Request submitted.');
    }

    public function preview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('rosterUser');

        $data = $request->validate([
            'dates' => ['required', 'array', 'min:1', 'max:31'],
            'dates.*' => ['required', 'date_format:Y-m-d'],
        ]);

        $dates = array_values(array_unique($data['dates']));
        sort($dates);
        $entries = RosterEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn (RosterEntry $entry) => $entry->date->toDateString());
        $parts = array_map(function (string $date) use ($entries) {
            $label = $this->rosterLabel($entries->get($date)?->status);

            return CarbonImmutable::parse($date)->format('j M').' ('.$label.')';
        }, $dates);
        $conflicts = $this->conflictingDates($user, $dates);

        return response()->json([
            'summary' => 'Current roster: '.implode(', ', $parts),
            'conflict' => $conflicts === []
                ? null
                : 'These dates already have a pending or approved request: '.$this->shortDates($conflicts).'.',
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveApprovalService $approvals): RedirectResponse
    {
        $data = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:2000'],
            'credit_dp' => ['nullable', 'boolean'],
        ]);

        try {
            $approvals->approve(
                $leaveRequest,
                $this->actor($request),
                $data['manager_note'] ?? null,
                $request->boolean('credit_dp'),
            );
        } catch (ApprovalException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Request approved and the roster updated.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveApprovalService $approvals): RedirectResponse
    {
        $data = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $approvals->reject($leaveRequest, $this->actor($request), $data['manager_note'] ?? null);
        } catch (ApprovalException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Request rejected.');
    }

    private function actor(Request $request): User
    {
        $user = $request->attributes->get('rosterUser');

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function scheduleDates(Request $request, string $start, string $end): array
    {
        $raw = $request->input('selected_dates');

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'selected_dates' => 'Choose the schedule dates again.',
                ]);
            }

            $raw = $decoded;
        }

        if (! is_array($raw) || $raw === []) {
            $raw = [];
            $cursor = CarbonImmutable::parse($start)->startOfDay();
            $last = CarbonImmutable::parse($end)->startOfDay();

            while ($cursor->lte($last) && count($raw) < 32) {
                $raw[] = $cursor->toDateString();
                $cursor = $cursor->addDay();
            }
        }

        $dates = [];

        foreach ($raw as $date) {
            if (! is_string($date)) {
                throw ValidationException::withMessages([
                    'selected_dates' => 'Choose valid dates.',
                ]);
            }

            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);

            if (! $parsed || $parsed->format('Y-m-d') !== $date) {
                throw ValidationException::withMessages([
                    'selected_dates' => 'Choose valid dates.',
                ]);
            }

            $dates[$date] = $date;
        }

        $dates = array_values($dates);
        sort($dates);

        if (count($dates) > 31) {
            throw ValidationException::withMessages([
                'selected_dates' => 'A schedule change can cover at most 31 dates.',
            ]);
        }

        return $dates;
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function conflictingDates(User $user, array $dates): array
    {
        $existing = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereDate('start_date', '<=', max($dates))
            ->whereDate('end_date', '>=', min($dates))
            ->get();
        $wanted = array_flip($dates);
        $hits = [];

        foreach ($existing as $request) {
            foreach ($request->dates() as $date) {
                if (isset($wanted[$date])) {
                    $hits[$date] = $date;
                }
            }
        }

        $hits = array_values($hits);
        sort($hits);

        return $hits;
    }

    /**
     * @param  list<string>  $dates
     */
    private function shortDates(array $dates): string
    {
        return implode(', ', array_map(
            fn (string $date) => CarbonImmutable::parse($date)->format('j M'),
            $dates,
        ));
    }

    private function rosterLabel(?string $status): string
    {
        return match ($status) {
            'SHIFT' => 'ON',
            'OFF' => 'OFF',
            'AL' => 'AL',
            'DP' => 'DP',
            'SL' => 'SL',
            'PH' => 'PH',
            'BLACK' => 'Black shift',
            default => 'Not set',
        };
    }
}
