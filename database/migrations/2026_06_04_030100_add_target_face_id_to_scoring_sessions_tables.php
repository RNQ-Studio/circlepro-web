<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->foreignUlid('target_face_id')
                ->nullable()
                ->after('target_face_cm')
                ->constrained('target_faces')
                ->nullOnDelete();
        });

        Schema::table('scoring_session_groups', function (Blueprint $table): void {
            $table->foreignUlid('target_face_id')
                ->nullable()
                ->after('target_face_cm')
                ->constrained('target_faces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scoring_session_groups', function (Blueprint $table): void {
            $table->dropForeign(['target_face_id']);
            $table->dropColumn('target_face_id');
        });

        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->dropForeign(['target_face_id']);
            $table->dropColumn('target_face_id');
        });
    }
};
