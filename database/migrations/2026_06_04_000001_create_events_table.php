<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->text('banner_url')->nullable();
            $table->string('tier', 10);      // EventTier
            $table->string('format', 20);    // EventFormat
            $table->string('status', 20)->default('draft');  // EventStatus
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('venue_name', 150)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->integer('capacity')->nullable();
            $table->jsonb('schedule')->nullable();
            $table->text('rules')->nullable();
            $table->boolean('is_external')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'starts_at'], 'organization_by_starts_at_index');
            $table->index(['status', 'starts_at']);
            $table->index(['province', 'city']);
            $table->index('tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
