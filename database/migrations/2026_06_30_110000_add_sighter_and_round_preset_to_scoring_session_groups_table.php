<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_session_groups', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sighter_end_count')->default(0)->after('arrows_per_end');
            $table->string('round_preset_key', 64)->nullable()->after('sighter_end_count');
            $table->string('round_preset_label', 80)->nullable()->after('round_preset_key');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_session_groups', function (Blueprint $table): void {
            $table->dropColumn(['sighter_end_count', 'round_preset_key', 'round_preset_label']);
        });
    }
};
