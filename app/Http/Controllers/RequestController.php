<?php

namespace App\Http\Controllers;

use App\Exceptions\ApprovalException;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveApprovalService;
use App\Support\Dates;
use Carbon\CarbonImmutable;
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
        $days = Dates::inclusiveDays($start, $end);

        if ($days > 31) {
            throw ValidationException::withMessages([
                'end_date' => 'A request can cover at most 31 days.',
            ]);
        }

        if ($data['type'] === 'SCHEDULE_CHANGE') {
            if ($start !== $end) {
                throw ValidationException::withMessages([
                    'end_date' => 'A schedule change is for one date.',
                ]);
            }

            if (! in_array($data['requested_status'] ?? null, ['SHIFT', 'OFF'], true)) {
                throw ValidationException::withMessages([
                    'requested_status' => 'Choose ON or OFF for a schedule change.',
                ]);
            }
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

        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'start_date' => $start,
            'end_date' => $end,
            'requested_status' => $data['type'] === 'SCHEDULE_CHANGE' ? $data['requested_status'] : null,
            'reason' => trim($data['reason']),
            'status' => 'PENDING',
        ]);

        return back()->with('status', 'Request submitted.');
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
}
