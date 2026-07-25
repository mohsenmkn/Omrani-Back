<?php

use Modules\Dashboard\App\Http\Controllers\DashboardController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/project-stats', [DashboardController::class, 'projectStats']);
        Route::get('/financial-stats', [DashboardController::class, 'financialStats']);
        Route::get('/recent-projects', [DashboardController::class, 'recentProjects']);
        Route::get('/progress-stats', [DashboardController::class, 'progressStats']);
        Route::get('/weekly-stats', [DashboardController::class, 'weeklyStats']);
        Route::get('/budget-by-category', [DashboardController::class, 'budgetByCategory']);
    });
});
