<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Http\Models\Announcement;

class DashboardController extends Controller
{


    /**
     * دریافت اطلاعات کامل داشبورد (پروفایل + فیش + اعلانات)
     */
//    public function getDashboardData(Request $request)
//    {
//        $user = $request->user();
//
//        // 1. اطلاعات پروفایل و شغلی
//        $profile = $user->getDashboardProfile();
//
//        // 2. آخرین فیش حقوقی
//        $months = $user->getAvailablePayslipMonths();
//        $latestMonth = $months[0] ?? null;
//        $latestPayslip = null;
//
//        if ($latestMonth) {
//            $latestPayslip = $user->getPayslipSummary($latestMonth);
//        }
//
//        return response()->json([
//            'profile' => $profile,
//            'latest_payslip' => $latestPayslip,
//            'latest_month' => $latestMonth,
//            'roles' => $user->getRoleNames()->values(),
//            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
//        ]);
//    }
    public function getDashboardData(Request $request)
    {
        $user = $request->user();

        // 1. اطلاعات پروفایل و شغلی (شامل اطلاعات مرخصی)
        $profile = $user->getDashboardProfile();

        // 2. آخرین فیش حقوقی
        $months = $user->getAvailablePayslipMonths();
        $latestMonth = $months[0] ?? null;
        $latestPayslip = null;

        if ($latestMonth) {
            $latestPayslip = $user->getPayslipSummary($latestMonth);
        }

        return response()->json([
            'profile' => $profile,
            'latest_payslip' => $latestPayslip,
            'latest_month' => $latestMonth,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }
    /**
     * دریافت اعلانات
     */
    public function getAnnouncements(Request $request)
    {
        $user = $request->user();

        $announcements = Announcement::active()
            ->orderBy('published_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($announcement) use ($user) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'description' => $announcement->description,
                    'type' => $announcement->type,
                    'is_read' => $announcement->isReadBy($user),
                    'created_at' => $announcement->created_at,
                    'published_at' => $announcement->published_at,
                ];
            });

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    /**
     * علامت‌گذاری اعلان به عنوان خوانده‌شده
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $announcement = Announcement::findOrFail($id);

        if (!$announcement->isReadBy($user)) {
            $announcement->readers()->attach($user->id, [
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'اعلان به عنوان خوانده‌شده علامت‌گذاری شد.',
        ]);
    }

    /**
     * دریافت خلاصه فیش حقوقی (برای backward compatibility)
     */
    public function getPayslipSummary(Request $request)
    {
        $user = $request->user();

        $months = $user->getAvailablePayslipMonths();
        $latestMonth = $months[0] ?? null;
        $payslip = null;

        if ($latestMonth) {
            $payslip = $user->getPayslipSummary($latestMonth);
        }

        return response()->json([
            'payslip' => $payslip,
            'month' => $latestMonth,
        ]);
    }
}
