<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Exception;

class TransactionController extends Controller
{

    public function store(Request $request)
    {
        try {

            return DB::transaction(function () use ($request) {

                /* =========================================================
                | VALIDATION
                ========================================================= */

                $validated = $request->validate([
                    'voucher_file'      => ['required','file','mimes:jpg,jpeg,png,pdf'],
                    'voucher_date'      => ['required','date'],
                    'trans_method'      => ['required','in:DEPOSIT,WITHDRAW'],
                    'trans_type'        => ['required','in:CASH,CHEQUE,DEPOSIT_SLIP,INTERNAL'],
                    'from_owner_id'     => ['required','exists:owners,id'],
                    'to_owner_id'       => ['required','exists:owners,id','different:from_owner_id'],
                    'amount'            => ['required','numeric','min:0.01'],
                    'fund_reference'    => ['nullable','string'],
                    'particulars'       => ['required','string'],
                    'transfer_group_id' => ['nullable','string'],
                    'person_in_charge'  => ['nullable','string'],
                    'instrument_no'     => ['required','string'],
                    'supporting_file'   => ['nullable','file','mimes:jpg,jpeg,png,pdf'],
                ]);

                /* =========================================================
                | MASTER RULE: ONLY MAIN CAN INITIATE
                ========================================================= */

                $fromOwner = \App\Models\Owner::findOrFail($validated['from_owner_id']);

                if ($fromOwner->owner_type !== 'MAIN') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'from_owner_id' => ['Only MAIN owner can initiate transactions']
                    ]);
                }

                /* =========================================================
                | USE FILE NAME AS VOUCHER NO
                ========================================================= */

                $voucherFile = $request->file('voucher_file');

                $voucherNo = pathinfo(
                    $voucherFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                // Prevent duplicate voucher
                if (\App\Models\Transaction::where('voucher_no', $voucherNo)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'voucher_file' => ['Voucher already exists']
                    ]);
                }

                /* =========================================================
                | CREATE TRANSACTION (BUSINESS EVENT)
                ========================================================= */

                $transaction = Transaction::create([
                    'voucher_no'        => $voucherNo,
                    'voucher_date'      => $validated['voucher_date'],
                    'trans_method'      => $validated['trans_method'],
                    'trans_type'        => $validated['trans_type'],
                    'from_owner_id'     => $validated['from_owner_id'],
                    'to_owner_id'       => $validated['to_owner_id'],
                    'amount'            => $validated['amount'],
                    'fund_reference'    => $validated['fund_reference'] ?? null,
                    'particulars'       => $validated['particulars'],
                    'transfer_group_id' => $validated['transfer_group_id'] ?? null,
                    'person_in_charge'  => $validated['person_in_charge'] ?? null,
                    'created_by'        => auth()->id(),
                    'status'            => 'ACTIVE'
                ]);

                /* =========================================================
                | CREATE INSTRUMENT RECORD
                ========================================================= */

                $transaction->instruments()->create([
                    'instrument_type' => $validated['trans_type'],
                    'instrument_no'   => $validated['instrument_no'],
                ]);

                /* =========================================================
                | STORE VOUCHER ATTACHMENT (REQUIRED)
                ========================================================= */

                $voucherPath = $voucherFile->store(
                    'transactions/'.$transaction->id,
                    'public'
                );

                $transaction->attachments()->create([
                    'attachment_type' => 'VOUCHER',
                    'file_name'       => $voucherFile->getClientOriginalName(),
                    'file_path'       => $voucherPath,
                    'file_type'       => $voucherFile->getClientMimeType(),
                ]);

                /* =========================================================
                | STORE SUPPORTING (OPTIONAL)
                ========================================================= */

                if ($request->hasFile('supporting_file')) {

                    $supportingFile = $request->file('supporting_file');

                    $supportingPath = $supportingFile->store(
                        'transactions/'.$transaction->id,
                        'public'
                    );

                    $transaction->attachments()->create([
                        'attachment_type' => 'SUPPORTING',
                        'file_name'       => $supportingFile->getClientOriginalName(),
                        'file_path'       => $supportingPath,
                        'file_type'       => $supportingFile->getClientMimeType(),
                    ]);
                }

                /* =========================================================
                | GENERAL LEDGER POSTING (DOUBLE ENTRY)
                ========================================================= */

                $fromOwner = \App\Models\Owner::with('chartOfAccount')
                                ->findOrFail($validated['from_owner_id']);

                $toOwner = \App\Models\Owner::with('chartOfAccount')
                                ->findOrFail($validated['to_owner_id']);

                $fromAccount = $fromOwner->chartOfAccount;
                $toAccount   = $toOwner->chartOfAccount;

                if (!$fromAccount || !$toAccount) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'owners' => ['Linked COA account missing for one of the owners']
                    ]);
                }

                $amount = $validated['amount'];

                // Debit receiving owner
                GeneralLedgerEntry::create([
                    'transaction_id'   => $transaction->id,
                    'account_id'       => $toAccount->id,
                    'debit'            => $amount,
                    'credit'           => 0,
                    'entry_description'=> 'Owner Allocation'
                ]);

                // Credit MAIN
                GeneralLedgerEntry::create([
                    'transaction_id'   => $transaction->id,
                    'account_id'       => $fromAccount->id,
                    'debit'            => 0,
                    'credit'           => $amount,
                    'entry_description'=> 'Owner Funding'
                ]);

                /* =========================================================
                | SUCCESS RESPONSE
                ========================================================= */

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction created successfully',
                    'data'    => $transaction->load(['instruments','attachments'])
                ], 201);

            });

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Transaction failed',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     try {

    //         return DB::transaction(function () use ($request) {

    //             $validated = $request->validate([
    //                 'voucher_no'        => ['required','string','unique:transactions,voucher_no'],
    //                 'voucher_date'      => ['required','date'],
    //                 'trans_method'      => ['required','in:DEPOSIT,WITHDRAW'],
    //                 'trans_type'        => ['required','in:CASH,CHEQUE,DEPOSIT_SLIP,INTERNAL'],
    //                 'from_owner_id'     => ['required','exists:owners,id'],
    //                 'to_owner_id'       => ['required','exists:owners,id','different:from_owner_id'],
    //                 'amount'            => ['required','numeric','min:0.01'],
    //                 'fund_reference'    => ['nullable','string'],
    //                 'particulars'       => ['required','string'],
    //                 'transfer_group_id' => ['nullable','string'],
    //                 'person_in_charge'  => ['nullable','string'],

    //                 // Instrument (REQUIRED)
    //                 'instrument_no'     => ['nullable','string'],
    //                 'voucher_file'      => ['nullable','file','mimes:jpg,jpeg,png,pdf']
    //             ]);

    //             /* =========================================================
    //             | MASTER RULE: ONLY MAIN CAN INITIATE
    //             ========================================================= */

    //             $fromOwner = \App\Models\Owner::findOrFail($validated['from_owner_id']);

    //             if ($fromOwner->owner_type !== 'MAIN') {
    //                 throw \Illuminate\Validation\ValidationException::withMessages([
    //                     'from_owner_id' => ['Only MAIN owner can initiate transactions']
    //                 ]);
    //             }

    //             /* =========================================================
    //             | CREATE SINGLE TRANSACTION ROW (BUSINESS EVENT ONLY)
    //             ========================================================= */

    //             $transaction = Transaction::create([
    //                 'voucher_no'        => $validated['voucher_no'],
    //                 'voucher_date'      => $validated['voucher_date'],
    //                 'trans_method'      => $validated['trans_method'],
    //                 'trans_type'        => $validated['trans_type'],
    //                 'from_owner_id'     => $validated['from_owner_id'],
    //                 'to_owner_id'       => $validated['to_owner_id'],
    //                 'amount'            => $validated['amount'],
    //                 'fund_reference'    => $validated['fund_reference'] ?? null,
    //                 'particulars'       => $validated['particulars'],
    //                 'transfer_group_id' => $validated['transfer_group_id'] ?? null,
    //                 'person_in_charge'  => $validated['person_in_charge'] ?? null,
    //                 'created_by'        => auth()->id(),
    //                 'status'            => 'ACTIVE'
    //             ]);

    //             /* =========================================================
    //             | REQUIRED INSTRUMENT RECORD
    //             ========================================================= */

    //             $transaction->instruments()->create([
    //                 'instrument_type' => $validated['trans_type'],
    //                 'instrument_no'   => $validated['instrument_no'] ?? null
    //             ]);

    //             /* =========================================================
    //             | ATTACHMENT (OPTIONAL)
    //             ========================================================= */

    //             if ($request->hasFile('voucher_file')) {

    //                 $file = $request->file('voucher_file');

    //                 $path = $file->store(
    //                     'transactions/'.$transaction->id,
    //                     'public'
    //                 );

    //                 $transaction->attachments()->create([
    //                     'attachment_type' => 'VOUCHER',
    //                     'file_name'       => $file->getClientOriginalName(),
    //                     'file_path'       => $path,
    //                     'file_type'       => $file->getClientMimeType(),
    //                 ]);
    //             }

    //             /* =========================================================
    //             | GENERAL LEDGER POSTING (DOUBLE ENTRY ONLY)
    //             ========================================================= */

    //             $fromOwner = \App\Models\Owner::with('chartOfAccount')
    //                             ->findOrFail($validated['from_owner_id']);

    //             $toOwner = \App\Models\Owner::with('chartOfAccount')
    //                             ->findOrFail($validated['to_owner_id']);

    //             $fromAccount = $fromOwner->chartOfAccount;
    //             $toAccount   = $toOwner->chartOfAccount;
                
    //             if (!$fromAccount || !$toAccount) {
    //                 throw \Illuminate\Validation\ValidationException::withMessages([
    //                     'owners' => ['Linked COA account missing for one of the owners']
    //                 ]);
    //             }

    //             $amount = $validated['amount'];

    //             /*
    //             MAIN → OTHER
    //             Debit  : Receiving Owner
    //             Credit : MAIN

    //             ALWAYS 2 entries only.
    //             */

    //             GeneralLedgerEntry::create([
    //                 'transaction_id' => $transaction->id,
    //                 'account_id'     => $toAccount->id,
    //                 'debit'          => $amount,
    //                 'credit'         => 0,
    //                 'entry_description'    => 'Owner Allocation'
    //             ]);

    //             GeneralLedgerEntry::create([
    //                 'transaction_id' => $transaction->id,
    //                 'account_id'     => $fromAccount->id,
    //                 'debit'          => 0,
    //                 'credit'         => $amount,
    //                 'entry_description'    => 'Owner Funding'
    //             ]);

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Transaction created successfully',
    //                 'data'    => $transaction->load(['instruments','attachments'])
    //             ],201);

    //         });

    //     } catch (\Illuminate\Validation\ValidationException $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors'  => $e->errors()
    //         ],422);

    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Transaction failed',
    //             'error'   => config('app.debug') ? $e->getMessage() : null
    //         ],500);
    //     }
    // }
    
}
