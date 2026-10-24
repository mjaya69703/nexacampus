<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function financialDashboard(Request $request): Response
    {
        $user = $request->user();
        return Inertia::render('Admin/Financial/Dashboard', [
            'shell' => ShellProps::make($user, 'Financial', 'Dashboard Keuangan'),
            'stats' => [],
            'urls' => ['index' => route('admin.financial.dashboard.index')],
        ]);
    }

    public function studentServicesDashboard(Request $request): Response
    {
        $user = $request->user();
        return Inertia::render('Admin/StudentServices/Dashboard', [
            'shell' => ShellProps::make($user, 'Student Services', 'Dashboard Layanan'),
            'stats' => [],
            'urls' => ['index' => route('admin.student-services.dashboard.index')],
        ]);
    }
}
