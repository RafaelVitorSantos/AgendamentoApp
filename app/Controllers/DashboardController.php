<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DashboardService;
use App\Services\PlanLimiter;

class DashboardController extends Controller
{
    public function index(): void
    {
        $dashboard = new DashboardService();
        $limiter   = new PlanLimiter();

        $data = [
            'todayStats'      => $dashboard->getTodayStats(),
            'todayRevenue'    => $dashboard->getTodayRevenue(),
            'occupancyRate'   => $dashboard->getOccupancyRate(),
            'upcoming'        => $dashboard->getUpcomingAppointments(8),
            'topServices'     => $dashboard->getTopServices(5),
            'weeklyRevenue'   => $dashboard->getWeeklyRevenue(),
            'pendingCount'    => $dashboard->getPendingConfirmations(),
            'birthdays'       => $dashboard->getTodayBirthdays(),
            'usage'           => $limiter->getUsageSummary(),
            'pageTitle'       => 'Dashboard',
        ];

        $this->render('dashboard.index', $data);
    }
}
