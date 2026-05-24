<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            // UUID sebagai PK → nama file di GCS = id (tidak bisa ditebak, tidak bocorkan urutan upload)
            $table->uuid('id')->primary();

            // Creator: nullOnDelete agar file tetap ada walau uploader dihapus (penting untuk file legal)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Storage & identitas
            $table->enum('storage_type', ['local', 'gcs'])->default('local');
            $table->text('path');                       // path relatif di dalam disk
            $table->text('url')->nullable();             // cached public URL (terutama GCS atau CDN)
            $table->string('original_filename');
            $table->string('extension', 20)->nullable();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->char('checksum', 64)->nullable();        // sha256 hex untuk dedup & verifikasi integritas
            $table->string('category', 100)->nullable();     // label bisnis: bukti_transfer, foto_profil, dll
            $table->jsonb('metadata')->nullable();            // dimensi/durasi/halaman/EXIF/dll — bervariasi per tipe

            // Retention policy
            // NULL = permanen, tidak boleh ikut terhapus otomatis
            $table->timestampTz('retain_until')->nullable();
            $table->boolean('is_protected')->default(false); // legal/compliance hold — abaikan scheduler

            // Lifecycle status: active → soft_deleted → hard_deleted
            $table->enum('status', ['active', 'soft_deleted', 'hard_deleted'])->default('active');
            $table->timestampTz('soft_deleted_at')->nullable();          // saat scheduler menandai expired
            $table->timestampTz('scheduled_hard_delete_at')->nullable(); // kapan hard delete dijadwalkan
            $table->timestampTz('hard_deleted_at')->nullable();          // saat file benar-benar dihapus dari storage

            // Timestamps standar + Laravel SoftDeletes (deleted_at ≠ soft_deleted_at, lihat catatan model)
            $table->timestampsTz();
            $table->softDeletesTz();

            // Index kolom tunggal
            $table->index('status');
            $table->index('user_id');
            $table->index('storage_type');
        });

        // Partial index: ramping, hanya mengindeks baris yang relevan untuk query scheduler.

        // Scope expired: status='active' AND retain_until < now() AND retain_until IS NOT NULL
        DB::statement(
            "CREATE INDEX assets_expired_idx ON assets (status, retain_until)
             WHERE status = 'active' AND retain_until IS NOT NULL"
        );

        // Scope pending hard delete: status='soft_deleted' AND scheduled_hard_delete_at <= now()
        DB::statement(
            "CREATE INDEX assets_pending_hard_delete_idx ON assets (status, scheduled_hard_delete_at)
             WHERE status = 'soft_deleted'"
        );

        // GIN index jsonb_path_ops: lebih kecil & cepat dari json_ops untuk operator containment (@>)
        DB::statement(
            'CREATE INDEX assets_metadata_gin_idx ON assets USING GIN (metadata jsonb_path_ops)'
        );

        // Partial index untuk lookup dedup berdasarkan checksum
        DB::statement(
            'CREATE INDEX assets_checksum_idx ON assets (checksum) WHERE checksum IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
