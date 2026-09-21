<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardProductsData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductsController extends Controller
{
    public function __construct(
        protected DashboardProductsData $productsData
    ) {}

    public function index(Request $request): View
    {
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'tab',
            'q',
            'category_id',
            'status',
            'stock_status',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'tab' => ['nullable', 'string', Rule::in(['products', 'services', 'categories'])],
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'stock_status' => ['nullable', 'string', Rule::in(['safe', 'low', 'empty', 'negative'])],
            'page' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->productsData->get($currentBusiness, $filters);

        return view('products.index', $data);
    }
}
