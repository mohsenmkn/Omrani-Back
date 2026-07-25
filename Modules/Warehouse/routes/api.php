<?php
// Modules/Inventory/Routes/api.php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\App\Http\Controllers\MaterialController;
use Modules\warehouse\App\Http\Controllers\InventoryCategoryController;
use Modules\warehouse\App\Http\Controllers\WarehouseTransactionController;
use Modules\warehouse\App\Http\Controllers\InventoryReportController;

/*
|--------------------------------------------------------------------------
| API Routes - Inventory Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    // ============================================
    // 1. دسته‌بندی کالاها (Categories)
    // ============================================
    Route::prefix('inventory-categories')->group(function () {
        Route::get('/', [InventoryCategoryController::class, 'index']);
        Route::post('/', [InventoryCategoryController::class, 'store']);
        Route::get('/{category}', [InventoryCategoryController::class, 'show']);
        Route::put('/{category}', [InventoryCategoryController::class, 'update']);
        Route::delete('/{category}', [InventoryCategoryController::class, 'destroy']);
        Route::get('/tree', [InventoryCategoryController::class, 'tree']); // دریافت درخت
    });

    // ============================================
    // 2. کالاها (Materials)
    // ============================================
    Route::prefix('materials')->group(function () {
        Route::get('/', [MaterialController::class, 'index']);
        Route::post('/', [MaterialController::class, 'store']);
        Route::get('/{material}', [MaterialController::class, 'show']);
        Route::put('/{material}', [MaterialController::class, 'update']);
        Route::delete('/{material}', [MaterialController::class, 'destroy']);
        Route::get('/{material}/transactions', [MaterialController::class, 'transactions']);
        Route::get('/{material}/stock-history', [MaterialController::class, 'stockHistory']);
    });

    // ============================================
    // 3. تراکنش‌های انبار (Warehouse Transactions)
    // ============================================
    Route::prefix('warehouse-transactions')->group(function () {
        Route::get('/', [WarehouseTransactionController::class, 'index']);
        Route::post('/', [WarehouseTransactionController::class, 'store']);
        Route::get('/{transaction}', [WarehouseTransactionController::class, 'show']);
        Route::delete('/{transaction}', [WarehouseTransactionController::class, 'destroy']);
        Route::get('/by-material/{materialId}', [WarehouseTransactionController::class, 'byMaterial']);
        Route::get('/by-project/{projectId}', [WarehouseTransactionController::class, 'byProject']);
    });

    // ============================================
    // 4. گزارشات انبار (Reports)
    // ============================================
    Route::prefix('inventory-reports')->group(function () {
        Route::get('/stock', [InventoryReportController::class, 'stockReport']);
        Route::get('/consumption', [InventoryReportController::class, 'consumptionReport']);
        Route::get('/project-consumption/{projectId}', [InventoryReportController::class, 'projectConsumption']);
        Route::get('/wbs-consumption/{wbsItemId}', [InventoryReportController::class, 'wbsConsumption']);
        Route::get('/movement', [InventoryReportController::class, 'movementReport']);
        Route::get('/low-stock', [InventoryReportController::class, 'lowStockReport']);
    });

    Route::get('/projects/{projectId}/materials', [InventoryReportController::class, 'projectMaterials']);

});
