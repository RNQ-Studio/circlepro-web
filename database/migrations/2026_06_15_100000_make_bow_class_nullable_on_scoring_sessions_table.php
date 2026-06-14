<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 02 — Lifecycle Grup & Quick-Add Tamu.
 *
 * Quick-add lets a host register a guest with just a name (K8: "jangan blokir
 * penambahan orang demi metadata"). bow_class is genuinely unknown for such a
 * guest, so the column must allow NULL — matching the ScoringSession model
 * docblock which already declares it `BowClass|null`. Solo sessions keep
 * sending a bow_class (StoreScoringSessionRequest requires it), so existing
 * stats/PB flows are unaffected; only owner-less guest rows store NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->string('bow_class', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->string('bow_class', 30)->nullable(false)->change();
        });
    }
};
