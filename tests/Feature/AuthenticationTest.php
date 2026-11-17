<?php

namespace Tests\Feature;

use App\Models\User;
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
            ->assertDontSee('1234')
            ->assertDontSee('Demo accounts');
    }
}
