<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Acl\App\Http\Controllers\AclController;



Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {
    Route::get('acl/roles/', [AclController::class, 'index']);
    Route::get('acl/roles/all', [AclController::class, 'getAllRoles'])->middleware('permission:roles.update');
    Route::get('acl/permissions/all', [AclController::class, 'getAllPermissions'])->middleware('permission:roles.update');
    Route::post('acl/roles/', [AclController::class, 'store'])->middleware('permission:roles.create');
    Route::put('acl/roles/{id}', [AclController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('acl/roles/{id}', [AclController::class, 'destroy'])->middleware('permission:roles.delete');
});
