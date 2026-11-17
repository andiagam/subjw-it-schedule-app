<?php

namespace Tests\Feature;

use App\Models\User;
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
            'role' => 'STAFF',
            'pin' => '4444',
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
            'role' => 'STAFF',
            'pin' => '5555',
            'al_balance' => 20,
            'dp_balance' => 3,
        ])->assertRedirect(route('team.index'));

        $hire->refresh();
        $this->assertSame(20, $hire->al_balance);
        $this->assertSame(3, $hire->dp_balance);
        $this->assertSame('5555', $hire->pin);

        $this->actingAsRoster($manager)
            ->get(route('team.index'))
            ->assertOk()
            ->assertDontSee('>5555<', false);

        $this->actingAsRoster($manager)
            ->delete(route('team.archive', $hire))
            ->assertRedirect(route('team.index'));

        $this->assertFalse($hire->refresh()->is_active);
        $this->assertNotNull(User::query()->find($hire->id));

        $this->flushSession();

        $this->post('/login', [
            'username' => 'newhire',
            'pin' => '5555',
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
            'role' => 'STAFF',
            'pin' => $manager->pin,
            'al_balance' => $manager->al_balance,
            'dp_balance' => $manager->dp_balance,
        ])->assertSessionHas('error');

        $this->assertSame('MANAGER', $manager->refresh()->role);
    }
}
