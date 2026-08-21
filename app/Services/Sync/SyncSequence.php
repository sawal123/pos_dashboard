<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\SyncCounter;
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
                $counter = SyncCounter::create([
                    'business_id' => $businessId,
                    'current_sequence' => 0,
                ]);

                /** @var SyncCounter $counter */
                $counter = SyncCounter::where('id', $counter->id)->lockForUpdate()->firstOrFail();
            }

            $counter->increment('current_sequence');

            return (int) $counter->fresh()->current_sequence;
        });
    }
}
