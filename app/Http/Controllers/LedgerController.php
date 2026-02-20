<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request, $accountId)
    {
        $account = ChartOfAccount::find($accountId);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $query = GeneralLedgerEntry::with(['transaction'])
            ->where('account_id', $accountId)
            ->orderBy('transaction_id');

        // Date Filters (based on transaction date)
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

        /* =====================================================
        | OPENING BALANCE (Before from_date)
        ===================================================== */

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

        /* =====================================================
        | RUNNING BALANCE
        ===================================================== */

        $runningBalance = $openingBalance;

        $ledger = $entries->map(function ($entry) use (&$runningBalance) {

            $runningBalance += $entry->debit;
            $runningBalance -= $entry->credit;

            return [
                'voucher_date' => $entry->transaction->voucher_date,
                'voucher_no'   => $entry->transaction->voucher_no,
                'particulars'  => $entry->transaction->particulars,

                'debit'  => $entry->debit,
                'credit' => $entry->credit,

                'running_balance' => $runningBalance,
            ];
        });

        return response()->json([
            'success' => true,
            'account' => $account->account_name,
            'opening_balance' => $openingBalance,
            'data' => $ledger
        ]);
    }
}