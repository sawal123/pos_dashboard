<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_requests', function (Blueprint $table) {
            $table->index(['business_id', 'processed_at'], 'sync_requests_business_processed_idx');
            $table->index('processed_at', 'sync_requests_processed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sync_requests', function (Blueprint $table) {
            $table->dropIndex('sync_requests_business_processed_idx');
            $table->dropIndex('sync_requests_processed_at_idx');
        });
    }
};
