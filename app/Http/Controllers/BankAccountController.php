<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;
use App\Models\BankAccount;

class BankAccountController extends Controller
{

    public function show($id)
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank account retrieved successfully',
            'data' => $account
        ], 200);
    }

    public function createBankAccount(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'company_name'      => ['required', 'string', 'min:3'],
            'bank_id'           => ['required', 'exists:banks,id'],
            'account_name'      => ['required', 'string', 'min:3'],
            'account_number'    => ['required', 'string', 'unique:bank_accounts,account_number'],
            'is_pmo'            => ['sometimes', 'boolean'],
            'contact_numbers'   => ['nullable', 'array'],
            'contact_numbers.*' => ['string', 'min:11'],
        ]);

        // Set default values for optional fields
        $validated['status'] = 'active';
        // If is_pmo is not provided, default to false
        $validated['is_pmo'] = $validated['is_pmo'] ?? false;

        // If PMO is true, contact numbers must be provided
        if ($validated['is_pmo'] && empty($validated['contact_numbers'])) {
            return response()->json([
                'success' => false,
                'message' => 'Contact numbers are required for PMO accounts',
                'data' => null
            ], 422);
        }

        //  Check if the selected bank is active
        $bank = Bank::find($validated['bank_id']);

        if ($bank->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Selected bank is inactive',
                'data' => null
            ], 400);
        }

        $account = BankAccount::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank account created successfully',
            'data' => $account
        ], 201);
    }


    public function updateBankAccount(Request $request, $id)
    {
        // Find the bank account
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        // Only allow updates if the account is active
        if ($account->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update archived account',
                'data' => null
            ], 400);
        }

        // Validate request data
        $validated = $request->validate([
            'company_name'      => ['required', 'string', 'min:3'],
            'bank_id'           => ['required', 'exists:banks,id'],
            'account_name'      => ['required', 'string', 'min:3'],
            'account_number'    => ['required', 'string', 'unique:bank_accounts,account_number,' . $id],
            'is_pmo'            => ['sometimes', 'boolean'],
            'contact_numbers'   => ['nullable', 'array'],
            'contact_numbers.*' => ['string', 'min:11'],
        ]);

        // Set default values for optional fields
        $validated['is_pmo'] = $validated['is_pmo'] ?? $account->is_pmo;

        // If PMO is true, contact numbers must be provided
        if ($validated['is_pmo'] && empty($validated['contact_numbers'])) {
            return response()->json([
                'success' => false,
                'message' => 'Contact numbers are required for PMO accounts',
                'data' => null
            ], 422);
        }

        // Check if the selected bank is active
        $bank = Bank::find($validated['bank_id']);

        if ($bank->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Selected bank is inactive',
                'data' => null
            ], 400);
        }

        // Update the bank account
        $account->update($validated);
        // Refresh the model instance to get the latest data
        $account->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Bank account updated successfully',
            'data' => $account
        ]);
    }


    public function archiveBank($id)
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        if ($account->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Already archived',
                'data' => null
            ], 400);
        }

        $account->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => 'Bank account archived successfully',
            'data' => $account
        ]);
    }


    public function restoreBank($id)
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        if ($account->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Already active',
                'data' => null
            ], 400);
        }

        $account->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Bank account restored successfully',
            'data' => $account
        ]);
    }
}
