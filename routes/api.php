<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\BankTransactionAttachmentController;

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
    Route::patch('/owner/{id}/archive', [OwnerController::class, 'inactive']);
    Route::patch('/owner/{id}/restore', [OwnerController::class, 'restore']);
    Route::get('/owner/{id}', [OwnerController::class, 'show']);
    Route::get('/owner', [OwnerController::class, 'index']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/bank', [BankController::class, 'createBank']);
    Route::put('/bank/{id}', [BankController::class, 'updateBankName']);
    Route::get('/bank', [BankController::class, 'index']);
    Route::get('/bank/{id}', [BankController::class, 'show']);
    Route::patch('/bank/{id}/archive', [BankController::class, 'archiveBank']);
    Route::patch('/bank/{id}/restore', [BankController::class, 'restoreBank']);
    Route::get('/banks/select', [BankController::class, 'selectBank']);

});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/bank-accounts', [BankAccountController::class, 'index']);
    Route::get('/bank-accounts/{id}', [BankAccountController::class, 'show']);
    Route::post('/banks', [BankController::class, 'store']);
    Route::post('/bank-accounts', [BankAccountController::class, 'createBankAccount']);
    Route::put('/bank-accounts/{id}', [BankAccountController::class, 'updateBankAccount']);
    Route::patch('/bank-accounts/{id}/archive', [BankAccountController::class, 'archiveBank']);
    Route::patch('/bank-accounts/{id}/restore', [BankAccountController::class, 'restoreBank']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/ledger/{bankAccountId}', [BankTransactionController::class, 'ledger']);
    Route::post('/bank-transactions', [BankTransactionController::class, 'createTransaction']);
    Route::get('/bank-transactions', [BankTransactionController::class, 'index']);
    Route::put('/bank-transactions/{id}', [BankTransactionController::class, 'updateTransaction']);
    Route::patch('/bank-transactions/{id}/archive', [BankTransactionController::class, 'archiveTransaction']);
    Route::patch('/bank-transactions/{id}/restore', [BankTransactionController::class, 'restoreTransaction']);
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('bank-accounts/{bankAccountId}')->group(function () {

        Route::get('/transactions', [BankTransactionController::class, 'index']);
        Route::post('/transactions', [BankTransactionController::class, 'store']);
        Route::put('/transactions/{transactionId}', [BankTransactionController::class, 'update']);
        Route::patch('/transactions/{transactionId}/archive', [BankTransactionController::class, 'archive']);
        Route::patch('/transactions/{transactionId}/restore', [BankTransactionController::class, 'restore']);
    });
});



