<?php
// Modules/Dashboard/App/Http/Controllers/DashboardController.php

namespace Modules\Dashboard\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Project\App\Models\Project;
use Modules\Budget\App\Models\Budget;
use Modules\Contract\App\Models\Contract;
use Modules\WBS\App\Models\WbsItem;
use Modules\PettyCash\App\Models\PettyCash;
use Modules\WBS\App\Models\Task;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * دریافت خلاصه کلی اطلاعات
     */
    public function summary(Request $request)
    {
        //$companyId = $request->user()->company_id;

        $companyId =1;

        return response()->json([
            'total_projects' => Project::where('company_id', $companyId)->count(),
            'active_projects' => Project::where('company_id', $companyId)->where('status', 'active')->count(),
            'total_contracts' => Contract::where('company_id', $companyId)->count(),
            'active_contracts' => Contract::where('company_id', $companyId)->where('status', 'active')->count(),
            'total_budget' => Budget::whereHas('project', fn($q) => $q->where('company_id', $companyId))->sum('amount'),
            'total_tasks' => Task::whereHas('wbsItem.project', fn($q) => $q->where('company_id', $companyId))->count(),
            'completed_tasks' => Task::whereHas('wbsItem.project', fn($q) => $q->where('company_id', $companyId))
                ->where('status', 'completed')->count(),
            'total_documents' => \Modules\Document\App\Models\Document::where('company_id', $companyId)->count(),
        ]);
    }

    /**
     * دریافت آمار پروژه‌ها بر اساس وضعیت
     */
    public function projectStats(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId =1;
        $stats = Project::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        $labels = [
            'planning' => 'در حال برنامه‌ریزی',
            'active' => 'فعال',
            'on_hold' => 'متوقف',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];

        $colors = [
            'planning' => '#f59e0b',
            'active' => '#3b82f6',
            'on_hold' => '#ef4444',
            'completed' => '#22c55e',
            'cancelled' => '#6b7280',
        ];

        return response()->json([
            'labels' => $stats->map(fn($item) => $labels[$item->status] ?? $item->status),
            'data' => $stats->map(fn($item) => $item->count),
            'colors' => $stats->map(fn($item) => $colors[$item->status] ?? '#6b7280'),
        ]);
    }

    /**
     * دریافت آمار مالی
     */
    public function financialStats(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId =1;
        // کل مبلغ قراردادها
        $totalContracts = Contract::where('company_id', $companyId)->sum('amount');
        $totalPaid = Contract::where('company_id', $companyId)->sum('paid_amount');
        $totalRemaining = $totalContracts - $totalPaid;

        // بودجه پروژه‌ها
        $totalBudget = Budget::whereHas('project', fn($q) => $q->where('company_id', $companyId))->sum('amount');

        // تنخواه‌ها
        $pettyCash = PettyCash::whereHas('project', fn($q) => $q->where('company_id', $companyId))
            ->select(DB::raw('SUM(initial_amount) as total_initial, SUM(current_balance) as total_balance'))
            ->first();

        return response()->json([
            'total_contracts' => $totalContracts,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'total_budget' => $totalBudget,
            'petty_cash_initial' => $pettyCash->total_initial ?? 0,
            'petty_cash_balance' => $pettyCash->total_balance ?? 0,
            'budget_usage_percent' => $totalBudget > 0 ? round(($totalPaid / $totalBudget) * 100, 2) : 0,
        ]);
    }

    /**
     * دریافت ۵ پروژه آخر
     */
    public function recentProjects(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId =1;

        $projects = Project::where('company_id', $companyId)
            ->with(['manager', 'company', 'wbsItems.tasks']) // ✅ بارگذاری تمام WBS و تسک‌ها
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($project) {
                // ✅ محاسبه صحیح پیشرفت از تمام WBS Items
                $progress = $this->calculateProjectProgress($project);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $project->status,
                    'status_label' => $this->getProjectStatusLabel($project->status),
                    'manager' => $project->manager?->name,
                    'total_budget' => $project->total_budget,
                    'created_at' => $project->created_at?->toDateString(),
                    'progress' => $progress,
                ];
            });

        return response()->json($projects);
    }

    /**
     * دریافت پیشرفت کلی پروژه‌ها
     */
    public function progressStats(Request $request)
    {
        //$companyId = $request->user()->company_id;
        //$companyId = $request->user()->company_id;
        $companyId =1;
        $projects = Project::where('company_id', $companyId)
            ->with(['wbsItems.tasks']) // ✅ بارگذاری تمام WBS و تسک‌ها
            ->get();

        $progressData = $projects->map(function ($project) {
            // ✅ محاسبه صحیح پیشرفت
            $allTasks = collect();

            foreach ($project->wbsItems as $wbsItem) {
                $tasks = $wbsItem->tasks;
                $allTasks = $allTasks->merge($tasks);
            }

            $totalTasks = $allTasks->count();
            $completedTasks = $allTasks->filter(fn($task) => $task->status === 'completed')->count();

            // اگر تسکی وجود نداشت
            if ($totalTasks === 0) {
                $wbsProgress = $project->wbsItems->avg('progress_percent') ?? 0;
                $progress = round($wbsProgress, 2);
            } else {
                $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
            }

            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'progress' => $progress,
            ];
        });

        return response()->json($progressData);
    }

    /**
     * دریافت آمار هفتگی (برای نمودار)
     */
    public function weeklyStats(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId =1;

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // پروژه‌های ایجاد شده در ۳۰ روز گذشته
        $projectsPerDay = Project::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // قراردادهای ایجاد شده در ۳۰ روز گذشته
        $contractsPerDay = Contract::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // تکمیل تاریخ‌ها
        $dates = [];
        $projectsCount = [];
        $contractsCount = [];

        for ($date = clone $startDate; $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $dates[] = $date->format('d M');

            $projectsCount[] = $projectsPerDay->firstWhere('date', $dateStr)?->count ?? 0;
            $contractsCount[] = $contractsPerDay->firstWhere('date', $dateStr)?->count ?? 0;
        }

        return response()->json([
            'dates' => $dates,
            'projects' => $projectsCount,
            'contracts' => $contractsCount,
        ]);
    }

    /**
     * دریافت آمار بودجه بر اساس دسته‌بندی
     */
    public function budgetByCategory(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId =1;

        $budgetByType = Budget::whereHas('project', fn($q) => $q->where('company_id', $companyId))
            ->select('type', DB::raw('sum(amount) as total'))
            ->groupBy('type')
            ->get();

        $labels = [
            'initial' => 'اولیه',
            'revised' => 'تجدیدنظر',
            'contingency' => 'اضطراری',
        ];

        $colors = [
            'initial' => '#3b82f6',
            'revised' => '#f59e0b',
            'contingency' => '#ef4444',
        ];

        return response()->json([
            'labels' => $budgetByType->map(fn($item) => $labels[$item->type] ?? $item->type),
            'data' => $budgetByType->map(fn($item) => $item->total),
            'colors' => $budgetByType->map(fn($item) => $colors[$item->type] ?? '#6b7280'),
        ]);
    }

    // Helper Methods
    private function getProjectStatusLabel($status)
    {
        $labels = [
            'planning' => 'در حال برنامه‌ریزی',
            'active' => 'فعال',
            'on_hold' => 'متوقف',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];
        return $labels[$status] ?? $status;
    }

    private function calculateProjectProgress($project)
    {
        // جمع‌آوری تمام تسک‌های پروژه از تمام WBS Items
        $allTasks = collect();

        foreach ($project->wbsItems as $wbsItem) {
            // بارگذاری تسک‌های هر WBS Item
            $tasks = $wbsItem->tasks;
            $allTasks = $allTasks->merge($tasks);
        }

        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->filter(fn($task) => $task->status === 'completed')->count();

        // اگر WBS Item وجود نداشت، از خود پروژه استفاده کن
        if ($totalTasks === 0) {
            // می‌توانید از progress_percent خود WBS Items استفاده کنید
            $wbsProgress = $project->wbsItems->avg('progress_percent') ?? 0;
            return round($wbsProgress, 2);
        }

        return $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
    }
}
