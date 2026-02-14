<?php

namespace App\Http\Controllers;

use App\Services\DocumentAlertService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentAlertService $alertService)
    {
    }

    /**
     * Display the dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $alertData = $this->alertService->getDashboardData($user);

        return view('dashboard', [
            'alertCounts' => $alertData['counts'],
            'recentAlerts' => $alertData['recent_alerts'],
        ]);
    }
}
