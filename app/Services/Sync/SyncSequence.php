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
}
