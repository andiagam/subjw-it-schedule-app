<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_tasks', function (Blueprint $table) {
            $table->text('completion_note')->nullable();
            $table->string('completed_by_name')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('task_activity_logs', function (Blueprint $table) {
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('day_tasks', function (Blueprint $table) {
            $table->dropColumn(['completion_note', 'completed_by_name', 'completed_at']);
        });

        Schema::table('task_activity_logs', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
