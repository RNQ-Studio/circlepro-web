<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('asset_id')->nullable();
            $table->foreign('asset_id')->references('id')->on('assets')->nullOnDelete();
            $table->string('media_type', 20); // image|video
            $table->text('media_url');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['expires_at', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
