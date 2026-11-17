<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Pin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_edit_and_archive_employees(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAsRoster($manager)->post(route('team.store'), [
            'name' => 'New Hire',
            'username' => 'newhire',
            'job_title' => 'IT Trainee',
            'role' => 'IT Trainee',
            'pin' => '444444',
            'end_date' => '2026-12-31',
            'al_balance' => 6,
            'dp_balance' => 0,
        ])->assertRedirect(route('team.index'));

        $hire = User::query()->where('username', 'newhire')->first();
        $this->assertNotNull($hire);
        $this->assertTrue($hire->is_active);

        $this->actingAsRoster($manager)->put(route('team.update', $hire), [
            'name' => 'New Hire',
            'username' => 'newhire',
            'job_title' => 'IT Officer',
            'role' => 'IT Staff',
            'pin' => '555555',
            'al_balance' => 20,
            'dp_balance' => 3,
            'end_date' => '2026-12-31',
        ])->assertRedirect(route('team.index'));

        $hire->refresh();
        $this->assertSame(20, $hire->al_balance);
        $this->assertSame(3, $hire->dp_balance);
        $this->assertTrue(Pin::matches($hire, '555555'));
        $this->assertSame('2026-12-31', $hire->end_date?->toDateString());

        $this->actingAsRoster($manager)
            ->get(route('team.index'))
            ->assertOk()
            ->assertDontSee('>555555<', false)
            ->assertSee('End date');

        $this->actingAsRoster($manager)
            ->delete(route('team.archive', $hire))
            ->assertRedirect(route('team.index'));

        $this->assertFalse($hire->refresh()->is_active);
        $this->assertNotNull(User::query()->find($hire->id));

        $this->flushSession();

        $this->post('/login', [
            'username' => 'newhire',
            'pin' => '555555',
        ])->assertSessionHasErrors('username');
    }

    public function test_manager_cannot_archive_self_or_demote_the_last_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $other = User::factory()->manager()->create();

        $this->actingAsRoster($manager)
            ->delete(route('team.archive', $manager))
            ->assertSessionHas('error');
        $this->assertTrue($manager->refresh()->is_active);

        $this->actingAsRoster($manager)
            ->delete(route('team.archive', $other))
            ->assertSessionHas('status');
        $this->assertFalse($other->refresh()->is_active);

        $this->actingAsRoster($manager)->put(route('team.update', $manager), [
            'name' => $manager->name,
            'username' => $manager->username,
            'job_title' => $manager->job_title,
            'role' => 'IT Staff',
            'al_balance' => $manager->al_balance,
            'dp_balance' => $manager->dp_balance,
        ])->assertSessionHas('error');

        $this->assertSame('IT Manager', $manager->refresh()->role);
    }

    public function test_existing_roles_keep_leave_balances_and_lists_follow_the_hierarchy(): void
    {
        $manager = User::factory()->create([
            'name' => 'Mia',
            'username' => 'mia',
            'job_title' => 'IT Manager',
            'role' => 'MANAGER',
            'al_balance' => 12,
            'dp_balance' => 2,
        ]);
        $officer = User::factory()->create([
            'name' => 'Zoe',
            'username' => 'zoe',
            'job_title' => 'IT Officer',
            'role' => 'STAFF',
            'al_balance' => 12,
            'dp_balance' => 1,
        ]);
        $trainee = User::factory()->create([
            'name' => 'Kholis',
            'username' => 'trainee1',
            'job_title' => 'IT Trainee',
            'role' => 'STAFF',
            'al_balance' => 6,
            'dp_balance' => 0,
        ]);

        \App\Support\RoleMigrator::apply();

        $manager->refresh();
        $officer->refresh();
        $trainee->refresh();
        $this->assertSame('IT Manager', $manager->role);
        $this->assertSame(12, $manager->al_balance);
        $this->assertSame(2, $manager->dp_balance);
        $this->assertSame('IT Staff', $officer->role);
        $this->assertSame(1, $officer->dp_balance);
        $this->assertSame('IT Trainee', $trainee->role);
        $this->assertSame(6, $trainee->al_balance);
        $this->assertSame(0, $trainee->dp_balance);

        $this->actingAsRoster($manager)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSeeInOrder(['IT Manager', 'IT Staff', 'IT Trainee', 'Mia', 'Zoe', 'Kholis']);

        $this->actingAsRoster($manager)
            ->get(route('roster.index'))
            ->assertOk()
            ->assertSeeInOrder(['Mia', 'Zoe', 'Kholis']);

        $this->actingAsRoster($trainee)->get(route('team.index'))->assertForbidden();
        $this->actingAsRoster($trainee)->get(route('roster.index'))->assertOk();
    }
}
