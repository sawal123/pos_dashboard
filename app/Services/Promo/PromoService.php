<?php

namespace App\Services\Promo;

use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Promo;
use App\Models\PromoTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dashboard CRUD for server-defined promos (PROMO-D01).
 */
final class PromoService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Business $business, array $data): Promo
    {
        return DB::transaction(function () use ($business, $data): Promo {
            $promo = Promo::create($this->attributes($business, $data));
            $this->syncTargets($promo, $data);
            $this->assertNoConflict($promo);

            return $promo->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promo $promo, array $data): Promo
    {
        return DB::transaction(function () use ($promo, $data): Promo {
            $promo->fill($this->attributes($promo->business ?? Business::findOrFail($promo->business_id), $data));
            $promo->save();
            $this->syncTargets($promo, $data);
            $this->assertNoConflict($promo);

            return $promo->refresh();
        });
    }

    public function updateStatus(Promo $promo, string $status): void
    {
        $promo->status = $status;
        $promo->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Business $business, array $data): array
    {
        return [
            'business_id' => $business->id,
            'name' => trim((string) ($data['name'] ?? '')),
            'code' => $this->nullableString($data['code'] ?? null),
            'discount_type' => (string) $data['discount_type'],
            'discount_value' => (int) ($data['discount_value'] ?? 0),
            'scope' => (string) ($data['scope'] ?? PromoScope::All->value),
            'min_purchase' => (int) ($data['min_purchase'] ?? 0),
            'max_discount' => isset($data['max_discount']) && $data['max_discount'] !== '' ? (int) $data['max_discount'] : null,
            'weekdays' => $this->weekdays($data['weekdays'] ?? null),
            'starts_at' => $this->date($data['starts_at'] ?? null),
            'ends_at' => $this->date($data['ends_at'] ?? null),
            'start_time' => $this->nullableString($data['start_time'] ?? null),
            'end_time' => $this->nullableString($data['end_time'] ?? null),
            'usage_limit_total' => isset($data['usage_limit_total']) && $data['usage_limit_total'] !== '' ? (int) $data['usage_limit_total'] : null,
            'usage_limit_per_customer' => isset($data['usage_limit_per_customer']) && $data['usage_limit_per_customer'] !== '' ? (int) $data['usage_limit_per_customer'] : null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_stackable' => (bool) ($data['is_stackable'] ?? false),
            'status' => (string) ($data['status'] ?? PromoStatus::Draft->value),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTargets(Promo $promo, array $data): void
    {
        $promo->targets()->delete();

        if ((string) $promo->scope === PromoScope::All->value) {
            return;
        }

        $targets = $data['targets'] ?? [];
        if (! is_array($targets)) {
            return;
        }

        foreach ($targets as $target) {
            if (! is_array($target) || ! isset($target['id'])) {
                continue;
            }

            PromoTarget::create([
                'business_id' => $promo->business_id,
                'promo_id' => $promo->id,
                'target_type' => (string) ($target['type'] ?? PromoScope::Product->value),
                'target_id' => (int) $target['id'],
            ]);
        }
    }

    private function assertNoConflict(Promo $promo): void
    {
        if ((string) $promo->status !== PromoStatus::Active->value) {
            return;
        }

        $query = Promo::query()
            ->where('business_id', $promo->business_id)
            ->where('status', PromoStatus::Active->value)
            ->whereKeyNot($promo->id);

        if ($promo->starts_at !== null) {
            $query->where(function ($sub) use ($promo): void {
                $sub->whereNull('ends_at')->orWhere('ends_at', '>=', $promo->starts_at);
            });
        }

        if ($promo->ends_at !== null) {
            $query->where(function ($sub) use ($promo): void {
                $sub->whereNull('starts_at')->orWhere('starts_at', '<=', $promo->ends_at);
            });
        }

        $candidates = $query->get();

        foreach ($candidates as $candidate) {
            if ($this->sharesTargets($promo, $candidate)) {
                throw ValidationException::withMessages([
                    'name' => 'Sudah ada promo aktif dengan target dan periode yang sama.',
                ]);
            }
        }
    }

    private function sharesTargets(Promo $first, Promo $second): bool
    {
        if ((string) $first->scope === PromoScope::All->value || (string) $second->scope === PromoScope::All->value) {
            return true;
        }

        if ((string) $first->scope !== (string) $second->scope) {
            return false;
        }

        $firstIds = $first->targets()->pluck('target_id')->map(static fn ($id): int => (int) $id)->all();
        $secondIds = $second->targets()->pluck('target_id')->map(static fn ($id): int => (int) $id)->all();

        return array_intersect($firstIds, $secondIds) !== [];
    }

    /**
     * @return array<int, int>|null
     */
    private function weekdays(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_map(static fn (mixed $day): int => (int) $day, $value));
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
