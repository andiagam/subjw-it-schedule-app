<?php

namespace App\Services;

use App\Exceptions\ApprovalException;
use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    public function approve(LeaveRequest $leaveRequest, User $reviewer, ?string $note, bool $creditDp): void
    {
        DB::transaction(function () use ($leaveRequest, $reviewer, $note, $creditDp) {
            $locked = LeaveRequest::query()->whereKey($leaveRequest->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'PENDING') {
                throw new ApprovalException('This request has already been reviewed.');
            }

            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();

            if (! $user || ! $user->is_active) {
                throw new ApprovalException('That employee is no longer active.');
            }

            $days = $locked->dayCount();

            if ($days < 1) {
                throw new ApprovalException('The request dates are invalid.');
            }

            if ($locked->type === 'AL') {
                if ($user->al_balance < $days) {
                    throw new ApprovalException('Annual leave balance is no longer enough to approve this request.');
                }

                $this->writeRange($user, $locked, 'AL');
                $user->decrement('al_balance', $days);
            } elseif ($locked->type === 'DP') {
                if ($user->dp_balance < $days) {
                    throw new ApprovalException('Day pending balance is no longer enough to approve this request.');
                }

                $this->writeRange($user, $locked, 'DP');
                $user->decrement('dp_balance', $days);
            } elseif ($locked->type === 'SL') {
                $this->writeRange($user, $locked, 'SL');
            } elseif ($locked->type === 'SCHEDULE_CHANGE') {
                $status = $locked->requested_status;

                if (! in_array($status, ['SHIFT', 'OFF'], true)) {
                    throw new ApprovalException('The requested schedule status is missing.');
                }

                foreach ($locked->dates() as $date) {
                    RosterEntry::query()->updateOrCreate(
                        ['user_id' => $user->id, 'date' => $date],
                        [
                            'status' => $status,
                            'note' => $status === 'SHIFT' ? '08:00 - 17:00' : null,
                        ],
                    );
                }

                if ($creditDp) {
                    $user->increment('dp_balance');
                    $locked->credit_dp = true;
                }
            } else {
                throw new ApprovalException('This request type cannot be approved.');
            }

            $locked->status = 'APPROVED';
            $locked->manager_note = $note !== null && trim($note) !== '' ? trim($note) : null;
            $locked->reviewed_by = $reviewer->id;
            $locked->save();
        });
    }

    public function reject(LeaveRequest $leaveRequest, User $reviewer, ?string $note): void
    {
        DB::transaction(function () use ($leaveRequest, $reviewer, $note) {
            $locked = LeaveRequest::query()->whereKey($leaveRequest->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'PENDING') {
                throw new ApprovalException('This request has already been reviewed.');
            }

            $locked->status = 'REJECTED';
            $locked->manager_note = $note !== null && trim($note) !== '' ? trim($note) : null;
            $locked->reviewed_by = $reviewer->id;
            $locked->save();
        });
    }

    private function writeRange(User $user, LeaveRequest $leaveRequest, string $status): void
    {
        $start = CarbonImmutable::parse($leaveRequest->start_date)->startOfDay();

        for ($offset = 0; $offset < $leaveRequest->dayCount(); $offset++) {
            RosterEntry::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $start->addDays($offset)->toDateString(),
                ],
                [
                    'status' => $status,
                    'note' => null,
                ],
            );
        }
    }
}
