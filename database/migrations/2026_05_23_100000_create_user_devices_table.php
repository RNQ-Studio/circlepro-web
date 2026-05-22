<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->enum('platform', ['android', 'ios', 'web']);
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('device_name')->nullable();
            $table->string('push_token')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index('push_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
