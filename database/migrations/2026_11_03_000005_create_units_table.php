<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('symbol', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'status']);
        });

        // Seed initial default units for any existing businesses
        $defaultUnits = ['pcs', 'gram', 'cup', 'ml', 'kg', 'liter', 'box', 'botol', 'pouch', 'sachet'];
        $businesses = DB::table('businesses')->pluck('id');
        $now = now();

        foreach ($businesses as $businessId) {
            $existingProductUnits = DB::table('products')
                ->where('business_id', $businessId)
                ->whereNotNull('unit')
                ->where('unit', '!=', '')
                ->distinct()
                ->pluck('unit')
                ->toArray();

            $mergedUnits = array_unique(array_merge($defaultUnits, $existingProductUnits));
            $inserts = [];
            foreach ($mergedUnits as $unitName) {
                $trimmed = strtolower(trim((string) $unitName));
                if ($trimmed !== '') {
                    $inserts[] = [
                        'business_id' => $businessId,
                        'name' => $trimmed,
                        'symbol' => null,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($inserts)) {
                DB::table('units')->insertOrIgnore($inserts);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};

