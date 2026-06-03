<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->unsignedInteger('target_butt')->nullable()->after('bib_number');
            $table->char('target_letter', 1)->nullable()->after('target_butt');

            // Unique index for event division + target butt + target letter to prevent duplicate assignments
            $table->unique(['event_division_id', 'target_butt', 'target_letter'], 'division_target_letter_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropUnique('division_target_letter_unique');
            $table->dropColumn(['target_butt', 'target_letter']);
        });
    }
};
