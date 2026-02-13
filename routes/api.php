<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\BanksController;


Route::middleware('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::get('/admin-only', function () {
        return response()->json([
            'message' => 'Super Admin Access Granted'
        ]);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/owner', [OwnerController::class, 'createOwner']);
    Route::put('/owner/{id}', [OwnerController::class, 'update']);
    Route::patch('/owner/{id}/archive', [OwnerController::class, 'archive']);
    Route::patch('/owner/{id}/restore', [OwnerController::class, 'restore']);
    Route::get('/owner/{id}', [OwnerController::class, 'show']);
    Route::get('/owner', [OwnerController::class, 'index']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/bank', [BanksController::class, 'createBank']);
    Route::put('/bank/{id}', [BanksController::class, 'updateBankName']);
    Route::get('/bank', [BanksController::class, 'index']);
    Route::get('/bank/{id}', [BanksController::class, 'show']);
    Route::patch('/bank/{id}/archive', [BanksController::class, 'archiveBank']);
    Route::patch('/bank/{id}/restore', [BanksController::class, 'restoreBank']);
});




