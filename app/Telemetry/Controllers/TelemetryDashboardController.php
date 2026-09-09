<?php

namespace App\Telemetry\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class TelemetryDashboardController extends Controller
{
    /**
     * Render the standalone mobile-first single-page application shell.
     */
    public function index(): View
    {
        return view('telemetry.dashboard');
    }
}
