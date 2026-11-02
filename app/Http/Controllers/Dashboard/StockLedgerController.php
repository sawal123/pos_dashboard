<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StockAdjustmentRequest;
use App\Http\Requests\Dashboard\StockReceiptRequest;
use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardStockHistoryData;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Stock ledger history and manual stock mutations (INV-D01).
 *
 * Reading uses `stock.view`; every mutation requires `stock.manage`. The active
 * business always comes from the shared dashboard context.
 */
class StockLedgerController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        private readonly DashboardStockHistoryData $historyData,
        private readonly StockLedgerService $ledger,
    ) {}

    public function history(Request $request): View
    {
        /** @var Business|null $business */
        $business = $request->attributes->get('dashboard_business');

        $data = $this->historyData->get($business, $this->filters($request));

        $data['canManageStock'] = $business !== null
            && app(BusinessAuthorizer::class)->allows(
                $request->user(),
                $business,
                BusinessPermission::STOCK_MANAGE,
            );

        return view('stock.history', $data);
    }

    public function historyData(Request $request): JsonResponse
    {
        /** @var Business|null $business */
        $business = $request->attributes->get('dashboard_business');

        $data = $this->historyData->get($business, $this->filters($request));

        return response()->json([
            'items' => $data['items']->items(),
            'summary' => $data['summary'],
            'has_more' => $data['items']->hasMorePages(),
        ]);
    }

    public function storeAdjustment(StockAdjustmentRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $validated = $request->validated();

        /** @var Product $product */
        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($validated['product_id'])
            ->firstOrFail();

        /** @var User $user */
        $user = $request->user();

        $quantity = (float) $validated['quantity'];
        $delta = $validated['direction'] === 'in' ? $quantity : -$quantity;

        $this->ledger->record(
            $business,
            $product,
            StockMovementType::from((string) $validated['type']),
            $delta,
            [
                'note' => $validated['note'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
            ],
            $user,
        );

        return redirect()
            ->route('stock.history')
            ->with('status', 'Penyesuaian stok berhasil dicatat.');
    }

    public function storeReceipt(StockReceiptRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        /** @var list<array{product_id: int|string, quantity: int|float|string, unit_cost?: int|float|string|null}> $lines */
        $lines = $validated['lines'];

        $movements = $this->ledger->receive(
            $business,
            $user,
            $lines,
            [
                'reference_id' => $validated['reference_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
            ],
        );

        return redirect()
            ->route('stock.history')
            ->with('status', 'Penerimaan stok berhasil dicatat ('.count($movements).' baris).');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $filters = $request->only([
            'q',
            'product_id',
            'type',
            'user_id',
            'from',
            'to',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'product_id' => ['nullable', 'integer'],
            'type' => ['nullable'],
            'type.*' => ['string'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        return $filters;
    }
}
