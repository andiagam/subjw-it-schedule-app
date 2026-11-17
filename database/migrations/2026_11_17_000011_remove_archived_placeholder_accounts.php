<?php

use App\Support\RemovePlaceholderAccounts;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RemovePlaceholderAccounts::class)();
    }

    public function down(): void
    {
        // The placeholder accounts and their roster rows are not restored.
    }
};
