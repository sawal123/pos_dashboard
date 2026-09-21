<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardTransactionsData;
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
        $currentBusiness = $request->attributes->get('dashboard_business');

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
        ]);
    }
}
