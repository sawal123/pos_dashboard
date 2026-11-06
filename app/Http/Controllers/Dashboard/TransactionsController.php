<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Sale;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardTransactionsData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionsController extends Controller
{
    public function __construct(
        protected DashboardTransactionsData $transactionsData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        $role = $request->attributes->get('dashboard_business_role');
        $permissions = $request->attributes->get('dashboard_business_permissions', []);

        $canEdit = $currentBusiness !== null && (
            $role === Business::ROLE_OWNER
            || in_array('*', $permissions, true)
            || in_array(BusinessPermission::TRANSACTIONS_MANAGE, $permissions, true)
        );

        $filters = $request->only([
            'q',
            'date',
            'start_date',
            'end_date',
            'outlet_id',
            'payment_method',
            'payment_status',
            'status',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7days', '30days', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
        ]);

        // If validation fails on any field (e.g. malformed date or bad input),
        // strip the invalid parameter so the request falls back safely without 500
        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->transactionsData->get($currentBusiness, $filters);

        return view('transactions.index', [
            'transactions' => $data['transactions'],
            'metrics' => $data['metrics'],
            'filterOptions' => $data['filterOptions'],
            'currentFilters' => $data['currentFilters'],
            'hasAnyTransactions' => $data['hasAnyTransactions'],
            'canEdit' => $canEdit,
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        if ($currentBusiness === null || (int) $sale->business_id !== (int) $currentBusiness->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_status' => ['required', 'string', Rule::in(['paid', 'unpaid', 'Lunas', 'Belum Lunas'])],
            'status' => ['required', 'string', Rule::in(['completed', 'cancelled', 'Selesai', 'Dibatalkan'])],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $canonicalPaymentStatus = match (strtolower($validated['payment_status'])) {
            'lunas', 'paid' => 'paid',
            'belum lunas', 'unpaid' => 'unpaid',
            default => $validated['payment_status'],
        };

        $canonicalStatus = match (strtolower($validated['status'])) {
            'selesai', 'completed' => 'completed',
            'dibatalkan', 'cancelled', 'canceled' => 'cancelled',
            default => $validated['status'],
        };

        $rawMethod = strtolower(trim($validated['payment_method']));
        $canonicalPaymentMethod = match ($rawMethod) {
            'cash', 'tunai' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card', 'kartu' => 'Kartu',
            default => ucwords($validated['payment_method']),
        };

        $cashReceived = null;
        $changeAmount = null;
        if (in_array($canonicalPaymentMethod, ['Tunai', 'cash'], true)) {
            if (isset($validated['cash_received']) && $validated['cash_received'] !== null && $validated['cash_received'] !== '') {
                $cashReceived = (int) round((float) $validated['cash_received']);
                $changeAmount = max(0, $cashReceived - (int) $sale->total_amount);
            }
        }

        $paidAt = $sale->paid_at;
        if ($canonicalPaymentStatus === 'paid' && $paidAt === null) {
            $paidAt = now();
        } elseif ($canonicalPaymentStatus === 'unpaid') {
            $paidAt = null;
        }

        $sale->update([
            'payment_method' => $canonicalPaymentMethod,
            'payment_status' => $canonicalPaymentStatus,
            'status' => $canonicalStatus,
            'paid_at' => $paidAt,
            'cash_received' => $cashReceived,
            'change_amount' => $changeAmount,
            'note' => array_key_exists('note', $validated) ? $validated['note'] : $sale->note,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaksi '.$sale->transaction_number.' berhasil diperbarui.',
                'sale' => $sale,
            ]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaksi '.$sale->transaction_number.' berhasil diperbarui.');
    }
}
