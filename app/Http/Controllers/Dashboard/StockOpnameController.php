<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StockOpnameCountsRequest;
use App\Http\Requests\Dashboard\StockOpnameRequest;
use App\Models\Business;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardStockOpnameData;
use App\Services\Inventory\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock opname sessions (INV-D02).
 *
 * Reading uses `stock.view`; opening, counting, posting and cancelling require
 * `stock.manage`. Every opname is scoped to the active dashboard business.
 */
class StockOpnameController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        private readonly DashboardStockOpnameData $opnameData,
        private readonly StockOpnameService $opnames,
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $business */
        $business = $request->attributes->get('dashboard_business');

        $data = $this->opnameData->get($business);
        $data['canManageStock'] = $business !== null
            && app(BusinessAuthorizer::class)->allows(
                $request->user(),
                $business,
                BusinessPermission::STOCK_MANAGE,
            );

        return view('stock.opname.index', $data);
    }

    public function store(StockOpnameRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        /** @var User $user */
        $user = $request->user();

        $opname = $this->opnames->open($business, $user, $request->validated());

        return redirect()
            ->route('stock.opname.show', ['opnameId' => $opname->id])
            ->with('status', 'Stock opname '.$opname->code.' dibuka. Isi jumlah fisik lalu posting.');
    }

    public function show(Request $request, int $opnameId): View
    {
        $business = $this->activeBusiness($request);
        $opname = $this->opnameData->find($business, $opnameId);

        if ($opname === null) {
            abort(404);
        }

        $canManage = app(BusinessAuthorizer::class)->allows(
            $request->user(),
            $business,
            BusinessPermission::STOCK_MANAGE,
        );

        return view('stock.opname.show', [
            'opname' => $opname,
            'items' => $opname->items->map(fn ($item): array => $this->opnameData->presentItem($item))->values()->all(),
            'canManageStock' => $canManage,
        ]);
    }

    public function updateCounts(StockOpnameCountsRequest $request, int $opnameId): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $opname = $this->findOpname($business, $opnameId);

        /** @var list<array{id: int|string, counted_stock?: int|float|string|null}> $lines */
        $lines = $request->validated()['items'];

        $this->opnames->saveCounts($opname, $lines);

        return redirect()
            ->route('stock.opname.show', ['opnameId' => $opname->id])
            ->with('status', 'Hitungan opname disimpan.');
    }

    public function post(Request $request, int $opnameId): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $opname = $this->findOpname($business, $opnameId);

        /** @var User $user */
        $user = $request->user();

        $movements = $this->opnames->post($opname, $user);

        return redirect()
            ->route('stock.opname.show', ['opnameId' => $opname->id])
            ->with('status', 'Stock opname diposting. '.$movements.' penyesuaian stok dibuat.');
    }

    public function cancel(Request $request, int $opnameId): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $opname = $this->findOpname($business, $opnameId);

        /** @var User $user */
        $user = $request->user();

        $this->opnames->cancel($opname, $user);

        return redirect()
            ->route('stock.opname.index')
            ->with('status', 'Stock opname dibatalkan.');
    }

    private function findOpname(Business $business, int $opnameId): StockOpname
    {
        /** @var StockOpname $opname */
        $opname = StockOpname::query()
            ->where('business_id', $business->id)
            ->whereKey($opnameId)
            ->firstOrFail();

        return $opname;
    }
}
