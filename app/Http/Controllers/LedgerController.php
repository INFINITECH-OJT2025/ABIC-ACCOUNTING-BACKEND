<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use App\Models\Owner;
use App\Models\Transaction;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    /* =====================================================
    | ACCOUNT LEVEL (GL) LEDGER
    ===================================================== */

    public function index(Request $request, $accountId)
    {
        $account = ChartOfAccount::find($accountId);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $query = GeneralLedgerEntry::with([
            'transaction.attachments',
            'transaction.instruments'
        ])
        ->where('account_id', $accountId)
        ->orderBy('transaction_id');

        if ($request->filled('from_date')) {
            $query->whereHas('transaction', function ($q) use ($request) {
                $q->whereDate('voucher_date', '>=', $request->from_date);
            });
        }

        if ($request->filled('to_date')) {
            $query->whereHas('transaction', function ($q) use ($request) {
                $q->whereDate('voucher_date', '<=', $request->to_date);
            });
        }

        $entries = $query->get();

        $openingBalance = 0;

        if ($request->filled('from_date')) {

            $previousEntries = GeneralLedgerEntry::where('account_id', $accountId)
                ->whereHas('transaction', function ($q) use ($request) {
                    $q->whereDate('voucher_date', '<', $request->from_date);
                })
                ->get();

            foreach ($previousEntries as $entry) {
                $openingBalance += $entry->debit;
                $openingBalance -= $entry->credit;
            }
        }

        $runningBalance = $openingBalance;

        $ledger = $entries->map(function ($entry) use (&$runningBalance) {

            $runningBalance += $entry->debit;
            $runningBalance -= $entry->credit;

            $transaction = $entry->transaction;

            return [
                'voucher_date' => $transaction->voucher_date,
                'voucher_no'   => $transaction->voucher_no,
                'particulars'  => $transaction->particulars,

                'debit'  => $entry->debit,
                'credit' => $entry->credit,
                'running_balance' => $runningBalance,

                'instruments' => $transaction->instruments->map(function ($instrument) {
                    return [
                        'instrument_type' => $instrument->instrument_type,
                        'instrument_no'   => $instrument->instrument_no,
                    ];
                }),

                'attachments' => $transaction->attachments->map(function ($attachment) {
                    return [
                        'attachment_type' => $attachment->attachment_type,
                        'file_name'       => $attachment->file_name,
                        'file_url'        => asset('storage/'.$attachment->file_path),
                        'file_type'       => $attachment->file_type,
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'account' => $account->account_name,
            'opening_balance' => $openingBalance,
            'data' => $ledger
        ]);
    }

    /* =====================================================
    | OWNER BANK LEDGER (OPERATIONAL VIEW)
    ===================================================== */

    public function ownerBankLedger($ownerId)
    {
        $owner = Owner::with('bankAccounts')->find($ownerId);

        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Owner not found'
            ], 404);
        }

        $result = [];

        foreach ($owner->bankAccounts as $bankAccount) {

            $transactions = Transaction::with(['attachments','instruments'])
                ->where(function ($q) use ($owner) {
                    $q->where('from_owner_id', $owner->id)
                      ->orWhere('to_owner_id', $owner->id);
                })
                ->orderBy('voucher_date')
                ->get();

            $runningBalance = $bankAccount->opening_balance;
            $ledger = [];

            foreach ($transactions as $transaction) {

                if ($transaction->from_owner_id == $owner->id) {
                    $withdrawal = $transaction->amount;
                    $deposit = 0;
                    $runningBalance -= $withdrawal;
                } else {
                    $deposit = $transaction->amount;
                    $withdrawal = 0;
                    $runningBalance += $deposit;
                }

                $ledger[] = [
                    'voucher_date' => $transaction->voucher_date,
                    'voucher_no'   => $transaction->voucher_no,
                    'particulars'  => $transaction->particulars,
                    'owner_id'     => $transaction->from_owner_id == $owner->id ? $transaction->to_owner_id : $transaction->from_owner_id,
                    'deposit'      => $deposit,
                    'withdrawal'   => $withdrawal,
                    'running_balance' => $runningBalance,

                    'instruments' => $transaction->instruments->map(function ($instrument) {
                        return [
                            'instrument_type' => $instrument->instrument_type,
                            'instrument_no'   => $instrument->instrument_no,
                        ];
                    }),

                    'attachments' => $transaction->attachments->map(function ($attachment) {
                        return [
                            'attachment_type' => $attachment->attachment_type,
                            'file_name'       => $attachment->file_name,
                            'file_url'        => asset('storage/'.$attachment->file_path),
                            'file_type'       => $attachment->file_type,
                        ];
                    })
                ];
            }

            $result[] = [
                'bank_account_id' => $bankAccount->id,
                'account_name'    => $bankAccount->account_name,
                'account_number'  => $bankAccount->account_number,
                'opening_balance' => $bankAccount->opening_balance,
                'data'            => $ledger
            ];
        }

        return response()->json([
            'success' => true,
            'owner' => $owner->name,
            'data' => $result
        ]);
    }
}