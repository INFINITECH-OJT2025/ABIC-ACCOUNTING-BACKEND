<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;
use App\Models\Owner;
use App\Models\BankAccount;

class BankAccountController extends Controller
{

    public function index(Request $request)
    {
        $query = BankAccount::with(['bank', 'owner', 'creator']);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('account_name', 'like', "%{$search}%")
                ->orWhere('account_number', 'like', "%{$search}%")
                ->orWhere('account_holder', 'like', "%{$search}%");
            });
        }

        $accounts = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Bank accounts retrieved successfully',
            'data' => $accounts
        ]);
    }


    public function show($id)
    {
        $account = BankAccount::with(['bank', 'owner'])->find($id);

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
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_id'        => ['required', 'exists:owners,id'],
            'bank_id'         => ['required', 'exists:banks,id'],
            'account_name'    => ['required', 'string'],
            'account_number'  => ['required', 'string', 'unique:bank_accounts,account_number'],
            'account_holder'  => ['required', 'string'],
            'account_type'    => ['required', 'string'],
            'opening_balance' => ['required', 'numeric'],
            'opening_date'    => ['required', 'date'],
            'currency'        => ['required', 'string'],
        ]);

        $bank = Bank::find($validated['bank_id']);
        if ($bank->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Selected bank is INACTIVE',
                'data' => null
            ], 400);
        }

        $owner = Owner::find($validated['owner_id']);
        if ($owner->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Selected owner is INACTIVE',
                'data' => null
            ], 400);
        }

        $validated['status'] = 'ACTIVE';
        $validated['created_by'] = auth()->id(); // authenticated user

        $account = BankAccount::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank account created successfully',
            'data' => $account
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        if ($account->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update INACTIVE account',
                'data' => null
            ], 400);
        }

        $validated = $request->validate([
            'owner_id'        => ['required', 'exists:owners,id'],
            'bank_id'         => ['required', 'exists:banks,id'],
            'account_name'    => ['required', 'string', 'min:2'],
            'account_number'  => ['required', 'string', 'unique:bank_accounts,account_number,' . $id],
            'account_holder'  => ['required', 'string', 'min:2'],
            'account_type'    => ['required', 'string'],
            'opening_balance' => ['required', 'numeric'],
            'opening_date'    => ['required', 'date'],
            'currency'        => ['required', 'string', 'max:10'],
        ]);

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank account updated successfully',
            'data' => $account
        ]);
    }

    public function INACTIVE($id)
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        if ($account->status === 'INACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Already INACTIVE',
                'data' => null
            ], 400);
        }

        $account->update(['status' => 'INACTIVE']);

        return response()->json([
            'success' => true,
            'message' => 'Bank account INACTIVE successfully',
            'data' => $account
        ]);
    }

    public function restore($id)
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
                'data' => null
            ], 404);
        }

        if ($account->status === 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Already ACTIVE',
                'data' => null
            ], 400);
        }

        $account->update(['status' => 'ACTIVE']);

        return response()->json([
            'success' => true,
            'message' => 'Bank account restored successfully',
            'data' => $account
        ]);
    }
}
