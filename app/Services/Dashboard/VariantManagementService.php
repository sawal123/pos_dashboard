<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\VariantGroup;
use App\Models\VariantOption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VariantManagementService
{
    /**
     * Get all active variant groups for a business with their options and products count.
     *
     * @return Collection<int, VariantGroup>
     */
    public function getGroups(Business $business): Collection
    {
        return VariantGroup::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->with(['options' => fn ($q) => $q->where('status', '!=', 'deleted')->orderBy('sort_order')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new variant group with its options.
     *
     * @param  array<string, mixed>  $data
     */
    public function createGroup(Business $business, array $data): VariantGroup
    {
        return DB::transaction(function () use ($business, $data): VariantGroup {
            $group = VariantGroup::create([
                'business_id' => $business->id,
                'name' => trim((string) $data['name']),
                'selection_type' => in_array($data['selection_type'] ?? 'single', ['single', 'multiple'], true)
                    ? $data['selection_type']
                    : 'single',
                'is_required' => (bool) ($data['is_required'] ?? true),
                'min_selection' => (int) ($data['min_selection'] ?? 1),
                'max_selection' => isset($data['max_selection']) && $data['max_selection'] !== ''
                    ? (int) $data['max_selection']
                    : null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => $data['status'] ?? 'active',
            ]);

            if (! empty($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $idx => $opt) {
                    if (empty(trim((string) ($opt['name'] ?? '')))) {
                        continue;
                    }

                    VariantOption::create([
                        'business_id' => $business->id,
                        'variant_group_id' => $group->id,
                        'name' => trim((string) $opt['name']),
                        'price_adjustment' => (int) ($opt['price_adjustment'] ?? 0),
                        'cost_adjustment' => (int) ($opt['cost_adjustment'] ?? 0),
                        'ingredient_product_id' => ! empty($opt['ingredient_product_id'])
                            ? (int) $opt['ingredient_product_id']
                            : null,
                        'ingredient_quantity' => (float) ($opt['ingredient_quantity'] ?? 0),
                        'is_default' => (bool) ($opt['is_default'] ?? false),
                        'sort_order' => (int) ($opt['sort_order'] ?? $idx + 1),
                        'status' => $opt['status'] ?? 'active',
                    ]);
                }
            }

            return $group->fresh(['options']);
        });
    }

    /**
     * Update an existing variant group and sync its options.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(VariantGroup $group, array $data): VariantGroup
    {
        return DB::transaction(function () use ($group, $data): VariantGroup {
            $group->update([
                'name' => trim((string) $data['name']),
                'selection_type' => in_array($data['selection_type'] ?? 'single', ['single', 'multiple'], true)
                    ? $data['selection_type']
                    : 'single',
                'is_required' => (bool) ($data['is_required'] ?? true),
                'min_selection' => (int) ($data['min_selection'] ?? 1),
                'max_selection' => isset($data['max_selection']) && $data['max_selection'] !== ''
                    ? (int) $data['max_selection']
                    : null,
                'sort_order' => (int) ($data['sort_order'] ?? $group->sort_order),
                'status' => $data['status'] ?? $group->status,
            ]);

            if (isset($data['options']) && is_array($data['options'])) {
                $keptOptionIds = [];

                foreach ($data['options'] as $idx => $opt) {
                    if (empty(trim((string) ($opt['name'] ?? '')))) {
                        continue;
                    }

                    $optionData = [
                        'business_id' => $group->business_id,
                        'variant_group_id' => $group->id,
                        'name' => trim((string) $opt['name']),
                        'price_adjustment' => (int) ($opt['price_adjustment'] ?? 0),
                        'cost_adjustment' => (int) ($opt['cost_adjustment'] ?? 0),
                        'ingredient_product_id' => ! empty($opt['ingredient_product_id'])
                            ? (int) $opt['ingredient_product_id']
                            : null,
                        'ingredient_quantity' => (float) ($opt['ingredient_quantity'] ?? 0),
                        'is_default' => (bool) ($opt['is_default'] ?? false),
                        'sort_order' => (int) ($opt['sort_order'] ?? $idx + 1),
                        'status' => $opt['status'] ?? 'active',
                    ];

                    if (! empty($opt['id'])) {
                        $option = VariantOption::query()
                            ->where('business_id', $group->business_id)
                            ->where('variant_group_id', $group->id)
                            ->whereKey($opt['id'])
                            ->first();

                        if ($option) {
                            $option->update($optionData);
                            $keptOptionIds[] = $option->id;
                            continue;
                        }
                    }

                    $newOption = VariantOption::create($optionData);
                    $keptOptionIds[] = $newOption->id;
                }

                // Soft-delete options not in the kept list for offline sync tombstones
                VariantOption::query()
                    ->where('business_id', $group->business_id)
                    ->where('variant_group_id', $group->id)
                    ->whereNotIn('id', $keptOptionIds)
                    ->update(['status' => 'deleted']);
            }

            return $group->fresh(['options']);
        });
    }

    /**
     * Soft-delete a variant group (sync tombstones travel through status column).
     */
    public function deleteGroup(VariantGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $group->products()->detach();
            $group->options()->update(['status' => 'deleted']);
            $group->status = 'deleted';
            $group->save();
        });
    }
}

