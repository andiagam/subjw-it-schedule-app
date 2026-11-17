<?php

use App\Support\RoleMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        RoleMigrator::apply();
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'IT Manager')->update(['role' => 'MANAGER']);
        DB::table('users')->where('role', 'IT Staff')->update(['role' => 'STAFF']);
        DB::table('users')->where('role', 'IT Trainee')->update(['role' => 'STAFF']);
    }
};
