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
use App\Http\Controllers\TransactionAttachmentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\TrialBalanceController;
// use App\Http\Controllers\OwnerLedgerController;
use App\Http\Controllers\BankAccountLedgerController;




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



Route::middleware(['auth:sanctum'])->prefix('transactions')->group(function () {
    // Create Transaction
    Route::post('/', [TransactionController::class, 'store']);
    // (Optional if you add later)
    Route::get('/', [TransactionController::class, 'index']);
    Route::get('/{id}', [TransactionController::class, 'show']);
    Route::put('/{id}', [TransactionController::class, 'update']);
    Route::patch('/{id}/inactive', [TransactionController::class, 'inactive']);
    Route::patch('/{id}/restore', [TransactionController::class, 'restore']);
});

Route::middleware(['auth:sanctum'])->prefix('transactions')->group(function () {

    // List instruments of a transaction
    Route::get('{transactionId}/instruments', 
        [TransactionInstrumentController::class, 'index']);

    // Add instrument to transaction
    Route::post('{transactionId}/instruments', 
        [TransactionInstrumentController::class, 'store']);
});

// Delete instrument
Route::middleware(['auth:sanctum'])->delete(
    'transaction-instruments/{id}',
    [TransactionInstrumentController::class, 'destroy']
);

Route::middleware(['auth:sanctum'])->prefix('transactions')->group(function () {

    // List attachments of a transaction
    Route::get('{transactionId}/attachments', 
        [TransactionAttachmentController::class, 'index']);

    // Upload attachment
    Route::post('{transactionId}/attachments', 
        [TransactionAttachmentController::class, 'store']);
});

// Delete attachment
Route::middleware(['auth:sanctum'])->delete(
    'transaction-attachments/{id}',
    [TransactionAttachmentController::class, 'destroy']
);



Route::middleware(['auth:sanctum'])
    ->prefix('transaction-attachments')
    ->group(function () {

        Route::get('/transaction/{transactionId}', [TransactionAttachmentController::class, 'index']);
        Route::post('/', [TransactionAttachmentController::class, 'store']);
        Route::delete('/{id}', [TransactionAttachmentController::class, 'destroy']);

});



Route::middleware(['auth:sanctum'])
    ->prefix('chart-of-accounts')
    ->group(function () {

        Route::get('/', [ChartOfAccountController::class, 'index']);
        Route::post('/', [ChartOfAccountController::class, 'store']);
        Route::get('/{id}', [ChartOfAccountController::class, 'show']);
        Route::put('/{id}', [ChartOfAccountController::class, 'update']);
        Route::patch('/deactivate/{id}', [ChartOfAccountController::class, 'deactivate']);
        Route::patch('/activate/{id}', [ChartOfAccountController::class, 'activate']);
});

