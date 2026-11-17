<?php

namespace Tests\Feature;

use App\Models\RosterEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EmploymentWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_roster_and_export_follow_each_employment_window(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create(['name' => 'Andi Agam']);
        $ended = User::factory()->create([
            'name' => 'Former Trainee',
            'role' => 'IT Trainee',
            'start_date' => '2026-06-01',
            'end_date' => '2026-10-31',
            'is_active' => false,
        ]);
        $finishing = User::factory()->create([
            'name' => 'Finishing Trainee',
            'role' => 'IT Trainee',
            'start_date' => '2026-06-01',
            'end_date' => '2026-11-10',
            'is_active' => false,
        ]);
        $archived = User::factory()->create([
            'name' => 'Archived Officer',
            'is_active' => false,
        ]);
        $future = User::factory()->create([
            'name' => 'Future Hire',
            'start_date' => '2026-12-01',
        ]);

        RosterEntry::query()->create([
            'user_id' => $archived->id,
            'date' => '2026-10-05',
            'status' => 'SHIFT',
        ]);

        $november = $this->actingAsRoster($manager)->get(route('roster.index', ['month' => 11, 'year' => 2026]));
        $november->assertOk();
        $this->assertRosterShows($november->getContent(), 'Andi Agam');
        $this->assertRosterShows($november->getContent(), 'Finishing Trainee');
        $this->assertRosterHides($november->getContent(), 'Future Hire');
        $this->assertRosterHides($november->getContent(), 'Former Trainee');
        $this->assertRosterHides($november->getContent(), 'Archived Officer');

        $october = $this->actingAsRoster($manager)->get(route('roster.index', ['month' => 10, 'year' => 2026]));
        $october->assertOk();
        $this->assertRosterShows($october->getContent(), 'Former Trainee');
        $this->assertRosterShows($october->getContent(), 'Finishing Trainee');
        $this->assertRosterShows($october->getContent(), 'Archived Officer');
        $this->assertRosterHides($october->getContent(), 'Future Hire');

        $december = $this->actingAsRoster($manager)->get(route('roster.index', ['month' => 12, 'year' => 2026]));
        $december->assertOk();
        $this->assertRosterShows($december->getContent(), 'Future Hire');
        $this->assertRosterHides($december->getContent(), 'Former Trainee');
        $this->assertRosterHides($december->getContent(), 'Finishing Trainee');
        $this->assertRosterHides($december->getContent(), 'Archived Officer');

        $response = $this->actingAsRoster($manager)->get(route('roster.export', ['month' => 12, 'year' => 2026]));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        $names = [];

        for ($row = 2; $row <= 10; $row++) {
            $value = $sheet->getCell('A'.$row)->getValue();

            if ($value) {
                $names[] = (string) $value;
            }
        }

        @unlink($path);

        $this->assertContains('Future Hire', $names);
        $this->assertNotContains('Finishing Trainee', $names);
        $this->assertNotContains('Former Trainee', $names);
    }

    public function test_autofill_respects_the_start_and_leaves_later_months_alone(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create();
        $trainee = User::factory()->create([
            'name' => 'Bernard',
            'username' => 'trainee2',
            'role' => 'IT Trainee',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
        ]);
        $future = User::factory()->create([
            'name' => 'Future Hire',
            'start_date' => '2026-12-01',
        ]);

        $this->actingAsRoster($manager)->post(route('roster.autofill'), [
            'month' => 11,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertDatabaseMissing('roster_entries', [
            'user_id' => $trainee->id,
            'date' => '2026-11-09',
        ]);
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-10')->value('status'));
        $this->assertSame('BLACK', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-13')->value('status'));
        $this->assertSame(0, RosterEntry::query()->where('user_id', $future->id)->count());

        $this->actingAsRoster($manager)->post(route('roster.autofill'), [
            'month' => 12,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertSame(0, RosterEntry::query()->where('user_id', $trainee->id)->where('date', '>=', '2026-12-01')->count());
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $future->id)->whereDate('date', '2026-12-01')->value('status'));
        $this->assertDatabaseMissing('roster_entries', [
            'user_id' => $future->id,
            'date' => '2026-11-30',
        ]);
    }

    public function test_an_ended_employee_cannot_sign_in_and_an_open_session_is_cleared(): void
    {
        Carbon::setTestNow('2026-11-17');
        $ended = User::factory()->create([
            'username' => 'ended',
            'pin' => '2208',
            'end_date' => '2026-11-16',
        ]);
        $lastDay = User::factory()->create([
            'username' => 'lastday',
            'pin' => '2208',
            'end_date' => '2026-11-17',
        ]);

        $this->post('/login', [
            'username' => 'ended',
            'pin' => '2208',
        ])->assertSessionHasErrors('username');
        $this->assertNull(session('roster_user_id'));

        $this->actingAsRoster($ended)
            ->get(route('roster.index'))
            ->assertRedirect(route('login'));
        $this->assertNull(session('roster_user_id'));

        $this->post('/login', [
            'username' => 'lastday',
            'pin' => '2208',
        ])->assertRedirect(route('roster.index'));
    }

    public function test_team_page_shows_the_employment_period_and_an_ended_badge(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create();

        $this->actingAsRoster($manager)->post(route('team.store'), [
            'name' => 'Six Month Trainee',
            'username' => 'sixmonth',
            'job_title' => 'IT Trainee',
            'role' => 'IT Trainee',
            'pin' => '444444',
            'start_date' => '2026-05-01',
            'end_date' => '2026-10-31',
            'al_balance' => 6,
            'dp_balance' => 0,
        ])->assertRedirect(route('team.index'));

        $hire = User::query()->where('username', 'sixmonth')->first();
        $this->assertSame('2026-05-01', $hire->start_date?->toDateString());
        $this->assertSame('2026-10-31', $hire->end_date?->toDateString());

        $this->actingAsRoster($manager)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('Start date')
            ->assertSee('End date')
            ->assertSee('Start 01 May 2026')
            ->assertSee('End 31 Oct 2026')
            ->assertSee('Ended');

        $this->actingAsRoster($manager)->post(route('team.store'), [
            'name' => 'Backwards',
            'username' => 'backwards',
            'job_title' => 'IT Staff',
            'role' => 'IT Staff',
            'pin' => '444444',
            'start_date' => '2026-12-01',
            'end_date' => '2026-11-01',
            'al_balance' => 12,
            'dp_balance' => 0,
        ])->assertSessionHasErrors('end_date');
    }

    private function assertRosterShows(string $html, string $name): void
    {
        $this->assertStringContainsString('font-medium text-on-surface">'.$name, $html);
    }

    private function assertRosterHides(string $html, string $name): void
    {
        $this->assertStringNotContainsString('font-medium text-on-surface">'.$name, $html);
    }
}
