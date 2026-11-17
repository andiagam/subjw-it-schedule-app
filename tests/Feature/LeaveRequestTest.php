<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_leave_over_balance_is_rejected(): void
    {
        $staff = User::factory()->create(['al_balance' => 1]);

        $this->actingAsRoster($staff)->post(route('requests.store'), [
            'form' => 'leave',
            'type' => 'AL',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'reason' => 'Trip',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_approving_annual_leave_updates_the_roster_and_balance(): void
    {
        $staff = User::factory()->create(['al_balance' => 5]);
        $manager = User::factory()->manager()->create();

        $this->actingAsRoster($staff)->post(route('requests.store'), [
            'type' => 'AL',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'reason' => 'Family visit',
        ])->assertSessionHas('status');

        $leave = LeaveRequest::query()->first();

        $this->actingAsRoster($staff)
            ->post(route('requests.approve', $leave), ['manager_note' => 'No'])
            ->assertForbidden();

        $this->actingAsRoster($manager)
            ->post(route('requests.approve', $leave), ['manager_note' => 'Approved'])
            ->assertSessionHas('status');

        $staff->refresh();
        $leave->refresh();

        $this->assertSame(3, $staff->al_balance);
        $this->assertSame('APPROVED', $leave->status);
        $this->assertSame('Approved', $leave->manager_note);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-02',
            'status' => 'AL',
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-03',
            'status' => 'AL',
        ]);

        $this->actingAsRoster($manager)
            ->post(route('requests.approve', $leave))
            ->assertSessionHas('error');

        $this->assertSame(3, $staff->refresh()->al_balance);
    }

    public function test_approval_fails_when_the_balance_was_spent(): void
    {
        $staff = User::factory()->create(['al_balance' => 1]);
        $manager = User::factory()->manager()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $staff->id,
            'type' => 'AL',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-04',
            'reason' => 'Too long',
            'status' => 'PENDING',
        ]);

        $this->actingAsRoster($manager)
            ->post(route('requests.approve', $leave))
            ->assertSessionHas('error');

        $this->assertSame('PENDING', $leave->refresh()->status);
        $this->assertSame(1, $staff->refresh()->al_balance);
        $this->assertDatabaseMissing('roster_entries', [
            'user_id' => $staff->id,
            'status' => 'AL',
        ]);
    }

    public function test_schedule_change_can_credit_a_day_pending(): void
    {
        $staff = User::factory()->create(['dp_balance' => 0]);
        $manager = User::factory()->manager()->create();

        RosterEntry::query()->create([
            'user_id' => $staff->id,
            'date' => '2026-11-07',
            'status' => 'OFF',
        ]);

        $leave = LeaveRequest::query()->create([
            'user_id' => $staff->id,
            'type' => 'SCHEDULE_CHANGE',
            'start_date' => '2026-11-07',
            'end_date' => '2026-11-07',
            'requested_status' => 'SHIFT',
            'reason' => 'Cover the weekend maintenance window',
            'status' => 'PENDING',
        ]);

        $this->actingAsRoster($manager)->post(route('requests.approve', $leave), [
            'credit_dp' => '1',
            'manager_note' => 'Extra off earned',
        ])->assertSessionHas('status');

        $this->assertSame(1, $staff->refresh()->dp_balance);
        $this->assertTrue($leave->refresh()->credit_dp);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-07',
            'status' => 'SHIFT',
        ]);
    }

    public function test_rejection_leaves_the_roster_unchanged(): void
    {
        $staff = User::factory()->create(['dp_balance' => 2]);
        $manager = User::factory()->manager()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $staff->id,
            'type' => 'DP',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-10',
            'reason' => 'Rest',
            'status' => 'PENDING',
        ]);

        $this->actingAsRoster($manager)->post(route('requests.reject', $leave), [
            'manager_note' => 'Need coverage',
        ])->assertSessionHas('status');

        $this->assertSame('REJECTED', $leave->refresh()->status);
        $this->assertSame(2, $staff->refresh()->dp_balance);
        $this->assertDatabaseCount('roster_entries', 0);
    }

    public function test_sick_leave_approval_does_not_spend_a_balance(): void
    {
        $staff = User::factory()->create(['al_balance' => 5, 'dp_balance' => 2]);
        $manager = User::factory()->manager()->create();

        $this->actingAsRoster($staff)->post(route('requests.store'), [
            'type' => 'SL',
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'reason' => 'Flu',
        ])->assertSessionHas('status');

        $leave = LeaveRequest::query()->first();

        $this->actingAsRoster($manager)
            ->post(route('requests.approve', $leave))
            ->assertSessionHas('status');

        $this->assertSame(5, $staff->refresh()->al_balance);
        $this->assertSame(2, $staff->dp_balance);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-12',
            'status' => 'SL',
        ]);
    }
}
