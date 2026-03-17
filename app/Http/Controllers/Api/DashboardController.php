<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TaskService;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTraits;

    public function overview(): JsonResponse
    {
        $totalUsers = User::query()->where('role', 'user')->count();
        $totalRunners = User::query()->where('role', 'runner')->count();
        $totalTaskServices = TaskService::query()->count();

        $completedOrderEarnings = (float) Order::query()
            ->where('admin_status', 'completed')
            ->sum('total_cost');

        $completedTaskServiceEarnings = (float) TaskService::query()
            ->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(CAST(price AS DECIMAL(10, 2))), 0) as total')
            ->value('total');

        $totalEarnings = round($completedOrderEarnings + $completedTaskServiceEarnings, 2);

        return $this->successResponse([
            'total_users' => $totalUsers,
            'total_runners' => $totalRunners,
            'total_task_services' => $totalTaskServices,
            'total_earnings' => $totalEarnings,
            'currency' => 'USD',
        ], 'Dashboard overview fetched successfully.', 200);
    }

    public function registrationStatistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:weekly,monthly,yearly',
        ]);

        $period = $validated['period'] ?? 'weekly';
        $now = CarbonImmutable::now();

        return match ($period) {
            'monthly' => $this->successResponse(
                $this->monthlyRegistrationStatistics($now),
                'Monthly registration statistics fetched successfully.',
                200
            ),
            'yearly' => $this->successResponse(
                $this->yearlyRegistrationStatistics($now),
                'Yearly registration statistics fetched successfully.',
                200
            ),
            default => $this->successResponse(
                $this->weeklyRegistrationStatistics($now),
                'Weekly registration statistics fetched successfully.',
                200
            ),
        };
    }

    public function taskServiceStatistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:weekly,monthly,yearly',
        ]);

        $period = $validated['period'] ?? 'weekly';
        $now = CarbonImmutable::now();

        $data = match ($period) {
            'monthly' => $this->monthlyTaskServiceStatisticsData($now),
            'yearly' => $this->yearlyTaskServiceStatisticsData($now),
            default => $this->weeklyTaskServiceStatisticsData($now),
        };

        return $this->successResponse($data, ucfirst($period).' task service statistics fetched successfully.', 200);
    }

    private function weeklyTaskServiceStatisticsData(CarbonImmutable $now): array
    {
        $weekStart = $now->startOfWeek()->startOfDay();
        $weekEnd = $weekStart->addDays(6)->endOfDay();

        $rows = $this->getTaskServiceDataGroupedByDay($weekStart, $weekEnd);

        $labels = [];
        $totalTasks = [];
        $newTasks = [];
        $pendingTasks = [];
        $completedTasks = [];
        $rejectedTasks = [];
        $completedEarnings = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->addDays($offset);
            $key = $day->toDateString();
            $row = $rows->get($key);

            $labels[] = $day->format('D');
            $totalTasks[] = (int) ($row->total_count ?? 0);
            $newTasks[] = (int) ($row->new_count ?? 0);
            $pendingTasks[] = (int) ($row->pending_count ?? 0);
            $completedTasks[] = (int) ($row->completed_count ?? 0);
            $rejectedTasks[] = (int) ($row->rejected_count ?? 0);
            $completedEarnings[] = round((float) ($row->completed_earnings ?? 0), 2);
        }

        return [
            'period' => 'weekly',
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'labels' => $labels,
            'total_tasks' => $totalTasks,
            'new_tasks' => $newTasks,
            'pending_tasks' => $pendingTasks,
            'completed_tasks' => $completedTasks,
            'rejected_tasks' => $rejectedTasks,
            'completed_earnings' => $completedEarnings,
            'totals' => $this->calculateTotals($totalTasks, $newTasks, $pendingTasks, $completedTasks, $rejectedTasks, $completedEarnings),
            'currency' => 'USD',
        ];
    }

    private function monthlyTaskServiceStatisticsData(CarbonImmutable $now): array
    {
        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfMonth()->endOfDay();

        $rows = $this->getTaskServiceDataGroupedByDay($monthStart, $monthEnd);

        $labels = [];
        $totalTasks = [];
        $newTasks = [];
        $pendingTasks = [];
        $completedTasks = [];
        $rejectedTasks = [];
        $completedEarnings = [];
        $daysInMonth = $monthStart->daysInMonth;

        for ($dayNumber = 1; $dayNumber <= $daysInMonth; $dayNumber++) {
            $day = $monthStart->setDay($dayNumber);
            $key = $day->toDateString();
            $row = $rows->get($key);

            $labels[] = (string) $dayNumber;
            $totalTasks[] = (int) ($row->total_count ?? 0);
            $newTasks[] = (int) ($row->new_count ?? 0);
            $pendingTasks[] = (int) ($row->pending_count ?? 0);
            $completedTasks[] = (int) ($row->completed_count ?? 0);
            $rejectedTasks[] = (int) ($row->rejected_count ?? 0);
            $completedEarnings[] = round((float) ($row->completed_earnings ?? 0), 2);
        }

        return [
            'period' => 'monthly',
            'month' => $monthStart->format('Y-m'),
            'labels' => $labels,
            'total_tasks' => $totalTasks,
            'new_tasks' => $newTasks,
            'pending_tasks' => $pendingTasks,
            'completed_tasks' => $completedTasks,
            'rejected_tasks' => $rejectedTasks,
            'completed_earnings' => $completedEarnings,
            'totals' => $this->calculateTotals($totalTasks, $newTasks, $pendingTasks, $completedTasks, $rejectedTasks, $completedEarnings),
            'currency' => 'USD',
        ];
    }

    private function yearlyTaskServiceStatisticsData(CarbonImmutable $now): array
    {
        $yearStart = $now->startOfYear()->startOfDay();
        $yearEnd = $now->endOfYear()->endOfDay();

        $rows = $this->getTaskServiceDataGroupedByDay($yearStart, $yearEnd);

        $labels = [];
        $totalTasks = array_fill(0, 12, 0);
        $newTasks = array_fill(0, 12, 0);
        $pendingTasks = array_fill(0, 12, 0);
        $completedTasks = array_fill(0, 12, 0);
        $rejectedTasks = array_fill(0, 12, 0);
        $completedEarnings = array_fill(0, 12, 0);

        foreach ($rows as $row) {
            $bucketDate = CarbonImmutable::parse((string) $row->bucket_date);
            $monthIndex = $bucketDate->month - 1;

            $totalTasks[$monthIndex] += (int) ($row->total_count ?? 0);
            $newTasks[$monthIndex] += (int) ($row->new_count ?? 0);
            $pendingTasks[$monthIndex] += (int) ($row->pending_count ?? 0);
            $completedTasks[$monthIndex] += (int) ($row->completed_count ?? 0);
            $rejectedTasks[$monthIndex] += (int) ($row->rejected_count ?? 0);
            $completedEarnings[$monthIndex] += (float) ($row->completed_earnings ?? 0);
        }

        for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
            $labels[] = $yearStart->setMonth($monthNumber)->format('M');
        }

        $completedEarnings = array_map(fn ($val) => round($val, 2), $completedEarnings);

        return [
            'period' => 'yearly',
            'year' => $yearStart->year,
            'labels' => $labels,
            'total_tasks' => $totalTasks,
            'new_tasks' => $newTasks,
            'pending_tasks' => $pendingTasks,
            'completed_tasks' => $completedTasks,
            'rejected_tasks' => $rejectedTasks,
            'completed_earnings' => $completedEarnings,
            'totals' => $this->calculateTotals($totalTasks, $newTasks, $pendingTasks, $completedTasks, $rejectedTasks, $completedEarnings),
            'currency' => 'USD',
        ];
    }

    private function getTaskServiceDataGroupedByDay($start, $end)
    {
        return TaskService::query()
            ->selectRaw('DATE(created_at) as bucket_date')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = "new" THEN 1 ELSE 0 END) as new_count')
            ->selectRaw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count')
            ->selectRaw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = "completed" THEN CAST(price AS DECIMAL(10, 2)) ELSE 0 END), 0) as completed_earnings')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('bucket_date')
            ->get()
            ->keyBy('bucket_date');
    }

    private function calculateTotals($totalTasks, $newTasks, $pendingTasks, $completedTasks, $rejectedTasks, $completedEarnings): array
    {
        return [
            'total_tasks' => array_sum($totalTasks),
            'new_tasks' => array_sum($newTasks),
            'pending_tasks' => array_sum($pendingTasks),
            'completed_tasks' => array_sum($completedTasks),
            'rejected_tasks' => array_sum($rejectedTasks),
            'completed_earnings' => round(array_sum($completedEarnings), 2),
        ];
    }

    private function weeklyRegistrationStatistics(CarbonImmutable $now): array
    {
        $weekStart = $now->startOfWeek()->startOfDay();
        $weekEnd = $weekStart->addDays(6)->endOfDay();

        $rows = User::query()
            ->selectRaw('DATE(created_at) as bucket_date')
            ->selectRaw('SUM(CASE WHEN role = "user" THEN 1 ELSE 0 END) as users_count')
            ->selectRaw('SUM(CASE WHEN role = "runner" THEN 1 ELSE 0 END) as runners_count')
            ->whereIn('role', ['user', 'runner'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->groupBy('bucket_date')
            ->get()
            ->keyBy('bucket_date');

        $labels = [];
        $users = [];
        $runners = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->addDays($offset);
            $key = $day->toDateString();
            $row = $rows->get($key);

            $labels[] = $day->format('D');
            $users[] = (int) ($row->users_count ?? 0);
            $runners[] = (int) ($row->runners_count ?? 0);
        }

        return [
            'period' => 'weekly',
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'labels' => $labels,
            'users' => $users,
            'runners' => $runners,
            'totals' => [
                'total_users_registrations' => array_sum($users),
                'total_runners_registrations' => array_sum($runners),
            ],
        ];
    }

    private function monthlyRegistrationStatistics(CarbonImmutable $now): array
    {
        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfMonth()->endOfDay();

        $rows = User::query()
            ->selectRaw('DATE(created_at) as bucket_date')
            ->selectRaw('SUM(CASE WHEN role = "user" THEN 1 ELSE 0 END) as users_count')
            ->selectRaw('SUM(CASE WHEN role = "runner" THEN 1 ELSE 0 END) as runners_count')
            ->whereIn('role', ['user', 'runner'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('bucket_date')
            ->get()
            ->keyBy('bucket_date');

        $labels = [];
        $users = [];
        $runners = [];
        $daysInMonth = $monthStart->daysInMonth;

        for ($dayNumber = 1; $dayNumber <= $daysInMonth; $dayNumber++) {
            $bucketDate = $monthStart->setDay($dayNumber)->toDateString();
            $row = $rows->get($bucketDate);

            $labels[] = (string) $dayNumber;
            $users[] = (int) ($row->users_count ?? 0);
            $runners[] = (int) ($row->runners_count ?? 0);
        }

        return [
            'period' => 'monthly',
            'month' => $monthStart->format('Y-m'),
            'labels' => $labels,
            'users' => $users,
            'runners' => $runners,
            'totals' => [
                'total_users_registrations' => array_sum($users),
                'total_runners_registrations' => array_sum($runners),
            ],
        ];
    }

    private function yearlyRegistrationStatistics(CarbonImmutable $now): array
    {
        $yearStart = $now->startOfYear()->startOfDay();
        $yearEnd = $now->endOfYear()->endOfDay();

        $rows = User::query()
            ->selectRaw('DATE(created_at) as bucket_date')
            ->selectRaw('SUM(CASE WHEN role = "user" THEN 1 ELSE 0 END) as users_count')
            ->selectRaw('SUM(CASE WHEN role = "runner" THEN 1 ELSE 0 END) as runners_count')
            ->whereIn('role', ['user', 'runner'])
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->groupBy('bucket_date')
            ->get();

        $labels = [];
        $users = array_fill(0, 12, 0);
        $runners = array_fill(0, 12, 0);

        foreach ($rows as $row) {
            $bucketDate = CarbonImmutable::parse((string) $row->bucket_date);
            $monthIndex = $bucketDate->month - 1;

            $users[$monthIndex] += (int) ($row->users_count ?? 0);
            $runners[$monthIndex] += (int) ($row->runners_count ?? 0);
        }

        for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
            $month = $yearStart->setMonth($monthNumber);

            $labels[] = $month->format('M');
        }

        return [
            'period' => 'yearly',
            'year' => $yearStart->year,
            'labels' => $labels,
            'users' => $users,
            'runners' => $runners,
            'totals' => [
                'total_users_registrations' => array_sum($users),
                'total_runners_registrations' => array_sum($runners),
            ],
        ];
    }
}
