<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->boolean('is_islamic')->default(false)->after('reading_time');
            $table->text('hadith_reference')->nullable()->after('is_islamic');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['is_islamic', 'hadith_reference']);
        });
    }
};
