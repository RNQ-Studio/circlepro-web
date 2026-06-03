<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_attendances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('club_schedule_id')
                ->constrained('club_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('status', 30); // AttendanceStatus
            $table->string('remark', 255)->nullable();
            $table->foreignId('marked_by')
                ->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_schedule_id', 'user_id']);
            $table->index(['club_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_attendances');
    }
};
