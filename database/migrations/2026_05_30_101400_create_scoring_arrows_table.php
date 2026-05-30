<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_arrows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('scoring_end_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('arrow_index'); // order within end (0-based)
            $table->smallInteger('score_value'); // 0-10; M = 0 + is_miss
            $table->boolean('is_x')->default(false);
            $table->boolean('is_miss')->default(false);
            $table->decimal('pos_x', 6, 3)->nullable();
            $table->decimal('pos_y', 6, 3)->nullable();
            $table->timestamps();

            $table->unique(['scoring_end_id', 'arrow_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_arrows');
    }
};
