<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('day_tasks')->where('kind', 'pending')->update(['kind' => 'task']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pending tasks were folded into tasks and cannot be told apart afterwards.
    }
};
