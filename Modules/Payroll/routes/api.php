<?php

// Modules/Payroll/routes/api.php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\App\Http\Controllers\PayslipController;

Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {


    Route::prefix('/payroll')->group(function () {
        // لیست ماه‌های موجود
        Route::get('/months', [PayslipController::class, 'months'])->middleware('Payroll.view');

        // فیش حقوقی ماه خاص
        Route::get('/payslip/{yearMonth}', [PayslipController::class, 'show'])
            ->where('yearMonth', '[0-9]{6}')->middleware('Payroll.view');

        // دانلود PDF فیش حقوقی
        Route::get('/payslip/{yearMonth}/pdf', [PayslipController::class, 'downloadPdf'])
            ->where('yearMonth', '[0-9]{6}')->middleware('Payroll.view');

        // مسیرهای مدیران/HR
            Route::get('/employees', [PayslipController::class, 'employees'])->middleware('AdminPayroll.view');
            Route::get('/employees/{employeeId}/payslip/{yearMonth}', [PayslipController::class, 'showForEmployee'])->middleware('AdminPayroll.view');
            Route::get('/employees/{employeeId}/payslip/{yearMonth}/pdf', [PayslipController::class, 'downloadEmployeePdf'])
                 ->where('yearMonth', '[0-9]{6}')->middleware('AdminPayroll.view');
    });
});
