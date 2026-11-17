<?php

namespace Tests\Feature;

use App\Models\DayTask;
use App\Models\RosterEntry;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\IndonesianPublicHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_set_a_shift_and_staff_cannot(): void
    {
        $manager = User::factory()->manager()->create();
        $staff = User::factory()->create(['name' => 'IT Officer']);

        $this->actingAsRoster($staff)->postJson(route('roster.upsert'), [
            'user_id' => $staff->id,
            'date' => '2026-11-03',
            'status' => 'SHIFT',
            'note' => '',
        ])->assertForbidden();

        $this->actingAsRoster($manager)->postJson(route('roster.upsert'), [
            'user_id' => $staff->id,
            'date' => '2026-11-03',
            'status' => 'SHIFT',
            'note' => '',
        ])->assertOk()
            ->assertJsonPath('entry.status', 'SHIFT')
            ->assertJsonPath('entry.note', '08:00 - 17:00');

        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-03',
            'status' => 'SHIFT',
            'note' => '08:00 - 17:00',
        ]);
    }

    public function test_manager_can_save_sick_leave_and_public_holiday(): void
    {
        $manager = User::factory()->manager()->create();

        foreach (['SL', 'PH'] as $status) {
            $this->actingAsRoster($manager)->postJson(route('roster.upsert'), [
                'user_id' => $manager->id,
                'date' => '2026-11-04',
                'status' => $status,
            ])->assertOk()
                ->assertJsonPath('entry.status', $status);
        }

        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $manager->id,
            'date' => '2026-11-04',
            'status' => 'PH',
        ]);
    }

    public function test_autofill_does_not_overwrite_existing_entries(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create();
        $staff = User::factory()->create();

        RosterEntry::query()->create([
            'user_id' => $staff->id,
            'date' => '2026-11-02',
            'status' => 'AL',
            'note' => 'Already booked',
        ]);

        $this->actingAsRoster($manager)->post(route('roster.autofill'), [
            'month' => 11,
            'year' => 2026,
        ])->assertRedirect(route('roster.index', ['month' => 11, 'year' => 2026]));

        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-02',
            'status' => 'AL',
            'note' => 'Already booked',
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-01',
            'status' => 'OFF',
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-03',
            'status' => 'SHIFT',
            'note' => '08:00 - 17:00',
        ]);
    }

    public function test_public_holidays_are_seeded_once_and_the_roster_page_stays_light(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create();

        RosterEntry::query()->create([
            'user_id' => $manager->id,
            'date' => '2026-12-25',
            'status' => 'SHIFT',
            'note' => '08:00 - 17:00',
        ]);
        RosterEntry::query()->create([
            'user_id' => $manager->id,
            'date' => '2026-03-21',
            'status' => 'OFF',
        ]);

        $this->seed(IndonesianPublicHolidaySeeder::class);
        $this->seed(IndonesianPublicHolidaySeeder::class);

        $this->assertDatabaseHas('day_tasks', [
            'date' => '2026-12-25',
            'title' => 'Christmas',
            'kind' => 'holiday',
        ]);
        $this->assertDatabaseHas('day_tasks', [
            'date' => '2026-05-01',
            'title' => 'Labour Day',
            'kind' => 'holiday',
        ]);
        $this->assertDatabaseHas('day_tasks', [
            'date' => '2027-01-05',
            'title' => "Isra and Mi'raj",
            'kind' => 'holiday',
        ]);
        $this->assertDatabaseHas('day_tasks', [
            'date' => '2027-08-17',
            'title' => 'Independence Day',
            'kind' => 'holiday',
        ]);
        $this->assertSame(35, DayTask::query()->where('kind', 'holiday')->count());
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $manager->id,
            'date' => '2026-12-25',
            'status' => 'PH',
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $manager->id,
            'date' => '2026-03-21',
            'status' => 'OFF',
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $manager->id,
            'date' => '2027-01-05',
            'status' => 'PH',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAsRoster($manager)
            ->get(route('roster.index', ['month' => 12, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Christmas')
            ->assertSee('Public holiday')
            ->assertSee("New Year's Day")
            ->assertSee("Isra and Mi'raj");

        $queries = collect(DB::getQueryLog())->pluck('query');
        $sql = strtolower($queries->implode("\n"));

        $this->assertSame(5, $queries->count(), $queries->implode("\n"));
        $this->assertStringNotContainsString('exists', $sql);
        $this->assertStringNotContainsString('::date', $sql);
        $this->assertSame(1, DayTask::query()->where('date', '2026-12-25')->where('kind', 'holiday')->count());
    }

    public function test_autofill_skips_public_holidays(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create();

        DayTask::query()->create([
            'date' => '2026-11-18',
            'title' => 'National Day',
            'kind' => 'holiday',
            'user_id' => $manager->id,
        ]);

        $this->actingAsRoster($manager)->post(route('roster.autofill'), [
            'month' => 11,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertDatabaseMissing('roster_entries', [
            'user_id' => $manager->id,
            'date' => '2026-11-18',
        ]);
        $this->assertDatabaseHas('roster_entries', [
            'user_id' => $manager->id,
            'date' => '2026-11-19',
            'status' => 'SHIFT',
        ]);
    }

    public function test_export_downloads_a_color_coded_workbook(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'IT Manager']);

        RosterEntry::query()->create([
            'user_id' => $manager->id,
            'date' => '2026-11-02',
            'status' => 'SHIFT',
            'note' => '08:00 - 17:00',
        ]);

        $response = $this->actingAsRoster($manager)->get(route('roster.export', [
            'month' => 11,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('IT Manager', $sheet->getCell('A2')->getValue());
        $this->assertSame('ON', (string) $sheet->getCell('D2')->getValue());
        $this->assertSame('FFA7F3D0', $sheet->getStyle('D2')->getFill()->getStartColor()->getARGB());

        @unlink($path);
    }

    public function test_seeder_builds_the_current_month(): void
    {
        Carbon::setTestNow('2026-11-17');

        $this->seed();

        $this->assertDatabaseHas('users', [
            'username' => 'aagam648',
            'name' => 'Andi Agam',
            'role' => 'IT Manager',
            'job_title' => 'IT Manager',
            'pin' => '2208',
            'is_active' => true,
            'al_balance' => 12,
            'dp_balance' => 2,
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'trainee2',
            'al_balance' => 6,
            'dp_balance' => 0,
        ]);

        $manager = User::query()->where('username', 'aagam648')->first();
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $manager->id)->whereDate('date', '2026-11-01')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $manager->id)->whereDate('date', '2026-11-02')->value('status'));

        $this->actingAsRoster($manager)
            ->get(route('roster.index', ['month' => 11, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Bintang')
            ->assertSee('Kholis')
            ->assertSee('Bernard')
            ->assertSee('ON')
            ->assertDontSee('08:00 - 17:00');
    }
}
