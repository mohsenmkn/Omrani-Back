<?php

use Illuminate\Support\Facades\Route;
use Modules\Library\App\Http\Controllers\BookController;
use Modules\Library\App\Http\Controllers\ReservationController;
use Modules\Library\App\Http\Controllers\CategoryController;
use Modules\Library\App\Http\Controllers\DashboardController;
use Modules\Library\App\Http\Controllers\NotificationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    // ========== کاربر عادی ==========

    // کتاب‌ها
    Route::get('library/books', [BookController::class, 'index']);
    Route::get('library/books/{id}', [BookController::class, 'show']);

    // دسته‌بندی‌ها
    Route::get('library/categories', [CategoryController::class, 'index']);

    // رزروهای من
    Route::get('library/my-reservations', [ReservationController::class, 'myReservations']);
    Route::post('library/reservations', [ReservationController::class, 'store']);
    Route::put('library/reservations/{id}/cancel', [ReservationController::class, 'cancel']);

    // 🔑 اعلان‌های من
    Route::get('library/my-notifications', [NotificationController::class, 'index']);
    Route::put('library/my-notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('library/my-notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // ========== مدیر کتابخانه ==========

    Route::middleware(['permission:librarybooks.manage'])->group(function () {
        // مدیریت کتاب‌ها
        Route::post('library/admin/books', [BookController::class, 'store']);
        Route::put('library/admin/books/{id}', [BookController::class, 'update']);
        Route::delete('library/admin/books/{id}', [BookController::class, 'destroy']);

        // مدیریت دسته‌بندی‌ها
        Route::post('library/admin/categories', [CategoryController::class, 'store']);
        Route::put('library/admin/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('library/admin/categories/{id}', [CategoryController::class, 'destroy']);

        // 🔑 مدیریت نسخه‌ها
        Route::post('library/admin/books/{id}/copies', [BookController::class, 'storeCopy']);
        Route::put('library/admin/copies/{id}', [BookController::class, 'updateCopy']);
        Route::delete('library/admin/copies/{id}', [BookController::class, 'destroyCopy']);
    });

    Route::middleware(['permission:libraryreservations.manage'])->group(function () {
        // مدیریت رزروها
        Route::get('library/admin/reservations', [ReservationController::class, 'adminIndex']);
        Route::put('library/admin/reservations/{id}/approve', [ReservationController::class, 'approve']);
        Route::put('library/admin/reservations/{id}/reject', [ReservationController::class, 'reject']);
        Route::put('library/admin/reservations/{id}/pickup', [ReservationController::class, 'pickup']);
        Route::put('library/admin/reservations/{id}/return', [ReservationController::class, 'returnBook']);
    });

    Route::middleware(['permission:librarystatistics.view'])->group(function () {
        // داشبورد آماری
        Route::get('library/admin/statistics', [DashboardController::class, 'statistics']);
    });
});
