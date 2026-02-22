<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransactionInstrument;
use App\Models\TransactionAttachment;
use App\Models\OwnerLedgerEntry;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class TransactionController extends Controller
{
    /**
     * Create a deposit transaction.
     */
    public function storeDeposit(Request $request)
    {
        return $this->storeTransaction($request, 'DEPOSIT');
    }

    /**
     * Create a withdrawal transaction.
     */
    public function storeWithdrawal(Request $request)
    {
        return $this->storeTransaction($request, 'WITHDRAWAL');
    }

    /**
     * Store a transaction (deposit or withdrawal) with instruments and attachments.
     * 🔥 CRITICAL: Everything wrapped in DB transaction for data integrity.
     */
    protected function storeTransaction(Request $request, string $transMethod)
    {
        // 🔥 8️⃣ Security: Validate user role
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check if user has accountant or super_admin role
        $userRole = $user->role ?? '';
        if (!in_array(strtolower($userRole), ['accountant', 'super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. Only accountants and admins can create transactions.'
            ], 403);
        }

        try {
            // Validate request structure
            $request->validate([
                'transaction' => ['required', 'string'],
                'instruments' => ['nullable', 'string'],
                'attachments' => ['nullable', 'string'],
                'voucher' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:10240'], // 10MB, images and PDFs only
            ]);

            $transactionData = json_decode($request->transaction, true);
            $instrumentsData = $request->filled('instruments') ? json_decode($request->instruments, true) : [];
            $attachmentsData = $request->filled('attachments') ? json_decode($request->attachments, true) : [];

            if (!is_array($transactionData)) {
                throw ValidationException::withMessages([
                    'transaction' => ['Invalid transaction data format']
                ]);
            }

            // 🔥 2️⃣ Comprehensive server-side validation
            $validated = $this->validateTransactionData($transactionData, $transMethod);

            // 🔥 7️⃣ Prevent duplicate submission (check unique voucher_no)
            if (!empty($validated['voucher_no'])) {
                $existingTransaction = Transaction::where('voucher_no', $validated['voucher_no'])->first();
                if ($existingTransaction) {
                    throw ValidationException::withMessages([
                        'voucher_no' => ['Voucher number already exists. Please use a unique voucher number.']
                    ]);
                }
            }

            // 🔥 1️⃣ Wrap EVERYTHING in DB transaction
            $transaction = null;
            $errorOccurred = false;
            $errorMessage = '';

            DB::beginTransaction();
            try {
                // 🔥 9️⃣ Set transaction_category automatically (backend-controlled)
                // 🔥 🔟 Enforce voucher mode logic (backend-controlled)
                $hasVoucher = !empty($validated['voucher_no']);
                $hasVoucherDate = !empty($validated['voucher_date']);
                
                // Enforce: if voucher_no exists, voucher_date must exist
                if ($hasVoucher && !$hasVoucherDate) {
                    throw ValidationException::withMessages([
                        'voucher_date' => ['Voucher date is required when voucher number is provided']
                    ]);
                }

                // 🔥 5️⃣ Create transaction with is_posted = false initially
                $transaction = Transaction::create([
                    'voucher_no' => $validated['voucher_no'] ?? null,
                    'voucher_date' => $validated['voucher_date'] ?? null,
                    'trans_method' => $transMethod,
                    'transaction_category' => $transMethod, // 🔥 9️⃣ Auto-set by backend
                    'trans_type' => $validated['trans_type'],
                    'from_owner_id' => $validated['from_owner_id'],
                    'to_owner_id' => $validated['to_owner_id'],
                    'unit_id' => $validated['unit_id'] ?? null,
                    'amount' => $validated['amount'],
                    'fund_reference' => $validated['fund_reference'] ?? null,
                    'particulars' => $validated['particulars'] ?? null,
                    'transfer_group_id' => $validated['transfer_group_id'] ?? null,
                    'person_in_charge' => $validated['person_in_charge'] ?? null,
                    'status' => 'ACTIVE',
                    'is_posted' => false, // 🔥 5️⃣ Start as unposted
                    'created_by' => $user->id, // 🔥 8️⃣ Get from session, not frontend
                ]);

                // 🔥 3️⃣ Validate and create instruments (do NOT trust frontend)
                if (is_array($instrumentsData) && count($instrumentsData) > 0) {
                    $seenInstrumentNos = [];
                    foreach ($instrumentsData as $inst) {
                        // Validate instrument_type
                        $instrumentType = $inst['instrument_type'] ?? $validated['trans_type'];
                        if (!in_array($instrumentType, ['CASH', 'CHEQUE', 'DEPOSIT_SLIP', 'INTERNAL'])) {
                            throw ValidationException::withMessages([
                                'instruments' => ["Invalid instrument type: {$instrumentType}"]
                            ]);
                        }

                        // Validate instrument_no if provided
                        $instrumentNo = isset($inst['instrument_no']) ? trim($inst['instrument_no']) : null;
                        if ($instrumentNo !== null && $instrumentNo !== '') {
                            // Validate length and format
                            if (strlen($instrumentNo) > 255) {
                                throw ValidationException::withMessages([
                                    'instruments' => ['Instrument number exceeds maximum length']
                                ]);
                            }

                            // Prevent duplicate instrument_no within same transaction
                            if (in_array($instrumentNo, $seenInstrumentNos)) {
                                throw ValidationException::withMessages([
                                    'instruments' => ["Duplicate instrument number: {$instrumentNo}"]
                                ]);
                            }
                            $seenInstrumentNos[] = $instrumentNo;
                        }

                        // Only create if instrument_no is not empty
                        if ($instrumentNo !== null && $instrumentNo !== '') {
                            $transaction->instruments()->create([
                                'instrument_type' => $instrumentType,
                                'instrument_no' => $instrumentNo,
                                'notes' => isset($inst['notes']) ? trim($inst['notes']) : null,
                            ]);
                        }
                    }
                }

                // 🔥 6️⃣ Handle file uploads with proper validation and unique naming
                $basePath = 'transactions/' . $transaction->id;

                // Voucher file
                if ($request->hasFile('voucher')) {
                    $file = $request->file('voucher');
                    
                    // Validate file size (10MB max)
                    if ($file->getSize() > 10 * 1024 * 1024) {
                        throw ValidationException::withMessages([
                            'voucher' => ['Voucher file exceeds maximum size of 10MB']
                        ]);
                    }

                    // Validate mime type (images and PDFs only)
                    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!in_array($file->getMimeType(), $allowedMimes)) {
                        throw ValidationException::withMessages([
                            'voucher' => ['Invalid file type. Only JPEG, PNG, and PDF files are allowed.']
                        ]);
                    }

                    // Generate unique file name (backend-controlled)
                    $extension = $file->getClientOriginalExtension();
                    $uniqueFileName = 'voucher_' . Str::uuid() . '.' . $extension;
                    $path = $file->storeAs($basePath, $uniqueFileName, 'local');

                    $transaction->attachments()->create([
                        'file_name' => $file->getClientOriginalName(), // Keep original name for display
                        'file_type' => $file->getMimeType(),
                        'file_path' => $path, // Store backend-generated path
                    ]);
                }

                // Uploaded files (file_0, file_1, ...)
                $fileIndex = 0;
                while ($request->hasFile("file_{$fileIndex}")) {
                    $file = $request->file("file_{$fileIndex}");
                    
                    // Validate file size
                    if ($file->getSize() > 10 * 1024 * 1024) {
                        throw ValidationException::withMessages([
                            "file_{$fileIndex}" => ['File exceeds maximum size of 10MB']
                        ]);
                    }

                    // Validate mime type
                    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!in_array($file->getMimeType(), $allowedMimes)) {
                        throw ValidationException::withMessages([
                            "file_{$fileIndex}" => ['Invalid file type. Only JPEG, PNG, and PDF files are allowed.']
                        ]);
                    }

                    // Generate unique file name
                    $extension = $file->getClientOriginalExtension();
                    $uniqueFileName = 'attachment_' . Str::uuid() . '.' . $extension;
                    $path = $file->storeAs($basePath, $uniqueFileName, 'local');

                    $transaction->attachments()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_type' => $file->getMimeType(),
                        'file_path' => $path,
                    ]);
                    $fileIndex++;
                }

                // 🔥 4️⃣ Generate owner_ledger_entries (backend-calculated)
                $transaction->load(['instruments', 'unit', 'fromOwner', 'toOwner']);
                $this->createLedgerEntries($transaction);

                // 🔥 5️⃣ Mark transaction as posted only after everything succeeds
                $transaction->update([
                    'is_posted' => true,
                    'posted_at' => now(),
                ]);

                // 🔥 8️⃣ Log activity (audit trail)
                Log::info('Transaction created', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'transaction_id' => $transaction->id,
                    'voucher_no' => $transaction->voucher_no,
                    'trans_method' => $transMethod,
                    'amount' => $transaction->amount,
                ]);

                DB::commit();
            } catch (ValidationException $e) {
                DB::rollBack();
                throw $e;
            } catch (Exception $e) {
                DB::rollBack();
                $errorOccurred = true;
                $errorMessage = $e->getMessage();
                
                // Log error
                Log::error('Transaction creation failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ], 500);
        }

        $transaction->load(['instruments', 'attachments', 'fromOwner', 'toOwner', 'unit']);

        return response()->json([
            'success' => true,
            'message' => $transMethod === 'DEPOSIT' ? 'Deposit created successfully' : 'Withdrawal created successfully',
            'data' => $transaction
        ], 201);
    }

    /**
     * 🔥 4️⃣ Create owner_ledger_entries for a transaction.
     * Backend-calculated running balances - NEVER trust frontend math.
     * 
     * Trust Account Model: Company tracks money it holds for different owners.
     * - DEPOSIT: Both MAIN and CLIENT show deposit (both balances increase)
     * - WITHDRAWAL: Both MAIN and CLIENT show withdrawal (both balances decrease)
     * 
     * This is NOT a transfer model - it's a trust/wallet/escrow model.
     */
    protected function createLedgerEntries(Transaction $transaction): void
    {
        $amount = (float) $transaction->amount;
        $voucherNo = $transaction->voucher_no ?? '—';
        $voucherDate = $transaction->voucher_date;
        $particulars = $transaction->particulars ?? '';
        $unitId = $transaction->unit_id;
        $transferGroupId = $transaction->transfer_group_id ? (string) $transaction->transfer_group_id : null;

        $instrumentNos = $transaction->instruments->pluck('instrument_no')->filter()->values()->all();
        $instrumentNo = implode(', ', $instrumentNos) ?: null;

        $particularsWithUnit = $particulars;
        if ($transaction->unit?->unit_name) {
            $particularsWithUnit = $transaction->unit->unit_name . ' - ' . $particulars;
        }

        // Load owners to determine their types
        $fromOwner = $transaction->fromOwner;
        $toOwner = $transaction->toOwner;

        if (!$fromOwner || !$toOwner) {
            throw new Exception('Invalid owner IDs: owners must exist');
        }

        // Determine transaction category
        $transactionCategory = $transaction->transaction_category ?? 'DEPOSIT';
        $isDeposit = $transactionCategory === 'DEPOSIT';
        $isWithdrawal = $transactionCategory === 'WITHDRAWAL';

        // 🔥 Trust Account Model Logic
        // For DEPOSIT: Both MAIN and CLIENT show deposit (both balances increase)
        // For WITHDRAWAL: Both MAIN and CLIENT show withdrawal (both balances decrease)
        
        // MAIN (Asset): deposit = debit (increase), withdrawal = credit (decrease)
        // CLIENT (Liability): deposit = credit (increase), withdrawal = debit (decrease)

        // From Owner Entry
        // IMPORTANT: Order by created_at (not voucher_date) to get the most recent entry chronologically
        // This ensures running balance is calculated correctly even if transactions are created out of voucher_date order
        $prevFromEntry = OwnerLedgerEntry::where('owner_id', $transaction->from_owner_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        $prevFromBalance = $prevFromEntry ? (float) $prevFromEntry->running_balance : 0;

        if ($fromOwner->owner_type === 'MAIN') {
            // MAIN: deposit = debit (increase), withdrawal = credit (decrease)
            if ($isDeposit) {
                $fromDebit = $amount;
                $fromCredit = 0;
                $newFromBalance = $prevFromBalance + $amount; // Increase
            } else {
                $fromDebit = 0;
                $fromCredit = $amount;
                $newFromBalance = $prevFromBalance - $amount; // Decrease
            }
        } else {
            // CLIENT: deposit = credit (increase), withdrawal = debit (decrease)
            if ($isDeposit) {
                $fromDebit = 0;
                $fromCredit = $amount;
                $newFromBalance = $prevFromBalance + $amount; // Increase
            } else {
                $fromDebit = $amount;
                $fromCredit = 0;
                $newFromBalance = $prevFromBalance - $amount; // Decrease
            }
        }

        OwnerLedgerEntry::create([
            'owner_id' => $transaction->from_owner_id,
            'transaction_id' => $transaction->id,
            'voucher_no' => $voucherNo,
            'voucher_date' => $voucherDate,
            'instrument_no' => $instrumentNo,
            'debit' => $fromDebit,
            'credit' => $fromCredit,
            'running_balance' => $newFromBalance,
            'unit_id' => $unitId,
            'particulars' => $particularsWithUnit,
            'transfer_group_id' => $transferGroupId,
            'created_at' => now(),
        ]);

        // To Owner Entry
        // IMPORTANT: Order by created_at (not voucher_date) to get the most recent entry chronologically
        // This ensures running balance is calculated correctly even if transactions are created out of voucher_date order
        $prevToEntry = OwnerLedgerEntry::where('owner_id', $transaction->to_owner_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        $prevToBalance = $prevToEntry ? (float) $prevToEntry->running_balance : 0;

        if ($toOwner->owner_type === 'MAIN') {
            // MAIN: deposit = debit (increase), withdrawal = credit (decrease)
            if ($isDeposit) {
                $toDebit = $amount;
                $toCredit = 0;
                $newToBalance = $prevToBalance + $amount; // Increase
            } else {
                $toDebit = 0;
                $toCredit = $amount;
                $newToBalance = $prevToBalance - $amount; // Decrease
            }
        } else {
            // CLIENT: deposit = credit (increase), withdrawal = debit (decrease)
            if ($isDeposit) {
                $toDebit = 0;
                $toCredit = $amount;
                $newToBalance = $prevToBalance + $amount; // Increase
            } else {
                $toDebit = $amount;
                $toCredit = 0;
                $newToBalance = $prevToBalance - $amount; // Decrease
            }
        }

        OwnerLedgerEntry::create([
            'owner_id' => $transaction->to_owner_id,
            'transaction_id' => $transaction->id,
            'voucher_no' => $voucherNo,
            'voucher_date' => $voucherDate,
            'instrument_no' => $instrumentNo,
            'debit' => $toDebit,
            'credit' => $toCredit,
            'running_balance' => $newToBalance,
            'unit_id' => $unitId,
            'particulars' => $particularsWithUnit,
            'transfer_group_id' => $transferGroupId,
            'created_at' => now(),
        ]);
    }

    /**
     * 🔥 2️⃣ Comprehensive server-side validation.
     * Never trust frontend - validate everything here.
     */
    protected function validateTransactionData(array $data, string $transMethod): array
    {
        // 🔥 2️⃣ Basic validation rules
        $rules = [
            'trans_type' => ['required', 'in:CASH,CHEQUE,DEPOSIT_SLIP,INTERNAL'],
            'from_owner_id' => ['required', 'integer', 'exists:owners,id'],
            'to_owner_id' => ['required', 'integer', 'exists:owners,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'amount' => ['required', 'numeric', 'min:0.01'], // Must be positive
            'fund_reference' => ['nullable', 'string', 'max:255'],
            'particulars' => ['required', 'string', 'min:1'], // 🔥 2️⃣ Required, not empty
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'voucher_no' => ['nullable', 'string', 'max:100'],
            'voucher_date' => ['nullable', 'date'],
            'transfer_group_id' => ['nullable', 'integer'],
        ];

        $validated = validator($data, $rules)->validate();

        // 🔥 2️⃣ Business rule: from_owner_id ≠ to_owner_id
        if ($validated['from_owner_id'] === $validated['to_owner_id']) {
            throw ValidationException::withMessages([
                'to_owner_id' => ['From owner and To owner cannot be the same']
            ]);
        }

        // 🔥 2️⃣ Validate owners exist and are ACTIVE
        $fromOwner = Owner::find($validated['from_owner_id']);
        $toOwner = Owner::find($validated['to_owner_id']);

        if (!$fromOwner) {
            throw ValidationException::withMessages([
                'from_owner_id' => ['From owner not found']
            ]);
        }

        if (!$toOwner) {
            throw ValidationException::withMessages([
                'to_owner_id' => ['To owner not found']
            ]);
        }

        if ($fromOwner->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'from_owner_id' => ['From owner must be ACTIVE']
            ]);
        }

        if ($toOwner->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'to_owner_id' => ['To owner must be ACTIVE']
            ]);
        }

        // 🔥 SYSTEM Owner Restriction: SYSTEM should only be used for OPENING, ADJUSTMENT, REVERSAL transactions
        // Never allow SYSTEM for normal DEPOSIT/WITHDRAWAL transactions
        $allowedCategoriesForSystem = ['OPENING', 'ADJUSTMENT', 'REVERSAL'];
        $isNormalTransaction = in_array($transMethod, ['DEPOSIT', 'WITHDRAWAL']);
        
        if ($isNormalTransaction) {
            if ($fromOwner->owner_type === 'SYSTEM') {
                throw ValidationException::withMessages([
                    'from_owner_id' => ['SYSTEM owner cannot be used for normal deposits or withdrawals. SYSTEM is only allowed for OPENING, ADJUSTMENT, or REVERSAL transactions.']
                ]);
            }
            
            if ($toOwner->owner_type === 'SYSTEM') {
                throw ValidationException::withMessages([
                    'to_owner_id' => ['SYSTEM owner cannot be used for normal deposits or withdrawals. SYSTEM is only allowed for OPENING, ADJUSTMENT, or REVERSAL transactions.']
                ]);
            }
        }

        // 🔥 2️⃣ Validate unit belongs to to_owner if provided
        if (!empty($validated['unit_id'])) {
            $unit = Unit::find($validated['unit_id']);
            if (!$unit) {
                throw ValidationException::withMessages([
                    'unit_id' => ['Unit not found']
                ]);
            }

            if ($unit->owner_id !== $validated['to_owner_id']) {
                throw ValidationException::withMessages([
                    'unit_id' => ['Unit does not belong to the selected To Owner']
                ]);
            }
        }

        // 🔥 2️⃣ Validate amount is numeric and positive
        $amount = (float) $validated['amount'];
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than 0']
            ]);
        }

        // 🔥 10️⃣ Enforce voucher mode logic
        $hasVoucherNo = !empty($validated['voucher_no']);
        $hasVoucherDate = !empty($validated['voucher_date']);

        // If voucher_no exists, voucher_date is required
        if ($hasVoucherNo && !$hasVoucherDate) {
            throw ValidationException::withMessages([
                'voucher_date' => ['Voucher date is required when voucher number is provided']
            ]);
        }

        // Normalize voucher_date
        if (!empty($validated['voucher_date'])) {
            $validated['voucher_date'] = $validated['voucher_date'];
        } else {
            $validated['voucher_date'] = null;
        }

        // Normalize voucher_no
        if (!empty($validated['voucher_no'])) {
            $validated['voucher_no'] = strtoupper(trim($validated['voucher_no']));
        } else {
            $validated['voucher_no'] = null;
        }

        return $validated;
    }

    /**
     * Get a transaction attachment file.
     */
    public function getAttachment(Request $request, $transactionId, $attachmentId)
    {
        $transaction = Transaction::find($transactionId);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        $attachment = TransactionAttachment::where('transaction_id', $transactionId)
            ->where('id', $attachmentId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        }

        if (!Storage::disk('local')->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $file = Storage::disk('local')->get($attachment->file_path);
        $mimeType = Storage::disk('local')->mimeType($attachment->file_path);

        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $attachment->file_name . '"');
    }
}
