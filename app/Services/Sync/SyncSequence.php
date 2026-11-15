<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\SyncCounter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SyncSequence
{
    /**
     * Get the next monotonic sync sequence number for the given business.
     */
    public static function next(int|Business $business): int
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return DB::transaction(function () use ($businessId): int {
            /** @var SyncCounter|null $counter */
            $counter = SyncCounter::where('business_id', $businessId)->lockForUpdate()->first();

            if (! $counter) {
                $createException = null;
                try {
                    SyncCounter::firstOrCreate([
                        'business_id' => $businessId,
                    ], [
                        'current_sequence' => 0,
                    ]);
                } catch (QueryException $e) {
                    $createException = $e;
                }

                /** @var SyncCounter|null $counter */
                $counter = SyncCounter::where('business_id', $businessId)->lockForUpdate()->first();

                if (! $counter && $createException) {
                    throw $createException;
                }

                if (! $counter) {
                    $counter = SyncCounter::where('business_id', $businessId)->lockForUpdate()->firstOrFail();
                }
            }

            $counter->increment('current_sequence');

            return (int) $counter->fresh()->current_sequence;
        });
    }

    /**
     * Resequence all catalog and master records for a business that have missing
     * or zero sync_sequence/sync_id. Ensures monotonic sequence and updates SyncCounter.
     */
    public static function resequenceBusiness(int $businessId): int
    {
        return DB::transaction(function () use ($businessId): int {
            /** @var SyncCounter $counter */
            $counter = SyncCounter::firstOrCreate(
                ['business_id' => $businessId],
                ['current_sequence' => 0]
            );

            // Reload with lock
            $counter = SyncCounter::where('business_id', $businessId)->lockForUpdate()->firstOrFail();
            $currentSeq = (int) $counter->current_sequence;

            $tables = [
                'categories',
                'products',
                'variant_groups',
                'variant_options',
                'customers',
                'promos',
            ];

            foreach ($tables as $table) {
                $records = DB::table($table)
                    ->where('business_id', $businessId)
                    ->where(function ($query) {
                        $query->whereNull('sync_sequence')
                            ->orWhere('sync_sequence', '<=', 0);
                    })
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($records as $record) {
                    $currentSeq++;
                    $updates = ['sync_sequence' => $currentSeq];
                    if (empty($record->sync_id)) {
                        $updates['sync_id'] = (string) \Illuminate\Support\Str::uuid();
                    }
                    if (empty($record->sync_version) || $record->sync_version < 1) {
                        $updates['sync_version'] = 1;
                    }
                    DB::table($table)->where('id', $record->id)->update($updates);
                }
            }

            $counter->update(['current_sequence' => $currentSeq]);

            return $currentSeq;
        });
    }
}
