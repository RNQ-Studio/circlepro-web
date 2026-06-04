<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_faces', function (Blueprint $table): void {
            $table->unsignedInteger('used_count')->default(0)->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('target_faces', function (Blueprint $table): void {
            $table->dropColumn('used_count');
        });
    }
};
