<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PREM-D03 — private Cloud backup snapshots.
 *
 * Each row is an immutable snapshot of the mobile app's local backup payload.
 * The server never interprets the snapshot; it stores it verbatim on a private
 * disk after size + SHA-256 verification. `storage_path` is internal only and
 * must never be exposed to clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('device_id');
            $table->string('device_identifier');
            $table->unsignedInteger('schema_version');
            $table->string('app_version')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('status')->default('ready');
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'status']);

            // Idempotency scope: one snapshot per (business, device, key). A null
            // key is never deduplicated (SQL treats NULLs as distinct).
            $table->unique(['business_id', 'device_id', 'idempotency_key']);

            // Tenant-safe device reference: a device can only belong to the same
            // business as the backup.
            $table->foreign(['business_id', 'device_id'])
                ->references(['business_id', 'id'])
                ->on('devices')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_backups');
    }
};
