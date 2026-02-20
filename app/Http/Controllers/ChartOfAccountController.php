<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

class ChartOfAccountController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ALL ACCOUNTS
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $accounts = ChartOfAccount::with(['parent', 'bankAccount'])
            ->orderBy('account_code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW SINGLE ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $account = ChartOfAccount::with(['parent', 'children', 'bankAccount'])
            ->find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $account
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'account_code' => ['required', 'unique:chart_of_accounts,account_code'],
                'account_name' => ['required', 'string'],
                'account_type' => ['required', 'in:ASSET,LIABILITY,EQUITY,INCOME,EXPENSE'],
                'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
                'related_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            ]);

            // If linking to bank account, must be ASSET
            if (!empty($validated['related_bank_account_id']) &&
                $validated['account_type'] !== 'ASSET') {

                throw ValidationException::withMessages([
                    'account_type' => ['Only ASSET accounts can be linked to bank accounts']
                ]);
            }

            $account = ChartOfAccount::create([
                'account_code' => $validated['account_code'],
                'account_name' => $validated['account_name'],
                'account_type' => $validated['account_type'],
                'parent_id' => $validated['parent_id'] ?? null,
                'related_bank_account_id' => $validated['related_bank_account_id'] ?? null,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $account
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {

            Log::error('COA Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create account'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        try {

            $account = ChartOfAccount::findOrFail($id);

            $validated = $request->validate([
                'account_code' => ['required', 'unique:chart_of_accounts,account_code,' . $id],
                'account_name' => ['required', 'string'],
                'account_type' => ['required', 'in:ASSET,LIABILITY,EQUITY,INCOME,EXPENSE'],
                'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
                'related_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            ]);

            if (!empty($validated['related_bank_account_id']) &&
                $validated['account_type'] !== 'ASSET') {

                throw ValidationException::withMessages([
                    'account_type' => ['Only ASSET accounts can be linked to bank accounts']
                ]);
            }

            $account->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'data' => $account
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {

            Log::error('COA Update Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update account'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DEACTIVATE ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function deactivate($id)
    {
        $account = ChartOfAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $account->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function activate($id)
    {
        $account = ChartOfAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $account->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Account activated'
        ]);
    }
}
