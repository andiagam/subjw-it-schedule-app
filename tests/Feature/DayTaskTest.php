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

        $this->actingAsRoster($staff)->putJson(route('roster.tasks.update', $task), [
            'date' => '2026-12-08',
            'title' => 'Close the ticket',
            'kind' => 'task',
            'is_done' => false,
        ])->assertOk()
            ->assertJsonPath('task.is_done', false);

        $this->actingAsRoster($staff)
            ->deleteJson(route('roster.tasks.destroy', $task))
            ->assertOk()
            ->assertJsonPath('task.id', $task->id);

        $this->assertDatabaseMissing('day_tasks', ['id' => $task->id]);
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

    public function test_task_changes_keep_an_activity_trail_for_every_role(): void
    {
        Carbon::setTestNow('2026-11-17 09:30:00');
        $staff = User::factory()->create(['name' => 'Bintang', 'username' => 'tnbin839']);
        $trainee = User::factory()->create([
            'name' => 'Kholis',
            'username' => 'trainee1',
            'role' => 'IT Trainee',
        ]);

        $this->actingAsRoster($trainee)
            ->get(route('roster.index'))
            ->assertOk()
            ->assertSee("Today's Tasks")
            ->assertSee('+ Add task')
            ->assertSee("Today's Events")
            ->assertSee('+ Add event')
            ->assertSee('Activity Log')
            ->assertSee('No tasks for today.')
            ->assertSee('No events for today.');

        $this->actingAsRoster($staff)->post(route('roster.tasks.store'), [
            'date' => '2026-11-17',
            'title' => 'Replace switch',
            'kind' => 'task',
        ])->assertRedirect();

        $task = DayTask::query()->where('title', 'Replace switch')->first();
        $this->assertSame('Bintang', $task->created_by_name);
        $this->assertDatabaseHas('task_activity_logs', [
            'day_task_id' => $task->id,
            'task_title' => 'Replace switch',
            'task_date' => '2026-11-17',
            'action' => 'added',
            'actor_name' => 'Bintang',
            'actor_username' => 'tnbin839',
        ]);

        $this->actingAsRoster($trainee)->put(route('roster.tasks.update', $task), [
            'date' => '2026-11-17',
            'title' => 'Replace the switch',
            'kind' => 'task',
            'is_done' => '0',
        ])->assertRedirect();

        $this->assertSame('Kholis', $task->refresh()->updated_by_name);
        $this->assertDatabaseHas('task_activity_logs', [
            'day_task_id' => $task->id,
            'action' => 'edited',
            'actor_name' => 'Kholis',
        ]);

        $this->actingAsRoster($staff)->putJson(route('roster.tasks.update', $task), [
            'date' => '2026-11-17',
            'title' => 'Replace the switch',
            'kind' => 'task',
            'is_done' => true,
        ])->assertOk()
            ->assertJsonPath('activity.0.action', 'completed')
            ->assertJsonPath('activity.0.actor_name', 'Bintang');

        $this->actingAsRoster($staff)
            ->get(route('roster.index'))
            ->assertOk()
            ->assertSee('Replace the switch')
            ->assertSee('Added by Bintang · Edited by Kholis')
            ->assertSee('Completed');

        $this->actingAsRoster($trainee)
            ->deleteJson(route('roster.tasks.destroy', $task))
            ->assertOk()
            ->assertJsonPath('activity.0.action', 'removed');

        $this->assertDatabaseMissing('day_tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('task_activity_logs', [
            'day_task_id' => null,
            'task_title' => 'Replace the switch',
            'action' => 'removed',
            'actor_name' => 'Kholis',
            'actor_username' => 'trainee1',
        ]);

        $this->actingAsRoster($staff)->post(route('roster.tasks.store'), [
            'date' => '2026-11-18',
            'title' => 'Team lunch',
            'kind' => 'event',
        ])->assertRedirect();

        $this->assertDatabaseHas('task_activity_logs', [
            'task_title' => 'Team lunch',
            'action' => 'added',
            'actor_name' => 'Bintang',
            'actor_username' => 'tnbin839',
        ]);
    }

    public function test_today_events_follow_jakarta_and_keep_an_activity_trail(): void
    {
        Carbon::setTestNow('2026-11-17 09:30:00');
        $staff = User::factory()->create(['name' => 'Bintang', 'username' => 'tnbin839']);

        $this->actingAsRoster($staff)->postJson(route('roster.tasks.store'), [
            'date' => '2026-11-17',
            'title' => 'BOD',
            'kind' => 'event',
        ])->assertOk()
            ->assertJsonPath('task.kind', 'event')
            ->assertJsonPath('activity.0.action', 'added')
            ->assertJsonPath('activity.0.actor_name', 'Bintang');

        $event = DayTask::query()->where('title', 'BOD')->first();
        $this->assertSame('Bintang', $event->created_by_name);

        $this->actingAsRoster($staff)
            ->get(route('roster.index', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertSee("Today's Events")
            ->assertSee('BOD')
            ->assertSee('Added by Bintang')
            ->assertSee('+ Add event')
            ->assertSee('No tasks for today.');

        $this->actingAsRoster($staff)->putJson(route('roster.tasks.update', $event), [
            'date' => '2026-11-17',
            'title' => 'BOD meeting',
            'kind' => 'event',
        ])->assertOk()
            ->assertJsonPath('activity.0.action', 'edited')
            ->assertJsonPath('activity.0.actor_name', 'Bintang');

        $this->assertSame('Bintang', $event->refresh()->updated_by_name);

        $this->actingAsRoster($staff)
            ->deleteJson(route('roster.tasks.destroy', $event))
            ->assertOk()
            ->assertJsonPath('activity.0.action', 'removed');

        $this->assertDatabaseMissing('day_tasks', ['id' => $event->id]);
        $this->assertDatabaseHas('task_activity_logs', [
            'day_task_id' => null,
            'task_title' => 'BOD meeting',
            'task_date' => '2026-11-17',
            'action' => 'removed',
            'actor_name' => 'Bintang',
            'actor_username' => 'tnbin839',
        ]);
    }
}
