<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * MULTI-OUTLET — owner-only branch (outlet) management.
 *
 * A new branch is opened in `pending_payment` status and only transitions to
 * `active` once its branch subscription payment is activated. The primary
 * outlet cannot be deactivated.
 */
final class OutletManagementService
{
    /**
     * @param  array{name: string, code?: string|null, address?: string|null, phone?: string|null}  $data
     */
    public function createBranch(Business $business, array $data): Outlet
    {
        return DB::transaction(function () use ($business, $data): Outlet {
            $name = trim((string) $data['name']);
            $code = $this->resolveUniqueCode($business, $data['code'] ?? null, $name);

            $nextSort = ((int) Outlet::query()->where('business_id', $business->id)->max('sort_order')) + 1;

            return Outlet::query()->create([
                'business_id' => $business->id,
                'name' => $name,
                'code' => $code,
                'is_primary' => false,
                'status' => Outlet::STATUS_PENDING_PAYMENT,
                'address' => $this->nullableText($data['address'] ?? null),
                'phone' => $this->nullableText($data['phone'] ?? null),
                'sort_order' => $nextSort,
            ]);
        });
    }

    /**
     * @param  array{name: string, code?: string|null, address?: string|null, phone?: string|null}  $data
     */
    public function update(Business $business, Outlet $outlet, array $data): Outlet
    {
        if ($outlet->business_id !== $business->id) {
            abort(404);
        }

        $name = trim((string) $data['name']);
        $rawCode = isset($data['code']) && trim((string) $data['code']) !== ''
            ? strtoupper(trim((string) $data['code']))
            : $outlet->code;

        $conflict = Outlet::query()
            ->where('business_id', $business->id)
            ->where('code', $rawCode)
            ->where('id', '!=', $outlet->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'code' => 'Kode cabang sudah digunakan oleh outlet lain pada bisnis ini.',
            ]);
        }

        $outlet->forceFill([
            'name' => $name,
            'code' => $rawCode,
            'address' => $this->nullableText($data['address'] ?? null),
            'phone' => $this->nullableText($data['phone'] ?? null),
        ])->save();

        return $outlet;
    }

    public function updateStatus(Business $business, Outlet $outlet, string $status): Outlet
    {
        if ($outlet->business_id !== $business->id) {
            abort(404);
        }

        if ($outlet->is_primary && $status !== Outlet::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Outlet utama tidak dapat dinonaktifkan.',
            ]);
        }

        if ($status === Outlet::STATUS_ACTIVE && ! $outlet->is_primary) {
            $sub = $outlet->effectiveSubscription();
            if ($sub === null || ! $sub->hasCloudAccess()) {
                throw ValidationException::withMessages([
                    'status' => 'Cabang ini memerlukan Paket Cloud aktif sebelum dapat diaktifkan. Silakan selesaikan pembayaran cabang terlebih dahulu.',
                ]);
            }
        }

        $outlet->forceFill(['status' => $status])->save();

        return $outlet;
    }

    private function resolveUniqueCode(Business $business, ?string $rawCode, string $name): string
    {
        if ($rawCode !== null && trim($rawCode) !== '') {
            $code = strtoupper(trim($rawCode));
            $exists = Outlet::query()
                ->where('business_id', $business->id)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'code' => 'Kode cabang sudah digunakan oleh outlet lain pada bisnis ini.',
                ]);
            }

            return $code;
        }

        $prefix = Str::upper(Str::slug(Str::limit($name, 10, ''), ''));
        if ($prefix === '') {
            $prefix = 'CBG';
        }

        for ($i = 1; $i <= 999; $i++) {
            $candidate = substr($prefix, 0, 6).'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $exists = Outlet::query()
                ->where('business_id', $business->id)
                ->where('code', $candidate)
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        return 'CBG-'.Str::upper(Str::random(5));
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
