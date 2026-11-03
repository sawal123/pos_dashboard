<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\VariantGroupRequest;
use App\Models\VariantGroup;
use App\Services\Dashboard\VariantManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VariantGroupController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        protected VariantManagementService $variantService,
    ) {}

    public function store(VariantGroupRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $group = $this->variantService->createGroup($business, $request->validated());

        return redirect()
            ->route('products.index', ['tab' => 'variants'])
            ->with('status', 'Template varian "'.$group->name.'" berhasil ditambahkan.');
    }

    public function update(VariantGroupRequest $request, int $groupId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $group = VariantGroup::query()
            ->where('business_id', $business->id)
            ->whereKey($groupId)
            ->firstOrFail();

        $this->variantService->updateGroup($group, $request->validated());

        return redirect()
            ->route('products.index', ['tab' => 'variants'])
            ->with('status', 'Template varian "'.$group->name.'" berhasil diperbarui.');
    }

    public function destroy(Request $request, int $groupId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $group = VariantGroup::query()
            ->where('business_id', $business->id)
            ->whereKey($groupId)
            ->firstOrFail();

        $name = $group->name;
        $this->variantService->deleteGroup($group);

        return redirect()
            ->route('products.index', ['tab' => 'variants'])
            ->with('status', 'Template varian "'.$name.'" berhasil dihapus.');
    }
}

