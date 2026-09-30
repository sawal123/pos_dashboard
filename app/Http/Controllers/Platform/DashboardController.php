<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the Platform Admin foundation overview.
     */
    public function index(Request $request): View
    {
        return view('platform.dashboard', [
            'user' => $request->user(),
        ]);
    }
}
