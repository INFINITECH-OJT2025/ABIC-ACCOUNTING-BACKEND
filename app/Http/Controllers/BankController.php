<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;

class BankController extends Controller
{   
    public function index()
        {
            try {

                $query = Bank::query();

                $query->where('status0', 'active');

                if ($request->filled('search')) {
                    $search = $request->search();

                    $query->where(function ($q) use ($search) {
                        $q->where('bank_name', 'like', "%{$search}%");
                    });
                }

                $bank = $query->latest()->get();

                return response()->json([
                    'success' => true,
                    'message' => 'Banks retrieved successfully',
                    'data' => $bank,
                ], 200);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong',
                ], 500);
            }
        }

    public function selectBank() {
        $banks = Bank::where('status', 'active')->get();

        if (Bank::where('status', 'active')->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No active banks available. Please create a bank first.',
                'data' => null
            ], 400);
        }

        
        return response()->json([
            'success' => true,
            'message' => 'Active banks retrieved successfully',
            'data' => $banks
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function createBank(Request $request)
    {
        try {

            $validated = $request->validate([
                'bank_name' => ['required', 'string', 'min:2', 'unique:banks,bank_name'],
                'short_name' => ['required', 'string', 'min:2'],
                'country' =>  ['required', 'string', 'min:2'],
            ]);

            $bank = Bank::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Bank account created successfully',
                'data' => $bank,
            ], 201);

        }         
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateBankName(Request $request, string $id)
    {
        try {
            
            $validated = $request->validate([
                'bank_name' => ['required', 'string', 'min:2', 'unique:banks,bank_name,' . $id],
                'short_name' => ['required', 'string', 'min:2'],
                'country' =>  ['required', 'string', 'min:3'],
            ]);

            $bank = Bank::find($id);

            if ($request->bank_name === $bank->bank_name) {
                return response()->json([
                    'success' => false,
                    'message' => 'No changes detected',
                    'data' => null
                ]);
            }

            if(!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found',
                    'data' => null
                ], 404);
            }

            $bank->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Bank updated successfully',
                'data' => $bank,
            ]);

        }         
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function inactive($id) {

        try {

            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                    'data' => null
                ], 404);
            }

            if ($bank->status === 'inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank is already inactive',
                    'data' => null,
                ], 400);
            }

            $bank->update(['status' => 'inactive']);

            return response()->json([
                'success' => true,
                'message' => 'Bank inactive successfully',
                'data' => $bank,
            ], 200);

        }         catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }


    public function restore($id) 
    {
        try {

            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                    'data' => null
                ], 404);
            }

            if ($bank->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank is already active',
                    'data' => null,
                ], 404);
            }

            $bank->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => 'Bank restored successfully',
                'data' => $bank,
            ], 200);

        }         catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                    'data' => null
                ], 404);
            }

            if ($bank->status === 'inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank is inactive',
                    'data' => null
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank retrieved successfully',
                'data' => $bank
            ], 200);

        }         catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
