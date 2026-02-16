<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountantController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

// Define custom rate limiters
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip())->response(function () {
        return response()->json([
            'success' => false,
            'message' => 'Too many authentication attempts. Please try again later.',
            'errors' => null,
            'retry_after' => 60
        ], 429);
    });
});

RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(60)->by($request->user()->id)
        : Limit::perMinute(20)->by($request->ip());
});

// Public routes with rate limiting
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login', [AuthController::class, 'loginInfo'])->name('login');
});

// Test routes for debugging
Route::get('/test-simple', [AuthController::class, 'testSimple']);
Route::get('/test-auth', [AuthController::class, 'testAuth'])->middleware('auth:sanctum');

// Authenticated routes with standard API rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Employee self-service (role=employee) - MUST be before /employees/{id} so /me is not captured as id
    Route::middleware(['role:employee'])->prefix('employees')->group(function () {
        Route::get('/me', [EmployeeController::class, 'me']);
        Route::put('/me', [EmployeeController::class, 'updateMe']);
    });
    
    // Admin-only routes
    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/admin-only', function () {
            return response()->json([
                'message' => 'Super Admin Access Granted'
            ]);
        });
        
        // Admin account management routes
        Route::prefix('admin/accounts')->group(function () {
            Route::get('/', [AdminController::class, 'index']);
            Route::post('/', [AdminController::class, 'store']);
            Route::post('/promote-from-employee', [AdminController::class, 'promoteFromEmployee']);
            Route::post('/{id}/revert-to-employee', [AdminController::class, 'revertToEmployee']);
            Route::get('/{id}', [AdminController::class, 'show']);
            Route::put('/{id}', [AdminController::class, 'update']);
            Route::delete('/{id}', [AdminController::class, 'destroy']);
        });

        // Employee management routes (super_admin only)
        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/', [EmployeeController::class, 'store']);
            Route::get('/{id}', [EmployeeController::class, 'show']);
            Route::put('/{id}', [EmployeeController::class, 'update']);
        });
    });
    
    // Accountant management routes (admin access)
    Route::middleware(['role:super_admin'])->prefix('accountant')->group(function () {
        Route::get('/', [AccountantController::class, 'index']);
        Route::post('/', [AccountantController::class, 'store']);
        Route::get('/{id}', [AccountantController::class, 'show']);
        Route::put('/{id}', [AccountantController::class, 'update']);
        Route::delete('/{id}', [AccountantController::class, 'destroy']);
        Route::post('/{id}/resend-credentials', [AccountantController::class, 'resendCredentials']);
        Route::post('/{id}/suspend', [AccountantController::class, 'suspend']);
        Route::post('/{id}/unsuspend', [AccountantController::class, 'unsuspend']);
    });
});


