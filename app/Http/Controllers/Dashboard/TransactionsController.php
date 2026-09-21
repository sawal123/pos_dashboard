<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardTransactionsData;
use Illuminate\Http\Request;
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

        $data = $this->transactionsData->get($currentBusiness, $filters);

        return view('transactions.index', [
            'transactions' => $data['transactions'],
            'metrics' => $data['metrics'],
            'filterOptions' => $data['filterOptions'],
            'currentFilters' => $data['currentFilters'],
        ]);
    }
}
