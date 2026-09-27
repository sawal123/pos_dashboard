<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CategoryRequest;
use App\Http\Requests\Dashboard\ProductRequest;
use App\Http\Requests\Dashboard\UpdateCatalogStatusRequest;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\CatalogManagementService;
use App\Services\Dashboard\DashboardProductsData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductsController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        protected DashboardProductsData $productsData,
        protected CatalogManagementService $catalog,
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
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

        $data['canManageCatalog'] = app(BusinessAuthorizer::class)
            ->allows($request->user(), $currentBusiness, BusinessPermission::PRODUCTS_MANAGE);

        return view('products.index', $data);
    }

    // ============================================================
    // DASH-15 — owner-only catalog mutations
    // ============================================================

    public function store(ProductRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $kind = (string) $request->validated('kind');

        $this->catalog->createProduct($business, $request->validated(), $kind);

        return redirect()
            ->route('products.index', ['tab' => $kind === 'service' ? 'services' : 'products'])
            ->with('status', $kind === 'service' ? 'Layanan berhasil ditambahkan.' : 'Produk berhasil ditambahkan.');
    }

    public function update(ProductRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('productId'))
            ->firstOrFail();

        $this->catalog->updateProduct($product, $request->validated());

        return redirect()
            ->route('products.index', ['tab' => $product->kind === 'service' ? 'services' : 'products'])
            ->with('status', $product->kind === 'service' ? 'Layanan berhasil diperbarui.' : 'Produk berhasil diperbarui.');
    }

    public function updateStatus(UpdateCatalogStatusRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('productId'))
            ->firstOrFail();

        $this->catalog->updateStatus($product, (string) $request->validated('status'));

        return redirect()
            ->route('products.index', ['tab' => $product->kind === 'service' ? 'services' : 'products'])
            ->with('status', 'Status produk diperbarui.');
    }

    public function storeCategory(CategoryRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $this->catalog->createCategory($business, $request->validated());

        return redirect()
            ->route('products.index', ['tab' => 'categories'])
            ->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function updateCategory(CategoryRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $category = Category::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('categoryId'))
            ->firstOrFail();

        $this->catalog->updateCategory($category, $request->validated());

        return redirect()
            ->route('products.index', ['tab' => 'categories'])
            ->with('status', 'Kategori berhasil diperbarui.');
    }

    public function updateCategoryStatus(UpdateCatalogStatusRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $category = Category::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('categoryId'))
            ->firstOrFail();

        $this->catalog->updateStatus($category, (string) $request->validated('status'));

        return redirect()
            ->route('products.index', ['tab' => 'categories'])
            ->with('status', 'Status kategori diperbarui.');
    }
}
