<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {

        $data = BankAccounts::orderBy('account_name','asc')->paginate(20);
        return response()->json([
            'success'=> true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {   
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number',
            'phone_number' => 'nullable|string|max:11',
            'is_pmo' => 'boolean',
        ]);

        $bankAccount = BankAccounts::create($validated);
        return response()->json([
            'success' => true,
            'data' => $bankAccount
        ], 201);
    }

    public function show($id)
    {
        $data = BankAccounts::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $data
        ]);

    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bank' => 'sometimes|required|string|max:255',
            'account_name' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|max:255|unique:bank_accounts,account_number,' . $id,
            'phone_number' => 'nullable|string|max:11',
            'is_pmo' => 'boolean',
        ]);

        $bankAccount = BankAccounts::findOrFail($id);
        $bankAccount->update($validated);
        
        return response()->json([
            'success'=> true,
        'data' => $bankAccount
        ], 200);
    }

    public function destroy($id)
    {
        BankAccounts::destroy($id);
        return response()->json([
            'success' => true,
            'message' => 'Removed bank account record successfully!'
        ], 200);
    }
}
