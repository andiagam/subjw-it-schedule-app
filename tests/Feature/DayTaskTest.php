<?php

namespace Tests\Feature;

use App\Models\DayTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_add_and_remove_events_tasks_and_holidays(): void
    {
        Carbon::setTestNow('2026-11-17');
        $staff = User::factory()->create();
        $other = User::factory()->create();

        foreach ([
            ['date' => '2026-11-18', 'title' => 'Network maintenance', 'kind' => 'event'],
            ['date' => '2026-11-19', 'title' => 'Replace the switch', 'kind' => 'task'],
            ['date' => '2026-11-20', 'title' => 'National Day', 'kind' => 'holiday'],
        ] as $payload) {
            $this->actingAsRoster($staff)
                ->post(route('roster.tasks.store'), $payload)
                ->assertRedirect(route('roster.index', [
                    'month' => (int) substr($payload['date'], 5, 2),
                    'year' => 2026,
                ]));
        }

        $this->assertDatabaseHas('day_tasks', [
            'date' => '2026-11-18',
            'title' => 'Network maintenance',
            'kind' => 'event',
            'user_id' => $staff->id,
        ]);

        $this->actingAsRoster($other)
            ->get(route('roster.index', ['month' => 11, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Network maintenance')
            ->assertSee('Replace the switch')
            ->assertSee('National Day')
            ->assertSee('data-red-day="2026-11-20"', false)
            ->assertDontSee('id="sum-', false);

        $event = DayTask::query()->where('title', 'Network maintenance')->first();

        $this->actingAsRoster($other)
            ->delete(route('roster.tasks.destroy', $event))
            ->assertRedirect(route('roster.index', ['month' => 11, 'year' => 2026]));

        $this->assertDatabaseMissing('day_tasks', ['id' => $event->id]);
    }

    public function test_guests_cannot_change_the_calendar(): void
    {
        $this->post(route('roster.tasks.store'), [
            'date' => '2026-11-20',
            'title' => 'Should not save',
            'kind' => 'task',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('day_tasks', 0);
    }

    public function test_pending_tasks_are_rejected(): void
    {
        $staff = User::factory()->create();

        $this->actingAsRoster($staff)->post(route('roster.tasks.store'), [
            'date' => '2026-11-20',
            'title' => 'Old pending item',
            'kind' => 'pending',
        ])->assertSessionHasErrors('kind');

        $this->assertDatabaseCount('day_tasks', 0);
    }

    public function test_a_task_can_be_marked_done(): void
    {
        $staff = User::factory()->create();
        $task = DayTask::query()->create([
            'date' => '2026-12-08',
            'title' => 'Close the ticket',
            'kind' => 'task',
            'user_id' => $staff->id,
            'is_done' => false,
        ]);

        $this->actingAsRoster($staff)->put(route('roster.tasks.update', $task), [
            'date' => '2026-12-08',
            'title' => 'Close the ticket',
            'kind' => 'task',
            'is_done' => '1',
        ])->assertRedirect(route('roster.index', ['month' => 12, 'year' => 2026]));

        $this->assertTrue($task->refresh()->is_done);

        $this->actingAsRoster($staff)
            ->get(route('roster.index', ['month' => 12, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Close the ticket')
            ->assertSee('line-through', false);
    }

    public function test_a_public_holiday_fills_empty_and_working_days_and_keeps_leave(): void
    {
        $staff = User::factory()->create();
        $other = User::factory()->create();

        \App\Models\RosterEntry::query()->create([
            'user_id' => $staff->id,
            'date' => '2026-11-20',
            'status' => 'SHIFT',
            'note' => '08:00 - 17:00',
        ]);
        \App\Models\RosterEntry::query()->create([
            'user_id' => $other->id,
            'date' => '2026-11-20',
            'status' => 'AL',
            'note' => 'Booked',
        ]);

        $this->actingAsRoster($staff)->post(route('roster.tasks.store'), [
            'date' => '2026-11-20',
            'title' => 'National Day',
            'kind' => 'holiday',
        ])->assertRedirect();

        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-20',
            'status' => 'PH',
            'note' => null,
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $other->id,
            'date' => '2026-11-20',
            'status' => 'AL',
            'note' => 'Booked',
        ]);
    }
}
