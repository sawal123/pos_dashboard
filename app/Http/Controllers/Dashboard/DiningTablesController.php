<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DiningTable;
use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiningTablesController extends Controller
{
    use ResolvesActiveBusiness;

    public function index(Request $request): View
    {
        $business = $this->activeBusiness($request);

        $outlets = $business->outlets()
            ->orderBy('is_primary', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $filters = $request->only(['q', 'outlet_id', 'status']);

        $query = DiningTable::query()
            ->where('business_id', $business->id)
            ->with('outlet');

        if (! empty($filters['q'])) {
            $searchTerm = '%' . trim($filters['q']) . '%';
            $query->where('name', 'like', $searchTerm);
        }

        if (isset($filters['outlet_id']) && $filters['outlet_id'] !== '') {
            if ($filters['outlet_id'] === 'global') {
                $query->whereNull('outlet_id');
            } elseif (is_numeric($filters['outlet_id'])) {
                $query->where('outlet_id', (int) $filters['outlet_id']);
            }
        }

        if (! empty($filters['status']) && in_array($filters['status'], ['available', 'occupied', 'reserved'], true)) {
            $query->where('status', $filters['status']);
        }

        $allTables = DiningTable::where('business_id', $business->id)->get();
        $totalTables = $allTables->count();
        $globalTablesCount = $allTables->whereNull('outlet_id')->count();
        $outletTablesCount = $totalTables - $globalTablesCount;

        $tables = $query->with('outlet')
            ->orderBy('outlet_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(30)
            ->withQueryString();

        return view('tables.index', [
            'business' => $business,
            'tables' => $tables,
            'outlets' => $outlets,
            'filters' => $filters,
            'summary' => [
                'total_tables' => $totalTables,
                'global_tables' => $globalTablesCount,
                'outlet_tables' => $outletTablesCount,
                'is_enabled' => (bool) $business->table_management_enabled,
            ],
            'tableManagementEnabled' => (bool) $business->table_management_enabled,
        ]);
    }

    public function updateToggle(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'table_management_enabled' => ['required', 'boolean'],
        ]);

        $business->table_management_enabled = (bool) $validated['table_management_enabled'];
        $business->save();

        $statusText = $business->table_management_enabled
            ? 'Fitur nomor meja berhasil diaktifkan untuk kasir (POS).'
            : 'Fitur nomor meja dinonaktifkan dari kasir (POS).';

        return redirect()
            ->route('tables.index')
            ->with('status', $statusText);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('business_id', $business->id)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:available,occupied,reserved'],
        ], [
            'name.required' => 'Nama / nomor meja harus diisi.',
            'name.max' => 'Nama meja maksimal 100 karakter.',
            'outlet_id.exists' => 'Cabang yang dipilih tidak valid untuk bisnis ini.',
        ]);

        // Ensure outlet belongs to this business if provided
        $outletId = null;
        if (! empty($validated['outlet_id'])) {
            $outlet = Outlet::where('business_id', $business->id)->where('id', $validated['outlet_id'])->first();
            if ($outlet !== null) {
                $outletId = $outlet->id;
            }
        }

        DiningTable::create([
            'business_id' => $business->id,
            'outlet_id' => $outletId,
            'name' => trim($validated['name']),
            'capacity' => ! empty($validated['capacity']) ? (int) $validated['capacity'] : 4,
            'status' => ! empty($validated['status']) ? $validated['status'] : 'available',
        ]);

        return redirect()
            ->route('tables.index')
            ->with('status', 'Nomor meja "'.trim($validated['name']).'" berhasil ditambahkan.');
    }

    public function storeBulk(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'prefix' => ['required', 'string', 'max:50'],
            'start_number' => ['required', 'integer', 'min:1', 'max:999'],
            'end_number' => ['required', 'integer', 'min:1', 'max:999', 'gte:start_number'],
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('business_id', $business->id)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pad_zeros' => ['nullable', 'boolean'],
        ], [
            'prefix.required' => 'Awalan nama meja (prefix) harus diisi, misal "Meja ".',
            'start_number.required' => 'Nomor awal harus diisi.',
            'end_number.required' => 'Nomor akhir harus diisi.',
            'end_number.gte' => 'Nomor akhir tidak boleh lebih kecil dari nomor awal.',
            'outlet_id.exists' => 'Cabang yang dipilih tidak valid untuk bisnis ini.',
        ]);

        $start = (int) $validated['start_number'];
        $end = (int) $validated['end_number'];
        $diff = $end - $start + 1;

        if ($diff > 50) {
            return redirect()
                ->route('tables.index')
                ->withErrors(['end_number' => 'Maksimal 50 meja yang dapat dibuat sekaligus dalam satu waktu.']);
        }

        $outletId = null;
        if (! empty($validated['outlet_id'])) {
            $outlet = Outlet::where('business_id', $business->id)->where('id', $validated['outlet_id'])->first();
            if ($outlet !== null) {
                $outletId = $outlet->id;
            }
        }

        $prefix = trim($validated['prefix']);
        $padZeros = ! empty($validated['pad_zeros']);
        $capacity = ! empty($validated['capacity']) ? (int) $validated['capacity'] : 4;

        DB::transaction(function () use ($business, $outletId, $prefix, $start, $end, $padZeros, $capacity) {
            for ($i = $start; $i <= $end; $i++) {
                $numStr = $padZeros ? sprintf('%02d', $i) : (string) $i;
                $tableName = $prefix . ' ' . $numStr;

                DiningTable::create([
                    'business_id' => $business->id,
                    'outlet_id' => $outletId,
                    'name' => trim($tableName),
                    'capacity' => $capacity,
                    'status' => 'available',
                    'sort_order' => $i,
                ]);
            }
        });

        return redirect()
            ->route('tables.index')
            ->with('status', "Berhasil membuat {$diff} nomor meja sekaligus ({$prefix} {$start} s/d {$end}).");
    }

    public function update(Request $request, int $tableId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $table = DiningTable::where('business_id', $business->id)
            ->where('id', $tableId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('business_id', $business->id)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['required', 'string', 'in:available,occupied,reserved'],
        ], [
            'name.required' => 'Nama / nomor meja harus diisi.',
            'name.max' => 'Nama meja maksimal 100 karakter.',
            'outlet_id.exists' => 'Cabang yang dipilih tidak valid untuk bisnis ini.',
        ]);

        $outletId = null;
        if (! empty($validated['outlet_id'])) {
            $outlet = Outlet::where('business_id', $business->id)->where('id', $validated['outlet_id'])->first();
            if ($outlet !== null) {
                $outletId = $outlet->id;
            }
        }

        $table->name = trim($validated['name']);
        $table->outlet_id = $outletId;
        $table->capacity = ! empty($validated['capacity']) ? (int) $validated['capacity'] : 4;
        $table->status = $validated['status'];
        $table->save();

        return redirect()
            ->route('tables.index')
            ->with('status', 'Nomor meja "'.$table->name.'" berhasil diperbarui.');
    }

    public function destroy(Request $request, int $tableId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $table = DiningTable::where('business_id', $business->id)
            ->where('id', $tableId)
            ->firstOrFail();

        $name = $table->name;
        $table->delete();

        return redirect()
            ->route('tables.index')
            ->with('status', 'Nomor meja "'.$name.'" berhasil dihapus.');
    }
}
