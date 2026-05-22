<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table): void {
            $table->id();
            $table->enum('platform', ['android', 'ios']);
            $table->string('min_version', 20);
            $table->string('latest_version', 20);
            $table->boolean('force_update')->default(false);
            $table->string('store_url')->nullable();
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->unique('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
