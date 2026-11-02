<?php

namespace App\Services\Pos;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Shift;
use Illuminate\Support\Carbon;

class PosNumberGenerator
{
    /**
     * Generate unique transaction number for POS Web.
     * Format: WEB-{OUTLET_CODE}-{YYYYMMDD}-{SEQ_4DIGIT}
     */
    public function generateTransactionNumber(Business $business, Outlet $outlet, ?Carbon $date = null): string
    {
        $date = $date ?? Carbon::now();
        $dateStr = $date->format('Ymd');
        $cleanOutletCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $outlet->code)) ?: 'OUT';
        $prefix = "WEB-{$cleanOutletCode}-{$dateStr}-";

        $lastNumber = Sale::query()
            ->where('business_id', $business->id)
            ->where('transaction_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('transaction_number');

        $nextSeq = 1;
        if ($lastNumber && preg_match('/-(\d+)$/', (string) $lastNumber, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = Sale::query()
                ->where('business_id', $business->id)
                ->where('transaction_number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }

    /**
     * Generate unique shift number for POS Web.
     * Format: SHIFT-{OUTLET_CODE}-{YYYYMMDD}-{SEQ_2DIGIT}
     */
    public function generateShiftNumber(Business $business, Outlet $outlet, ?Carbon $date = null): string
    {
        $date = $date ?? Carbon::now();
        $dateStr = $date->format('Ymd');
        $cleanOutletCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $outlet->code)) ?: 'OUT';
        $prefix = "SHIFT-{$cleanOutletCode}-{$dateStr}-";

        $lastNumber = Shift::query()
            ->where('business_id', $business->id)
            ->where('shift_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('shift_number');

        $nextSeq = 1;
        if ($lastNumber && preg_match('/-(\d+)$/', (string) $lastNumber, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 2, '0', STR_PAD_LEFT);
            $exists = Shift::query()
                ->where('business_id', $business->id)
                ->where('shift_number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }
}
