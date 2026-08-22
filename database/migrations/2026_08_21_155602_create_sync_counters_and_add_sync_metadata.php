<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The syncable tables that require sync metadata.
     *
     * @var list<string>
     */
    protected array $syncableTables = [
        'categories',
        'products',
        'customers',
        'shifts',
        'sales',
        'sale_items',
        'expenses',
    ];

    public function up(): void
    {
        Schema::create('sync_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('current_sequence')->default(0);
            $table->timestamps();
        });

        foreach ($this->syncableTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('sync_id')->nullable();
                $table->unsignedBigInteger('sync_version')->default(1);
                $table->unsignedBigInteger('sync_sequence')->default(0);

                $table->unique(['business_id', 'sync_id']);
                $table->index(['business_id', 'sync_sequence']);
            });
        }

        // Backfill existing businesses and records with sync_id, sync_version, and monotonic sequence
        $businesses = DB::table('businesses')->select('id')->get();

        foreach ($businesses as $business) {
            $currentSequence = 0;

            foreach ($this->syncableTables as $tableName) {
                DB::table($tableName)
                    ->where('business_id', $business->id)
                    ->orderBy('id', 'asc')
                    ->chunkById(100, function ($records) use ($tableName, &$currentSequence) {
                        foreach ($records as $record) {
                            $currentSequence++;
                            DB::table($tableName)
                                ->where('id', $record->id)
                                ->update([
                                    'sync_id' => $record->sync_id ?? (string) Str::uuid(),
                                    'sync_version' => 1,
                                    'sync_sequence' => $currentSequence,
                                ]);
                        }
                    });
            }

            DB::table('sync_counters')->updateOrInsert(
                ['business_id' => $business->id],
                [
                    'current_sequence' => $currentSequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        foreach ($this->syncableTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique($tableName.'_business_id_sync_id_unique');
                $table->dropIndex($tableName.'_business_id_sync_sequence_index');
                $table->dropColumn(['sync_id', 'sync_version', 'sync_sequence']);
            });
        }

        Schema::dropIfExists('sync_counters');
    }
};
