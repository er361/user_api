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
        // Add constraint to prevent unrealistic balance values
        // Max: 999,999,999.99 (999 million)
        DB::statement('ALTER TABLE users ADD CONSTRAINT check_balance_max CHECK (balance <= 999999999.99)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT check_balance_max');
    }
};
