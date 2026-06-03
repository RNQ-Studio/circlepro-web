<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('location', 200)->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->foreignId('created_by')
                ->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_schedules');
    }
};
