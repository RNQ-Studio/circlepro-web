<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sighter (warm-up) marker on an end. Sighter ends are excluded from
 * score/PB/aggregates. Prepared now ("while the hand is in the ALTER
 * engine"); wired up in Phase 3 (Archer M2 / Sprint 21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_ends', function (Blueprint $table): void {
            $table->boolean('is_sighter')->default(false)->after('end_number');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_ends', function (Blueprint $table): void {
            $table->dropColumn('is_sighter');
        });
    }
};
