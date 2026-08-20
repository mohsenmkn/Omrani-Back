<?php

use Illuminate\Support\Facades\Route;
use Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController;
use Modules\Complaint\App\Http\Controllers\Api\CategoryController;
use Modules\Complaint\App\Http\Controllers\Api\ComplaintController;
use Modules\Complaint\App\Http\Controllers\Api\ComplaintStatisticsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    // سمت پرسنل
    Route::prefix('complaints')->group(function () {
        Route::get('categories', [ComplaintController::class, 'categories'])
            ->middleware('permission:complaints.create');

        Route::get('my', [ComplaintController::class, 'my'])
            ->middleware('permission:complaints.read');

        Route::post('/', [ComplaintController::class, 'store'])
            ->middleware('permission:complaints.create');

        Route::get('{complaint}', [ComplaintController::class, 'show'])
            ->middleware('permission:complaints.read');

        Route::put('{complaint}', [ComplaintController::class, 'update'])
            ->middleware('permission:complaints.update');

        Route::delete('{complaint}', [ComplaintController::class, 'destroy'])
            ->middleware('permission:complaints.delete');

        Route::post('{complaint}/attachments', [ComplaintController::class, 'uploadAttachment'])
            ->middleware('permission:complaints.create');
    });

    // سمت ادمین
    Route::prefix('admin/complaints')->group(function () {
        Route::get('/', [AdminComplaintController::class, 'index'])
            ->middleware('permission:complaints.manage');
        // ✅ این خط را قبل از {complaint} اضافه کن
        Route::get('statistics', [ComplaintStatisticsController::class, 'index'])
            ->middleware('permission:complaintstatistics.view');

        Route::get('/', [AdminComplaintController::class, 'index'])
            ->middleware('permission:complaints.manage');


        // ✅ این دو خط باید قبل از {complaint} باشند
        Route::get('statistics', [\Modules\Complaint\App\Http\Controllers\Api\ComplaintStatisticsController::class, 'index'])
            ->middleware('permission:complaintstatistics.view');

        Route::get('export', [\Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController::class, 'export'])
            ->middleware('permission:complaints.export');

        Route::get('/', [\Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController::class, 'index'])
            ->middleware('permission:complaints.manage');

        Route::get('{complaint}', [\Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController::class, 'show'])
            ->middleware('permission:complaints.manage');

        Route::put('{complaint}/status', [\Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController::class, 'updateStatus'])
            ->middleware('permission:complaints.manage');

        Route::post('{complaint}/replies', [\Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController::class, 'storeReply'])
            ->middleware('permission:complaints.reply');


        Route::get('{complaint}', [AdminComplaintController::class, 'show'])
            ->middleware('permission:complaints.manage');

        Route::put('{complaint}/status', [AdminComplaintController::class, 'updateStatus'])
            ->middleware('permission:complaints.manage');

        Route::post('{complaint}/replies', [AdminComplaintController::class, 'storeReply'])
            ->middleware('permission:complaints.reply');

        Route::get('{complaint}', [AdminComplaintController::class, 'show'])
            ->middleware('permission:complaints.manage');

        Route::put('{complaint}/status', [AdminComplaintController::class, 'updateStatus'])
            ->middleware('permission:complaints.manage');

        Route::post('{complaint}/replies', [AdminComplaintController::class, 'storeReply'])
            ->middleware('permission:complaints.reply');
    });

    // مدیریت دسته‌بندی‌ها
    Route::prefix('admin/complaint-categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])
            ->middleware('permission:complaintcategories.read');

        Route::post('/', [CategoryController::class, 'store'])
            ->middleware('permission:complaintcategories.create');

        Route::put('{complaintCategory}', [CategoryController::class, 'update'])
            ->middleware('permission:complaintcategories.update');

        Route::delete('{complaintCategory}', [CategoryController::class, 'destroy'])
            ->middleware('permission:complaintcategories.delete');
    });
});
