<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_division_id')
                ->constrained('event_divisions')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('confirmed'); // Default directly confirmed as we bypass payment
            $table->string('payment_id', 26)->nullable();
            $table->string('bib_number', 20)->nullable();
            $table->string('qr_code', 120)->nullable()->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            // Prevent duplicate registration for the same user in the same division
            $table->unique(['user_id', 'event_division_id'], 'user_division_unique');
            
            $table->index('status');
            $table->index('qr_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
