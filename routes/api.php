<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\BankTransactionAttachmentController;
use App\Http\Controllers\BankContactController;
use App\Http\Controllers\BankContactChannelController;



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
    Route::patch('/bank/{id}/archive', [BankController::class, 'inactive']);
    Route::patch('/bank/{id}/restore', [BankController::class, 'restore']);
    Route::get('/banks/select', [BankController::class, 'selectBank']);
});

Route::prefix('bank-contact-channels')->group(function () {
    Route::post('/', [BankContactChannelController::class, 'store']);
    Route::get('/contact/{contactId}', [BankContactChannelController::class, 'index']);
    Route::get('/{id}', [BankContactChannelController::class, 'show']);
    Route::put('/{id}', [BankContactChannelController::class, 'update']);
    Route::delete('/{id}', [BankContactChannelController::class, 'destroy']);
});

Route::prefix('bank-contacts')->group(function () {
    Route::post('/', [BankContactController::class, 'store']);
    Route::get('/', [BankContactController::class, 'index']);
    Route::get('/{id}', [BankContactController::class, 'show']);
    Route::put('/{id}', [BankContactController::class, 'update']);
    Route::delete('/{id}', [BankContactController::class, 'destroy']);
});


Route::middleware(['auth:sanctum'])->prefix('bank-accounts')->group(function () {
    Route::post('/', [BankAccountController::class, 'store']);
    Route::get('/', [BankAccountController::class, 'index']);
    Route::get('/{id}', [BankAccountController::class, 'show']);
    Route::put('/{id}', [BankAccountController::class, 'update']);
    Route::patch('/archive/{id}', [BankAccountController::class, 'archive']);
    Route::patch('/restore/{id}', [BankAccountController::class, 'restore']);
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



