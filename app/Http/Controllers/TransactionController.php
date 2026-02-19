<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class TransactionController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | SHOW SINGLE TRANSACTION
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        try {

            $transaction = Transaction::with([
                'bankAccount',
                'counterpartyBankAccount',
                'attachments',
                'creator'
            ])->find($id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction retrieved successfully',
                'data' => $transaction
            ]);

        } catch (Exception $e) {

            Log::error('Transaction Show Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while retrieving transaction',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LIST TRANSACTIONS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        try {

            $query = Transaction::with([
                'bankAccount',
                'counterpartyBankAccount',
                'creator'
            ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('voucher_no', 'like', "%{$search}%")
                      ->orWhere('transaction_reference', 'like', "%{$search}%")
                      ->orWhere('document_reference', 'like', "%{$search}%");
                });
            }

            $transactions = $query->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions
            ]);

        } catch (Exception $e) {

            Log::error('Transaction Index Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE TRANSACTION
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        try {

            return DB::transaction(function () use ($request) {

                $validated = $request->validate([
                    'voucher_image'               => ['required', 'file', 'mimes:jpg,jpeg,png,pdf'],
                    'voucher_date'                => ['required', 'date'],
                    'trans_type'                  => ['required', 'string'],
                    'transaction_reference'       => ['nullable', 'string'],
                    'supporting_document'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf'],
                    'bank_account_id'             => ['required', 'exists:bank_accounts,id'],
                    'counterparty_bank_account_id'=> ['nullable', 'exists:bank_accounts,id'],
                    'particulars'                 => ['required', 'string'],
                    'deposit_amount'              => ['nullable', 'numeric'],
                    'withdrawal_amount'           => ['nullable', 'numeric'],
                ]);

                $voucherFile = $request->file('voucher_image');
                $voucherNo = pathinfo($voucherFile->getClientOriginalName(), PATHINFO_FILENAME);

                /* TYPE LOGIC */
                switch ($validated['trans_type']) {

                    case 'BANK_DEPOSIT':
                    case 'CASH_DEPOSIT':
                    case 'CHEQUE_DEPOSIT':

                        if (empty($validated['deposit_amount']) || $validated['deposit_amount'] <= 0) {
                            throw ValidationException::withMessages([
                                'deposit_amount' => ['Deposit amount must be greater than 0']
                            ]);
                        }

                        $validated['withdrawal_amount'] = 0;
                        break;

                    case 'BANK_TRANSFER':

                        if (empty($validated['withdrawal_amount']) || $validated['withdrawal_amount'] <= 0) {
                            throw ValidationException::withMessages([
                                'withdrawal_amount' => ['Transfer amount must be greater than 0']
                            ]);
                        }

                        if (empty($validated['counterparty_bank_account_id'])) {
                            throw ValidationException::withMessages([
                                'counterparty_bank_account_id' => ['Counterparty bank account is required']
                            ]);
                        }

                        if ($validated['bank_account_id'] == $validated['counterparty_bank_account_id']) {
                            throw ValidationException::withMessages([
                                'counterparty_bank_account_id' => ['Source and destination accounts cannot be the same']
                            ]);
                        }

                        $validated['deposit_amount'] = 0;
                        break;

                    default:
                        throw ValidationException::withMessages([
                            'trans_type' => ['Invalid transaction type']
                        ]);
                }

                /* BALANCE CHECK */
                $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);

                $currentBalance = Transaction::where('bank_account_id', $bankAccount->id)
                    ->sum(DB::raw('deposit_amount - withdrawal_amount'));

                $currentBalance += $bankAccount->opening_balance;

                if (!empty($validated['withdrawal_amount']) &&
                    $validated['withdrawal_amount'] > $currentBalance) {

                    throw ValidationException::withMessages([
                        'withdrawal_amount' => [
                            'Insufficient balance. Available: ' . $currentBalance
                        ]
                    ]);
                }

                /* CREATE TRANSACTION */
                $transaction = Transaction::create([
                    'voucher_no' => $voucherNo,
                    'voucher_date' => $validated['voucher_date'],
                    'trans_type' => $validated['trans_type'],
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'document_reference' => null,
                    'bank_account_id' => $validated['bank_account_id'],
                    'counterparty_bank_account_id' => $validated['counterparty_bank_account_id'] ?? null,
                    'particulars' => $validated['particulars'],
                    'deposit_amount' => $validated['deposit_amount'] ?? 0,
                    'withdrawal_amount' => $validated['withdrawal_amount'] ?? 0,
                    'created_by' => auth()->id(),
                ]);

                /* STORE VOUCHER */
                $voucherPath = $voucherFile->store(
                    'transactions/' . $transaction->id,
                    'public'
                );

                $transaction->attachments()->create([
                    'attachment_type' => 'VOUCHER',
                    'file_name' => $voucherFile->getClientOriginalName(),
                    'file_path' => $voucherPath,
                    'file_type' => $voucherFile->getClientMimeType(),
                    'uploaded_at' => now()
                ]);

                /* SUPPORTING FILE */
                if ($request->hasFile('supporting_document')) {

                    $supportingFile = $request->file('supporting_document');

                    $supportingPath = $supportingFile->store(
                        'transactions/' . $transaction->id,
                        'public'
                    );

                    $transaction->attachments()->create([
                        'attachment_type' => 'SUPPORTING',
                        'file_name' => $supportingFile->getClientOriginalName(),
                        'file_path' => $supportingPath,
                        'file_type' => $supportingFile->getClientMimeType(),
                        'uploaded_at' => now()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction created successfully',
                    'data' => $transaction->load('attachments')
                ], 201);

            });

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {

            Log::error('Transaction Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
