<?php

use Illuminate\Support\Facades\Route;
use Modules\Assessment\App\Http\Controllers\AssessmentAssignmentController;
use Modules\Assessment\App\Http\Controllers\AssessmentController;
use Modules\Assessment\App\Http\Controllers\AssessmentCycleController;
use Modules\Assessment\App\Http\Controllers\AssessmentPeriodController;

Route::prefix('v1/assessment')->middleware(['auth:sanctum'])->group(function () {

    // ── چرخه‌ها ──
    Route::get('cycles', [AssessmentCycleController::class, 'index'])
        ->middleware('permission:assessment.manage');
    Route::post('cycles', [AssessmentCycleController::class, 'store'])
        ->middleware('permission:assessment.manage');
    Route::post('cycles/{cycle}/activate', [AssessmentCycleController::class, 'activate'])
        ->middleware('permission:assessment.manage');
    Route::post('cycles/{cycle}/close', [AssessmentCycleController::class, 'close'])
        ->middleware('permission:assessment.manage');

    // ── کاتالوگ‌ها ──
    Route::get('users', [AssessmentController::class, 'users'])
        ->middleware('permission:assessment.manage');
    Route::get('posts', [AssessmentController::class, 'posts'])
        ->middleware('permission:assessment.view');
    Route::get('methods', [AssessmentController::class, 'methods'])
        ->middleware('permission:assessment.view');

    // ── شناسنامه‌ها ──
    Route::get('posts/{post}/questions', [AssessmentController::class, 'postQuestions'])
        ->middleware('permission:assessment.manage');
    Route::put('questions/bulk', [AssessmentController::class, 'bulkUpdateQuestions'])
        ->middleware('permission:assessment.manage');
    Route::put('questions/{question}', [AssessmentController::class, 'updateQuestion'])
        ->whereNumber('question')
        ->middleware('permission:assessment.manage');

    // ── ارزیابی‌ها ──
    Route::get('assessments', [AssessmentController::class, 'index'])
        ->middleware('permission:assessment.view');
    Route::post('assessments', [AssessmentController::class, 'store'])
        ->middleware('permission:assessment.manage');
    Route::post('assessments/bulk', [AssessmentController::class, 'bulkStore'])
        ->middleware('permission:assessment.manage');

    Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])
        ->whereNumber('assessment')
        ->middleware('permission:assessment.view');
    Route::post('assessments/{assessment}/submit', [AssessmentController::class, 'submit'])
        ->whereNumber('assessment')
        ->middleware('permission:assessment.evaluate');
    Route::post('assessments/{assessment}/approve', [AssessmentController::class, 'approve'])
        ->whereNumber('assessment')
        ->middleware('permission:assessment.approve');
    Route::post('assessments/{assessment}/reject', [AssessmentController::class, 'reject'])
        ->whereNumber('assessment')
        ->middleware('permission:assessment.approve');
    Route::get('assessments/{assessment}/gaps', [AssessmentController::class, 'gaps'])
        ->whereNumber('assessment')
        ->middleware('permission:assessment.view');
    Route::post('assessments/{assessment}/actions', [AssessmentController::class, 'storeAction'])
        ->whereNumber('assessment')
        ->middleware('permission:assessment.manage');

    Route::get('categories', [AssessmentController::class, 'categories'])
        ->middleware('permission:assessment.manage');
    Route::post('categories', [AssessmentController::class, 'storeCategory'])
        ->middleware('permission:assessment.manage');

    Route::post('questions', [AssessmentController::class, 'storeQuestion'])
        ->middleware('permission:assessment.manage');
    Route::delete('questions/{question}', [AssessmentController::class, 'destroyQuestion'])
        ->whereNumber('question')
        ->middleware('permission:assessment.manage');


    //CRUD POST
    Route::post('posts', [AssessmentController::class, 'storePost'])
        ->middleware('permission:assessment.manage');
    Route::put('posts/{post}', [AssessmentController::class, 'updatePost'])
        ->whereNumber('post')
        ->middleware('permission:assessment.manage');
    Route::delete('posts/{post}', [AssessmentController::class, 'destroyPost'])
        ->whereNumber('post')
        ->middleware('permission:assessment.manage');

    Route::get('suggest-post', [AssessmentController::class, 'suggestPost'])
        ->middleware('permission:assessment.manage');


    Route::post('methods', [AssessmentController::class, 'storeMethod'])
        ->middleware('permission:assessment.manage');
    Route::put('methods/{method}', [AssessmentController::class, 'updateMethod'])
        ->whereNumber('method')
        ->middleware('permission:assessment.manage');
    Route::delete('methods/{method}', [AssessmentController::class, 'destroyMethod'])
        ->whereNumber('method')
        ->middleware('permission:assessment.manage');

    Route::get('employees/{user}/report', [AssessmentController::class, 'employeeReport'])
        ->whereNumber('user')
        ->middleware('auth:sanctum');

    //auto assessment
    Route::post('/', [AssessmentPeriodController::class, 'store'])->name('store');
    Route::post('/{period}/generate', [AssessmentPeriodController::class, 'generate'])->name('generate');
    Route::get('/{period}', [AssessmentPeriodController::class, 'show'])->name('show');

    Route::get('auto-assign/preview', [AssessmentAssignmentController::class, 'preview'])
        ->middleware('permission:assessment.manage');
    Route::post('auto-assign/execute', [AssessmentAssignmentController::class, 'execute'])
        ->middleware('permission:assessment.manage');

    Route::post('/periods/{period}/auto-assign', [AssessmentController::class, 'autoAssign'])
        ->middleware('permission:assessment.manage')
        ->name('periods.auto-assign');

});
