<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_sign_in_with_username_and_pin(): void
    {
        $manager = User::factory()->manager()->create([
            'username' => 'manager',
            'pin' => '1234',
        ]);

        $this->get('/team')->assertRedirect(route('login'));

        $this->post('/login', [
            'username' => 'manager',
            'pin' => '1234',
        ])->assertRedirect(route('team.index'));

        $this->assertSame($manager->id, session('roster_user_id'));
    }

    public function test_inactive_user_and_wrong_pin_are_rejected(): void
    {
        User::factory()->inactive()->create([
            'username' => 'gone',
            'pin' => '1234',
        ]);

        $this->post('/login', [
            'username' => 'gone',
            'pin' => '1234',
        ])->assertSessionHasErrors('username');

        $this->assertNull(session('roster_user_id'));
    }

    public function test_staff_cannot_switch_users_or_open_manager_pages(): void
    {
        $staff = User::factory()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAsRoster($staff)
            ->post(route('switch-user'), ['user_id' => $manager->id])
            ->assertForbidden();

        $this->actingAsRoster($staff)->get(route('team.index'))->assertForbidden();
        $this->actingAsRoster($staff)->get(route('roster.export'))->assertForbidden();
    }

    public function test_manager_can_preview_a_staff_account_and_switch_back(): void
    {
        $manager = User::factory()->manager()->create();
        $staff = User::factory()->create();

        $this->actingAsRoster($manager)
            ->post(route('switch-user'), ['user_id' => $staff->id])
            ->assertRedirect();

        $this->assertSame($staff->id, session('roster_user_id'));
        $this->assertSame($manager->id, session('impersonator_id'));

        $this->get(route('team.index'))->assertForbidden();

        $this->post(route('switch-user'), ['user_id' => $manager->id])->assertRedirect();

        $this->assertSame($manager->id, session('roster_user_id'));
        $this->assertNull(session('impersonator_id'));
        $this->get(route('team.index'))->assertOk();
    }

    public function test_login_page_does_not_list_demo_pins(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Use your username and PIN (min. 6 digits).')
            ->assertDontSee('1234')
            ->assertDontSee('Demo accounts');
    }

    public function test_a_stored_four_digit_pin_still_signs_in_and_five_failures_lock_the_account(): void
    {
        $user = User::factory()->create([
            'username' => 'legacy',
            'pin' => '2208',
        ]);

        $this->post('/login', [
            'username' => 'legacy',
            'pin' => '2208',
        ])->assertRedirect(route('roster.index'));

        $user->refresh();
        $this->assertTrue(Pin::isHashed((string) $user->pin));
        $this->assertTrue(Pin::matches($user, '2208'));
        auth()->logout();
        session()->flush();

        $locked = User::factory()->create([
            'username' => 'locked',
            'pin' => '1111',
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post('/login', [
                'username' => 'locked',
                'pin' => '0000',
            ])->assertSessionHasErrors('username');
        }

        $this->assertFalse($locked->refresh()->is_locked);

        $this->post('/login', [
            'username' => 'locked',
            'pin' => '0000',
        ])->assertSessionHasErrors('username');

        $this->assertTrue($locked->refresh()->is_locked);
        $this->assertSame(5, $locked->failed_login_attempts);

        $this->post('/login', [
            'username' => 'locked',
            'pin' => '1111',
        ])->assertSessionHasErrors('username');
        $this->assertNull(session('roster_user_id'));
    }

    public function test_a_successful_login_clears_earlier_failures(): void
    {
        $user = User::factory()->create([
            'username' => 'retry',
            'pin' => '2208',
            'failed_login_attempts' => 4,
            'is_locked' => false,
        ]);

        $this->post('/login', [
            'username' => 'retry',
            'pin' => '2208',
        ])->assertRedirect(route('roster.index'));

        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertFalse($user->is_locked);
        $this->assertTrue(Pin::matches($user, '2208'));
    }

    public function test_staff_and_trainees_cannot_manage_the_roster_or_the_team(): void
    {
        $staff = User::factory()->create();
        $trainee = User::factory()->create(['role' => 'IT Trainee']);
        $target = User::factory()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $target->id,
            'type' => 'AL',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'reason' => 'Rest',
            'status' => 'PENDING',
        ]);

        foreach ([$staff, $trainee] as $actor) {
            $this->actingAsRoster($actor)->post(route('roster.autofill'), [
                'month' => 11,
                'year' => 2026,
            ])->assertForbidden();
            $this->actingAsRoster($actor)->post(route('requests.approve', $leave))->assertForbidden();
            $this->actingAsRoster($actor)->post(route('requests.reject', $leave))->assertForbidden();
            $this->actingAsRoster($actor)->post(route('team.store'))->assertForbidden();
            $this->actingAsRoster($actor)->put(route('team.update', $target))->assertForbidden();
            $this->actingAsRoster($actor)->delete(route('team.archive', $target))->assertForbidden();
            $this->actingAsRoster($actor)->post(route('team.unlock', $target))->assertForbidden();
            $this->actingAsRoster($actor)->post(route('team.pin', $target))->assertForbidden();
        }

        $this->assertTrue($target->refresh()->is_active);
        $this->assertSame('PENDING', $leave->refresh()->status);
    }

    public function test_anyone_can_change_their_own_pin_and_only_a_manager_can_reset_another(): void
    {
        $staff = User::factory()->create(['pin' => '1234']);
        $manager = User::factory()->manager()->create();

        $this->actingAsRoster($staff)->post(route('account.pin'), [
            'current_pin' => '0000',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertSessionHasErrors('current_pin');

        $this->actingAsRoster($staff)->post(route('account.pin'), [
            'current_pin' => '1234',
            'pin' => '12345',
            'pin_confirmation' => '12345',
        ])->assertSessionHasErrors('pin');

        $this->actingAsRoster($staff)->post(route('account.pin'), [
            'current_pin' => '1234',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(Pin::matches($staff->refresh(), '123456'));

        $this->actingAsRoster($staff)
            ->post(route('team.pin', $manager), [
                'pin' => '654321',
                'pin_confirmation' => '654321',
            ])->assertForbidden();

        $staff->forceFill(['is_locked' => true, 'failed_login_attempts' => 5])->save();

        $this->actingAsRoster($manager)->post(route('team.unlock', $staff))->assertRedirect();
        $this->assertFalse($staff->refresh()->is_locked);
        $this->assertSame(0, $staff->failed_login_attempts);

        $staff->forceFill(['is_locked' => true, 'failed_login_attempts' => 5])->save();

        $this->actingAsRoster($manager)->post(route('team.pin', $staff), [
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ])->assertRedirect()->assertSessionHas('status');

        $staff->refresh();
        $this->assertFalse($staff->is_locked);
        $this->assertTrue(Pin::matches($staff, '654321'));

        $this->actingAsRoster($manager)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('Change PIN', false);
    }
}
