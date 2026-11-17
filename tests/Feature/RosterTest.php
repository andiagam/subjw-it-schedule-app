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

    public function test_autofill_uses_each_persons_default_and_keeps_leave(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create();
        $bintang = User::factory()->create([
            'name' => 'Bintang',
            'username' => 'tnbin839',
            'role' => 'IT Staff',
        ]);
        $trainee = User::factory()->create([
            'name' => 'Kholis',
            'username' => 'trainee1',
            'role' => 'IT Trainee',
        ]);

        RosterEntry::query()->create([
            'user_id' => $bintang->id,
            'date' => '2026-11-05',
            'status' => 'SHIFT',
            'note' => '08:00 - 17:00',
        ]);
        RosterEntry::query()->create([
            'user_id' => $bintang->id,
            'date' => '2026-11-07',
            'status' => 'OFF',
        ]);
        foreach (['AL' => '2026-11-02', 'SL' => '2026-11-03', 'DP' => '2026-11-04', 'PH' => '2026-11-09'] as $status => $date) {
            RosterEntry::query()->create([
                'user_id' => $bintang->id,
                'date' => $date,
                'status' => $status,
            ]);
        }

        $this->actingAsRoster($manager)->post(route('roster.autofill'), [
            'month' => 11,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-05')->value('status'));
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-06')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-07')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-01')->value('status'));
        $this->assertSame('AL', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-02')->value('status'));
        $this->assertSame('SL', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-03')->value('status'));
        $this->assertSame('DP', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-04')->value('status'));
        $this->assertSame('PH', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-09')->value('status'));
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-01')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-02')->value('status'));
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
            ->assertSee('No upcoming events or tasks in the next 30 days.')
            ->assertSee('hidden flex-wrap items-center gap-x-4 gap-y-2 text-xs text-on-surface-variant sm:flex', false)
            ->assertSee("Today's Tasks")
            ->assertSee('No tasks for today.')
            ->assertSee("Today's Events")
            ->assertSee('No events for today.')
            ->assertSee('+ Add event')
            ->assertSee('Activity Log')
            ->assertDontSee('Hover or tap a date')
            ->assertDontSee('The IT manager edits any schedule directly')
            ->assertDontSee('Events, tasks, and public holidays in');

        $logged = collect(DB::getQueryLog());
        $queries = $logged->pluck('query');
        $sql = strtolower($queries->implode("\n"));
        $logQuery = $logged->first(fn (array $query) => str_contains(strtolower($query['query']), 'task_activity_logs'));

        $this->assertSame(7, $queries->count(), $queries->implode("\n"));
        $this->assertStringNotContainsString('exists', $sql);
        $this->assertStringNotContainsString('::date', $sql);
        $upcomingQuery = $logged->first(function (array $query) {
            $statement = strtolower($query['query']);

            return str_contains($statement, 'day_tasks')
                && str_contains($statement, 'between')
                && in_array('2026-11-18', $query['bindings'], true)
                && in_array('2026-12-17', $query['bindings'], true);
        });
        $this->assertNotNull($upcomingQuery);
        $upcomingSql = strtolower($upcomingQuery['query']);
        $this->assertMatchesRegularExpression('/order by .*date.*id/', $upcomingSql);
        $this->assertStringNotContainsString('users', $upcomingSql);
        $this->assertStringNotContainsString('exists', $upcomingSql);
        $this->assertNotNull($logQuery);
        $this->assertStringContainsString('limit 30', strtolower($logQuery['query']));
        $this->assertStringNotContainsString('users', strtolower($logQuery['query']));
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
        RosterEntry::query()->create([
            'user_id' => $manager->id,
            'date' => '2026-11-03',
            'status' => 'BLACK',
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
        $this->assertSame('', (string) $sheet->getCell('E2')->getValue());
        $this->assertSame('FF000000', $sheet->getStyle('E2')->getFill()->getStartColor()->getARGB());
        $onColumn = null;

        for ($index = 1; $index <= 40; $index++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);

            if ($sheet->getCell($column.'1')->getValue() === 'ON') {
                $onColumn = $column;
                break;
            }
        }

        $this->assertNotNull($onColumn);
        $this->assertSame(1, (int) $sheet->getCell($onColumn.'2')->getValue());

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
        $bintang = User::query()->where('username', 'tnbin839')->first();
        $trainee = User::query()->where('username', 'trainee1')->first();
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $manager->id)->whereDate('date', '2026-11-01')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $manager->id)->whereDate('date', '2026-11-02')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-01')->value('status'));
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-05')->value('status'));
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-06')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $bintang->id)->whereDate('date', '2026-11-07')->value('status'));
        $this->assertSame('OFF', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-01')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-02')->value('status'));

        $this->actingAsRoster($manager)
            ->get(route('roster.index', ['month' => 11, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Bintang')
            ->assertSee('Kholis')
            ->assertSee('Bernard')
            ->assertSee('ON')
            ->assertDontSee('08:00 - 17:00');
    }

    public function test_black_shift_stays_put_and_days_after_the_end_date_are_filled_black(): void
    {
        Carbon::setTestNow('2026-11-17');
        $manager = User::factory()->manager()->create(['name' => 'Andi Agam']);
        $trainee = User::factory()->create([
            'name' => 'Bernard',
            'username' => 'trainee2',
            'role' => 'IT Trainee',
            'end_date' => '2026-11-10',
        ]);

        RosterEntry::query()->create([
            'user_id' => $trainee->id,
            'date' => '2026-11-04',
            'status' => 'BLACK',
        ]);
        RosterEntry::query()->create([
            'user_id' => $trainee->id,
            'date' => '2026-11-12',
            'status' => 'AL',
            'note' => 'Booked',
        ]);

        $this->actingAsRoster($manager)->post(route('roster.autofill'), [
            'month' => 11,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertSame('BLACK', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-04')->value('status'));
        $this->assertSame('SHIFT', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-10')->value('status'));
        $this->assertSame('BLACK', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-11')->value('status'));
        $this->assertSame('AL', RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-12')->value('status'));

        RosterEntry::query()->where('user_id', $trainee->id)->whereDate('date', '2026-11-17')->update(['status' => 'BLACK']);

        $page = $this->actingAsRoster($manager)
            ->get(route('roster.index', ['month' => 11, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Black shift (Ended)')
            ->assertSee('Multi-select');

        $this->assertDoesNotMatchRegularExpression('/Working:<\/span>[^<]*Bernard/', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/Not set:<\/span>[^<]*Bernard/', $page->getContent());
        $this->assertMatchesRegularExpression('/Working:<\/span>[^<]*'.$manager->name.'/', $page->getContent());
    }

    public function test_manager_updates_many_days_in_one_roster_write(): void
    {
        $manager = User::factory()->manager()->create();
        $staff = User::factory()->create(['name' => 'Bernard']);

        $this->actingAsRoster($staff)->postJson(route('roster.bulk-update'), [
            'user_id' => $staff->id,
            'dates' => ['2026-11-02', '2026-11-03'],
            'status' => 'OFF',
        ])->assertForbidden();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAsRoster($manager)->postJson(route('roster.bulk-update'), [
            'user_id' => $staff->id,
            'dates' => ['2026-11-02', '2026-11-03', '2026-11-04'],
            'status' => 'BLACK',
        ])->assertOk();

        $writes = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'roster_entries'));

        $this->assertCount(1, $writes);
        $this->assertStringContainsString('on conflict', strtolower($writes->first()['query']));
        $this->assertSame(3, RosterEntry::query()->where('user_id', $staff->id)->where('status', 'BLACK')->count());

        $this->actingAsRoster($manager)->postJson(route('roster.bulk-update'), [
            'user_id' => $staff->id,
            'dates' => ['2026-11-02'],
            'status' => null,
        ])->assertOk();

        $this->assertDatabaseMissing('roster_entries', [
            'user_id' => $staff->id,
            'date' => '2026-11-02',
        ]);
    }

    public function test_upcoming_lists_the_next_thirty_days_from_jakarta_today(): void
    {
        Carbon::setTestNow('2026-11-17');
        $staff = User::factory()->create();

        DayTask::query()->create(['date' => '2026-10-15', 'title' => 'October task', 'kind' => 'task', 'user_id' => $staff->id]);
        DayTask::query()->create(['date' => '2026-10-15', 'title' => 'October event', 'kind' => 'event', 'user_id' => $staff->id]);
        DayTask::query()->create(['date' => '2026-10-15', 'title' => 'October extra', 'kind' => 'task', 'user_id' => $staff->id]);
        DayTask::query()->create(['date' => '2026-10-16', 'title' => 'October holiday', 'kind' => 'holiday', 'user_id' => $staff->id]);
        DayTask::query()->create(['date' => '2026-11-17', 'title' => 'Today only', 'kind' => 'task', 'user_id' => $staff->id]);
        DayTask::query()->create(['date' => '2026-11-18', 'title' => 'Tomorrow item', 'kind' => 'event', 'user_id' => $staff->id]);
        DayTask::query()->create(['date' => '2026-12-17', 'title' => 'Horizon check', 'kind' => 'task', 'user_id' => $staff->id, 'is_done' => true]);
        DayTask::query()->create(['date' => '2026-12-18', 'title' => 'Past horizon', 'kind' => 'holiday', 'user_id' => $staff->id]);

        $october = $this->actingAsRoster($staff)->get(route('roster.index', ['month' => 10, 'year' => 2026]));
        $october->assertOk()
            ->assertSee('Tomorrow item')
            ->assertSee('Horizon check')
            ->assertSee('Today only')
            ->assertSee('min-h-[68px]', false)
            ->assertSee('line-clamp-2', false)
            ->assertSee('data-day-sheet', false)
            ->assertSee('bg-emerald-500', false)
            ->assertSee('bg-blue-500', false)
            ->assertSee('bg-purple-600', false)
            ->assertSee('+1', false)
            ->assertDontSee('break-all', false);

        $upcoming = $this->upcomingMarkup($october->getContent());
        $this->assertStringContainsString('Tomorrow item', $upcoming);
        $this->assertStringContainsString('Horizon check', $upcoming);
        $this->assertStringNotContainsString('Today only', $upcoming);
        $this->assertStringNotContainsString('Past horizon', $upcoming);

        $january = $this->actingAsRoster($staff)->get(route('roster.index', ['month' => 1, 'year' => 2027]));
        $januaryUpcoming = $this->upcomingMarkup($january->getContent());
        $this->assertStringContainsString('Tomorrow item', $januaryUpcoming);
        $this->assertStringContainsString('Horizon check', $januaryUpcoming);
        $this->assertStringNotContainsString('Past horizon', $januaryUpcoming);
    }

    private function upcomingMarkup(string $html): string
    {
        $start = strpos($html, 'data-upcoming');
        $this->assertNotFalse($start);
        $end = strpos($html, '</ul>', $start);

        return substr($html, $start, $end - $start);
    }
}
