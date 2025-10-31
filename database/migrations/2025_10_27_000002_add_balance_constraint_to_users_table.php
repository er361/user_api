<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT check_balance_non_negative CHECK (balance >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT check_balance_non_negative');
    }
};
