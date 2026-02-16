<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankTransactionAttachment;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BankTransactionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX - List transactions per bank account
    |--------------------------------------------------------------------------
    */
    public function index(Request $request, $bankAccountId)
    {
        try {

            $bankAccount = BankAccount::find($bankAccountId);

            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found',
                    'data' => null
                ], 404);
            }

            $query = BankTransaction::with(['owner', 'attachments'])
                ->where('bank_account_id', $bankAccountId)
                ->where('status', 'active');

            /*
            |--------------------------------------------------------------------------
            | DATE FILTER
            |--------------------------------------------------------------------------
            */

            if ($request->filled('from')) {
                $query->whereDate('transaction_date', '>=', $request->from);
            }

            if ($request->filled('to')) {
                $query->whereDate('transaction_date', '<=', $request->to);
            }

            $query->orderBy('transaction_date', 'asc')
                ->orderBy('id', 'asc');

            $perPage = $request->get('per_page', 10);

            $transactions = $query->paginate($perPage);

            /*
            |--------------------------------------------------------------------------
            | RUNNING BALANCE
            |--------------------------------------------------------------------------
            */

            $runningBalance = 0;

            $transactions->getCollection()->transform(function ($transaction) use (&$runningBalance) {

                $runningBalance += $transaction->deposit;
                $runningBalance -= $transaction->withdraw;

                $transaction->computed_balance = $runningBalance;

                return $transaction;
            });

            return response()->json([
                'success' => true,
                'message' => 'Ledger retrieved successfully',
                'data' => [
                    'bank_account' => $bankAccount,
                    'ledger' => $transactions,
                    'final_balance' => $runningBalance
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }




    /*
    |--------------------------------------------------------------------------
    | STORE - Create transaction under specific bank account
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, $bankAccountId)
    {
        try {

            return DB::transaction(function () use ($request, $bankAccountId) {

                $bankAccount = BankAccount::find($bankAccountId);

                if (!$bankAccount || $bankAccount->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or inactive bank account',
                        'data' => null
                    ], 400);
                }

                $validated = $request->validate([
                    'owner_id' => ['required', 'exists:owners,id'],
                    'transaction_date' => ['required', 'date'],
                    'transaction_type' => ['required', 'in:cash,cheque'],
                    'particulars' => ['nullable', 'string'],
                    'deposit' => ['nullable', 'numeric', 'min:0'],
                    'withdraw' => ['nullable', 'numeric', 'min:0'],
                    'attachments' => ['nullable', 'array', 'max:2'],
                    'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
                    'person_in_charge' => ['nullable', 'string'],
                ]);

                $deposit = isset($validated['deposit']) ? (float)$validated['deposit'] : 0;
                $withdraw = isset($validated['withdraw']) ? (float)$validated['withdraw'] : 0;

                if ($deposit > 0 && $withdraw > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot deposit and withdraw at the same time',
                        'data' => null
                    ], 422);
                }

                if ($deposit == 0 && $withdraw == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Either deposit or withdrawal must be greater than zero',
                        'data' => null
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | CHEQUE / CASH ATTACHMENT RULES
                |--------------------------------------------------------------------------
                */

                if ($validated['transaction_type'] === 'cheque') {

                    if (!$request->hasFile('attachments')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cheque transactions require attachment images',
                            'data' => null
                        ], 422);
                    }

                }

                if ($validated['transaction_type'] === 'cash' && $request->hasFile('attachments')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cash transactions should not have attachments',
                        'data' => null
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Reference Number
                |--------------------------------------------------------------------------
                */

                $prefix = $validated['transaction_type'] === 'cash' ? 'CSH' : 'CHK';

                $year = Carbon::parse($validated['transaction_date'])->format('y');
                $fullYear = Carbon::parse($validated['transaction_date'])->format('Y');

                $lastTransaction = BankTransaction::where('transaction_type', $validated['transaction_type'])
                    ->whereYear('transaction_date', $fullYear)
                    ->orderByDesc('id')
                    ->first();

                $newSequence = $lastTransaction
                    ? ((int) substr($lastTransaction->reference_number, -6)) + 1
                    : 1;

                $sequence = str_pad($newSequence, 6, '0', STR_PAD_LEFT);
                $referenceNumber = "{$prefix}-{$year}-{$sequence}";

                $transaction = BankTransaction::create([
                    'bank_account_id' => $bankAccountId,
                    'owner_id' => $validated['owner_id'],
                    'transaction_date' => $validated['transaction_date'],
                    'reference_number' => $referenceNumber,
                    'transaction_type' => $validated['transaction_type'],
                    'particulars' => $validated['particulars'] ?? null,
                    'deposit' => $deposit,
                    'withdraw' => $withdraw,
                    'status' => 'active',
                ]);

                /*
                |--------------------------------------------------------------------------
                | SAVE ATTACHMENTS (CHEQUE ONLY)
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('attachments')) {

                    foreach ($request->file('attachments') as $file) {

                        $path = $file->store('transaction_attachments', 'public');

                        BankTransactionAttachment::create([
                            'bank_transaction_id' => $transaction->id,
                            'owner_id' => $validated['owner_id'],
                            'file_path' => $path,
                            'person_in_charge' => $validated['person_in_charge'] ?? null,
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction created successfully',
                    'data' => $transaction->load('attachments')
                ], 201);
            });

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $bankAccountId, $transactionId)
    {
        try {

            return DB::transaction(function () use ($request, $bankAccountId, $transactionId) {

                $transaction = BankTransaction::with('attachments')->find($transactionId);

                if (!$transaction || $transaction->bank_account_id != $bankAccountId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaction not found under this bank account',
                        'data' => null
                    ], 404);
                }

                if ($transaction->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update archived transaction',
                        'data' => null
                    ], 400);
                }

                $validated = $request->validate([
                    'transaction_date' => ['required', 'date'],
                    'transaction_type' => ['required', 'in:cash,cheque'],
                    'particulars' => ['nullable', 'string'],
                    'deposit' => ['nullable', 'numeric', 'min:0'],
                    'withdraw' => ['nullable', 'numeric', 'min:0'],
                    'attachments' => ['nullable', 'array', 'max:2'],
                    'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
                    'person_in_charge' => ['nullable', 'string'],
                ]);

                $deposit = isset($validated['deposit']) ? (float)$validated['deposit'] : 0;
                $withdraw = isset($validated['withdraw']) ? (float)$validated['withdraw'] : 0;

                if ($deposit > 0 && $withdraw > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot deposit and withdraw at the same time',
                        'data' => null
                    ], 422);
                }

                if ($deposit == 0 && $withdraw == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Either deposit or withdrawal must be greater than zero',
                        'data' => null
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | HANDLE TRANSACTION TYPE CHANGE
                |--------------------------------------------------------------------------
                */

                if ($validated['transaction_type'] === 'cash') {

                    // Cash should not have attachments
                    if ($request->hasFile('attachments')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cash transactions should not have attachments',
                            'data' => null
                        ], 422);
                    }

                    // Delete existing attachments if switching from cheque → cash
                    foreach ($transaction->attachments as $attachment) {
                        \Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    }
                }

                if ($validated['transaction_type'] === 'cheque') {

                    // Must have attachments
                    if (!$request->hasFile('attachments') && $transaction->attachments->isEmpty()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cheque transactions require attachment images',
                            'data' => null
                        ], 422);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | UPDATE TRANSACTION
                |--------------------------------------------------------------------------
                */

                $transaction->update([
                    'transaction_date' => $validated['transaction_date'],
                    'transaction_type' => $validated['transaction_type'],
                    'particulars' => $validated['particulars'] ?? null,
                    'deposit' => $deposit,
                    'withdraw' => $withdraw,
                ]);

                /*
                |--------------------------------------------------------------------------
                | REPLACE ATTACHMENTS IF PROVIDED
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('attachments')) {

                    // Delete old attachments first
                    foreach ($transaction->attachments as $attachment) {
                        \Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    }

                    // Save new attachments
                    foreach ($request->file('attachments') as $file) {

                        $path = $file->store('transaction_attachments', 'public');

                        BankTransactionAttachment::create([
                            'bank_transaction_id' => $transaction->id,
                            'owner_id' => $transaction->owner_id,
                            'file_path' => $path,
                            'person_in_charge' => $validated['person_in_charge'] ?? null,
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction updated successfully',
                    'data' => $transaction->load('attachments')
                ], 200);

            });

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ARCHIVE
    |--------------------------------------------------------------------------
    */
    public function archive($bankAccountId, $transactionId)
    {
        $transaction = BankTransaction::find($transactionId);

        if (!$transaction || $transaction->bank_account_id != $bankAccountId) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found under this bank account',
                'data' => null
            ], 404);
        }

        if ($transaction->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Transaction already archived',
                'data' => null
            ], 400);
        }

        $transaction->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction archived successfully',
            'data' => $transaction
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RESTORE
    |--------------------------------------------------------------------------
    */
    public function restore($bankAccountId, $transactionId)
    {
        $transaction = BankTransaction::find($transactionId);

        if (!$transaction || $transaction->bank_account_id != $bankAccountId) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found under this bank account',
                'data' => null
            ], 404);
        }

        if ($transaction->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Transaction already active',
                'data' => null
            ], 400);
        }

        $transaction->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction restored successfully',
            'data' => $transaction
        ]);
    }
}
