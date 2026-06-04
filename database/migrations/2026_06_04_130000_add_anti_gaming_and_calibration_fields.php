<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_calibration')->default(false)->after('is_external');
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->boolean('is_suspicious')->default(false)->after('status');
            $table->string('suspicious_reason')->nullable()->after('is_suspicious');
        });

        Schema::table('rating_history', function (Blueprint $table) {
            $table->boolean('is_calibration')->default(false)->after('rating_period_id');
            $table->boolean('is_suspicious')->default(false)->after('is_manual_override');
            $table->string('suspicious_reason')->nullable()->after('is_suspicious');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_calibration');
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn(['is_suspicious', 'suspicious_reason']);
        });

        Schema::table('rating_history', function (Blueprint $table) {
            $table->dropColumn(['is_calibration', 'is_suspicious', 'suspicious_reason']);
        });
    }
};
