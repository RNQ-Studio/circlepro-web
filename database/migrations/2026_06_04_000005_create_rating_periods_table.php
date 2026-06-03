<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('period_month'); // Date of the 1st of the month
            $table->string('status', 20)->default('open'); // open, computing, closed
            $table->timestamp('decay_applied_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_periods');
    }
};
