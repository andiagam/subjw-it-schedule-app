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
        Schema::table('day_tasks', function (Blueprint $table) {
            $table->string('created_by_name')->nullable();
            $table->string('updated_by_name')->nullable();
        });

        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('day_task_id')->nullable()->constrained('day_tasks')->nullOnDelete();
            $table->string('task_title');
            $table->date('task_date');
            $table->string('action');
            $table->string('actor_name');
            $table->string('actor_username');
            $table->timestamps();

            $table->index('task_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_activity_logs');

        Schema::table('day_tasks', function (Blueprint $table) {
            $table->dropColumn(['created_by_name', 'updated_by_name']);
        });
    }
};
