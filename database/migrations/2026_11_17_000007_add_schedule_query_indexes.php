<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active', 'users_is_active_index');
        });

        Schema::table('day_tasks', function (Blueprint $table) {
            $table->index(['kind', 'date'], 'day_tasks_kind_date_index');
        });

        Schema::table('roster_entries', function (Blueprint $table) {
            $table->index('date', 'roster_entries_date_index');
            $table->index('status', 'roster_entries_status_index');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index('start_date', 'leave_requests_start_date_index');
            $table->index('end_date', 'leave_requests_end_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_active_index');
        });

        Schema::table('day_tasks', function (Blueprint $table) {
            $table->dropIndex('day_tasks_kind_date_index');
        });

        Schema::table('roster_entries', function (Blueprint $table) {
            $table->dropIndex('roster_entries_date_index');
            $table->dropIndex('roster_entries_status_index');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('leave_requests_start_date_index');
            $table->dropIndex('leave_requests_end_date_index');
        });
    }
};
