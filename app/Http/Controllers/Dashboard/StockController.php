<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Dashboard\DashboardStockData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        protected DashboardStockData $stockData
    ) {}

    public function index(Request $request): View
    {
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'q',
            'category_id',
            'stock_status',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'stock_status' => ['nullable', 'string', Rule::in(['safe', 'low', 'empty', 'negative'])],
            'page' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->stockData->get($currentBusiness, $filters);

        return view('stock.index', $data);
    }

    public function movements(Request $request, int $productId): JsonResponse
    {
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(404);
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->where('business_id', $currentBusiness->id)
            ->where('id', $productId)
            ->where('kind', 'product')
            ->where('status', '!=', 'deleted')
            ->first();

        if ($product === null) {
            abort(404);
        }

        $data = $this->stockData->getProductMovements($currentBusiness, $product);

        return response()->json($data);
    }
}
