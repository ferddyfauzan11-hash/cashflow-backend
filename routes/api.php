<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LoanInstallmentController;
use App\Http\Controllers\Api\PocketAllocationController;
use App\Http\Controllers\Api\PocketController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Auth Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Sanctum)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // Categories
        Route::apiResource('categories', CategoryController::class)->except(['show']);

        // Pockets
        Route::apiResource('pockets', PocketController::class)->except(['show']);

        // Pocket Allocations (transfer)
        Route::get('/pocket-allocations', [PocketAllocationController::class, 'index']);
        Route::post('/pocket-allocations', [PocketAllocationController::class, 'store']);
        Route::delete('/pocket-allocations/{id}', [PocketAllocationController::class, 'destroy']);

        // Transactions
        Route::apiResource('transactions', TransactionController::class)->except(['show']);

        // Loans
        Route::apiResource('loans', LoanController::class);
        Route::get('/loans/{id}/installments', [LoanInstallmentController::class, 'index']);
        Route::post('/loans/{id}/installments', [LoanInstallmentController::class, 'store']);

        // Dashboard
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Analytics
        Route::get('/analytics/monthly', [AnalyticsController::class, 'monthly']);
        Route::get('/analytics/yearly', [AnalyticsController::class, 'yearly']);
        Route::get('/analytics/pocket/{id}', [AnalyticsController::class, 'pocket']);

        // Sync
        Route::post('/sync/push', [SyncController::class, 'push']);
        Route::get('/sync/pull', [SyncController::class, 'pull']);
    });
});
