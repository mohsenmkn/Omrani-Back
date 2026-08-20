<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HR\App\Http\Controllers\EmployeeController;
use Modules\HR\App\Http\Controllers\OrgChartController;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::middleware(['auth:sanctum'])->prefix('v1/hr')->name('hr.')->group(function () {
    // ─────────────────────────────────────────────
    //  چارت سازمانی
    // ─────────────────────────────────────────────

    // GET /api/v1/hr/org-chart
    // دریافت درخت کامل چارت سازمانی + پرسنل هر واحد
    Route::get('/org-chart', [OrgChartController::class, 'index'])
        ->middleware('permission:hr.view')
        ->name('org-chart.index');

    // GET /api/v1/hr/org-chart/units
    // لیست تخت واحدها (برای Select، فیلتر و جستجو)
    Route::get('/org-chart/units', [OrgChartController::class, 'units'])
        ->middleware('permission:hr.view')
        ->name('org-chart.units');

    // GET /api/v1/hr/org-chart/search?q=نام
    // جستجو در چارت (واحد یا پرسنل)
    Route::get('/org-chart/search', [OrgChartController::class, 'search'])
        ->middleware('permission:hr.view')
        ->name('org-chart.search');

    // ── ✅ پرسنل (جدید) ──
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('permission:hr.view')
        ->name('employees.index');

    Route::get('/employees/{user}', [EmployeeController::class, 'show'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.show');

    // ✅ اطلاعات خانواده کارمند
    Route::get('/employees/{user}/relatives', [EmployeeController::class, 'relatives'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.relatives');

    // ✅ sync دستی یک کاربر (فقط ادمین)
    Route::post('/sync/user/{user}', function (\Modules\Auth\App\Models\User $user) {
        $position = app(\Modules\HR\App\Services\GtarabarSyncService::class)
            ->syncUser($user, 'api');

        if ($position) {
            return response()->json([
                'message' => 'sync با موفقیت انجام شد.',
                'position' => [
                    'post_title' => $position->post_title,
                    'job_title'  => $position->job_title,
                    'unit'       => $position->unit?->title,
                    'synced_at'  => $position->synced_at,
                ],
            ]);
        }

        return response()->json(['message' => 'خطا در sync'], 500);
    })
        ->middleware('permission:hr.manage')
        ->name('sync.user');

// ✅ وضعیت sync
    Route::get('/sync/status', function () {
        return response()->json([
            'positions' => \Modules\HR\App\Models\EmployeePosition::count(),
            'relatives' => \Modules\HR\App\Models\EmployeeRelative::count(),
            'units'     => \Modules\HR\App\Models\OrganizationalUnit::count(),
            'last_sync' => \Modules\HR\App\Models\EmployeePosition::max('synced_at'),
            'today_success' => \Modules\HR\App\Models\HRSyncLog::today()->success()->count(),
            'today_failed'  => \Modules\HR\App\Models\HRSyncLog::today()->failed()->count(),
        ]);
    })
        ->middleware('permission:hr.manage')
        ->name('sync.status');

    Route::get('/employees/{user}/history', [EmployeeController::class, 'history'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.history');

    Route::get('/employees/{user}/history/details', [EmployeeController::class, 'historyDetails'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.history.details');

    // ✅ تردد کارمند
    Route::get('/employees/{user}/attendance', [EmployeeController::class, 'attendance'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.attendance');



    // ─────────────────────────────────────────────
    //  🔮 آینده — لیست پرسنل
    // ─────────────────────────────────────────────

    // Route::get('/employees', [EmployeeController::class, 'index'])
    //     ->middleware('permission:hr.view')
    //     ->name('employees.index');

    // Route::get('/employees/{user}', [EmployeeController::class, 'show'])
    //     ->middleware('permission:hr.view')
    //     ->name('employees.show');


    // ─────────────────────────────────────────────
    //  🔮 آینده — sync دستی (فقط مدیر)
    // ─────────────────────────────────────────────

    // Route::post('/sync', [SyncController::class, 'run'])
    //     ->middleware('permission:hr.manage')
    //     ->name('sync');
});
