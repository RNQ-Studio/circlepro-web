<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            // Owner polymorphic: satu asset dimiliki satu entitas (User, Transaction, dll).
            // Nullable agar asset bisa berdiri sendiri (mis. file sementara sebelum di-attach).
            // nullableMorphs membuat morphable_type (string) + morphable_id (bigint) + index gabungan.
            $table->nullableMorphs('morphable');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropMorphs('morphable');
        });
    }
};
