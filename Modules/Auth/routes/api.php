<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Auth\App\Http\Controllers\AuthController;



//Route::prefix('v1/auth')->group(function() {
//
//    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
//    //Routes Auth
//    Route::post('/login', [AuthController::class, 'login']);
//    Route::post('/forgot-password/send-otp', [AuthController::class, 'sendOtp']);
//    Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);
//    Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyOtp']);
//    Route::get('/captcha', [AuthController::class, 'getCaptcha']);
//});

Route::prefix('v1/auth')->group(function() {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('/captcha', [AuthController::class, 'getCaptcha']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/roles', [AuthController::class, 'getRoles'])
            ->middleware('permission:users.read');

        Route::get('/permissions', [AuthController::class, 'getPermissions'])
            ->middleware('permission:users.read');

        Route::get('/users/{user}/access', [AuthController::class, 'getUserAccess'])
            ->middleware('permission:users.read');

        Route::put('/users/{user}/permissions', [AuthController::class, 'syncUserPermissions'])
            ->middleware('permission:users.update');

        Route::put('/users/{user}/roles', [AuthController::class, 'syncUserRoles'])
            ->middleware('permission:users.update');


    });
});


