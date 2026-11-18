<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-OUTLET — branch foundation.
 *
 * Every business gets exactly one primary outlet (the oldest one). The primary
 * outlet is covered by the business-level (legacy) subscription row; every
 * additional branch carries its own paid subscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('code');
            $table->string('phone', 40)->nullable()->after('address');
            $table->unsignedInteger('sort_order')->default(0)->after('phone');
            $table->index(['business_id', 'is_primary']);
        });

        $primaryIds = DB::table('outlets')
            ->selectRaw('MIN(id) as id')
            ->groupBy('business_id')
            ->pluck('id');

        foreach ($primaryIds->chunk(500) as $chunk) {
            DB::table('outlets')->whereIn('id', $chunk->all())->update(['is_primary' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'is_primary']);
            $table->dropColumn(['is_primary', 'phone', 'sort_order']);
        });
    }
};
