<?php

namespace App\Models\Concerns;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;

/**
 * MULTI-OUTLET stock foundation: rows that do not state an outlet explicitly
 * are attributed to the business' primary outlet, so stock history is always
 * outlet-attributed and can later be split per branch.
 */
trait DefaultsToPrimaryOutlet
{
    public static function bootDefaultsToPrimaryOutlet(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('outlet_id') !== null || empty($model->getAttribute('business_id'))) {
                return;
            }

            $outletId = Outlet::query()
                ->where('business_id', (int) $model->getAttribute('business_id'))
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->value('id');

            if ($outletId !== null) {
                $model->setAttribute('outlet_id', (int) $outletId);
            }
        });
    }
}
