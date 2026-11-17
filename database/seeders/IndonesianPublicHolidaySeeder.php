<?php

namespace Database\Seeders;

use App\Services\RosterService;
use Illuminate\Database\Seeder;

class IndonesianPublicHolidaySeeder extends Seeder
{
    public function run(RosterService $roster): void
    {
        $roster->seedIndonesianPublicHolidays();
    }
}
