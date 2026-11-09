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
use App\Models\Unit;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Catalog\ProductCodeService;
use App\Services\Dashboard\CatalogManagementService;
use App\Services\Dashboard\DashboardProductsData;
use App\Services\Inventory\HppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            'tab' => ['nullable', 'string', Rule::in(['products', 'supplies', 'services', 'categories', 'variants'])],
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

        $product = $this->catalog->createProduct($business, $request->validated(), $kind);

        $tab = $kind === 'service' ? 'services' : (! $product->is_sellable ? 'supplies' : 'products');
        $label = $kind === 'service' ? 'Layanan' : (! $product->is_sellable ? 'Bahan baku' : 'Produk');

        return redirect()
            ->route('products.index', ['tab' => $tab])
            ->with('status', $label.' berhasil ditambahkan.');
    }

    public function update(ProductRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('productId'))
            ->firstOrFail();

        $this->catalog->updateProduct($product, $request->validated());

        $tab = $product->kind === 'service' ? 'services' : (! $product->is_sellable ? 'supplies' : 'products');
        $label = $product->kind === 'service' ? 'Layanan' : (! $product->is_sellable ? 'Bahan baku' : 'Produk');

        return redirect()
            ->route('products.index', ['tab' => $tab])
            ->with('status', $label.' berhasil diperbarui.');
    }

    public function applyHpp(Request $request, int $productId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($productId)
            ->firstOrFail();

        app(HppService::class)->applyHppToCost($product, $request->user());

        return back()->with('status', 'HPP terhitung berhasil diterapkan ke harga modal.');
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

    public function storeUnit(Request $request): JsonResponse|RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['nullable', 'string', 'max:20'],
        ]);

        $name = strtolower(trim($validated['name']));

        $unit = Unit::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'name' => $name,
            ],
            [
                'symbol' => $validated['symbol'] ?? null,
                'status' => 'active',
            ]
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'unit' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                ],
                'message' => 'Satuan berhasil ditambahkan.',
            ]);
        }

        return back()->with('status', 'Satuan berhasil ditambahkan.');
    }

    public function generateQr(Request $request, int $productId): JsonResponse|RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($productId)
            ->firstOrFail();

        /** @var ProductCodeService $codeService */
        $codeService = app(ProductCodeService::class);
        $product = $codeService->assignBarcode($product);
        $svg = $codeService->renderQrSvg((string) $product->barcode, 280);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $product->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'price' => (int) $product->price,
                'barcode' => $product->barcode,
                'svg' => $svg,
                'download_url' => route('products.qr-download', ['productId' => $product->id]),
                'message' => 'Kode produk dan QR berhasil dibuat.',
            ]);
        }

        return back()->with('status', 'Kode produk dan QR berhasil dibuat.');
    }

    public function qrPreview(Request $request, int $productId): JsonResponse|Response
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($productId)
            ->firstOrFail();

        if (empty($product->barcode)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk belum memiliki kode barcode/QR.',
                ], 404);
            }
            abort(404, 'Produk belum memiliki barcode.');
        }

        /** @var ProductCodeService $codeService */
        $codeService = app(ProductCodeService::class);
        $svg = $codeService->renderQrSvg((string) $product->barcode, 280);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $product->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'price' => (int) $product->price,
                'barcode' => $product->barcode,
                'svg' => $svg,
                'download_url' => route('products.qr-download', ['productId' => $product->id]),
            ]);
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    public function qrDownload(Request $request, int $productId): Response
    {
        $business = $this->activeBusiness($request);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->whereKey($productId)
            ->firstOrFail();

        if (empty($product->barcode)) {
            abort(404, 'Produk belum memiliki barcode.');
        }

        /** @var ProductCodeService $codeService */
        $codeService = app(ProductCodeService::class);
        $png = $codeService->renderLabelPng($product, 240);

        $filename = 'label-' . Str::slug($product->name ?: 'product') . '-' . Str::slug($product->barcode) . '.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($png),
        ]);
    }
}
