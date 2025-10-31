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
        Schema::create('transfer_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('confirmation_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->boolean('confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamps();

            // Optimized index for token lookup by user
            $table->index(['user_id', 'confirmation_token']);
            $table->index(['user_id', 'confirmed']);
            $table->index('expires_at'); // For cleanup job
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_confirmations');
    }
};
