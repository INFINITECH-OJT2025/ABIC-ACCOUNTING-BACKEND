<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BankController extends Controller
{
    /**
     * List all banks
     */
    public function index()
    {
        try {
            $banks = Bank::orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'Banks retrieved successfully',
                'data' => $banks
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve banks due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Show a specific bank
     */
    public function show($id)
    {
        try {
            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                    'errors' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank retrieved successfully',
                'data' => $bank
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve bank: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Create a new bank
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:150|unique:banks,name',
                'short_name' => 'nullable|string|max:50',
                'country' => 'nullable|string|max:100',
                'status' => 'nullable|string|in:ACTIVE,INACTIVE',
            ], [
                'name.required' => 'Bank name is required',
                'name.unique' => 'A bank with this name already exists',
                'name.max' => 'Bank name must not exceed 150 characters',
                'short_name.max' => 'Short name must not exceed 50 characters',
                'country.max' => 'Country must not exceed 100 characters',
                'status.in' => 'Status must be either ACTIVE or INACTIVE',
            ]);

            // Set default status if not provided
            if (!isset($validated['status'])) {
                $validated['status'] = 'ACTIVE';
            }

            $bank = Bank::create($validated);

            // Log the activity
            Log::info('Bank created', [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'created_by' => auth()->user()->id,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank created successfully',
                'data' => $bank
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create bank: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Update a bank
     */
    public function update(Request $request, $id)
    {
        try {
            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                    'errors' => null
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:150|unique:banks,name,' . $id,
                'short_name' => 'nullable|string|max:50',
                'country' => 'nullable|string|max:100',
                'status' => 'sometimes|required|string|in:ACTIVE,INACTIVE',
            ], [
                'name.required' => 'Bank name is required',
                'name.unique' => 'A bank with this name already exists',
                'name.max' => 'Bank name must not exceed 150 characters',
                'short_name.max' => 'Short name must not exceed 50 characters',
                'country.max' => 'Country must not exceed 100 characters',
                'status.in' => 'Status must be either ACTIVE or INACTIVE',
            ]);

            $bank->update($validated);

            // Log the activity
            Log::info('Bank updated', [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'updated_by' => auth()->user()->id,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank updated successfully',
                'data' => $bank->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update bank: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Delete a bank
     */
    public function destroy($id)
    {
        try {
            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                    'errors' => null
                ], 404);
            }

            // Log the deletion
            Log::info('Bank deleted', [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'deleted_by' => auth()->user()->id,
                'deleted_at' => now()
            ]);

            $bank->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bank deleted successfully',
                'data' => null
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete bank: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank due to server error',
                'errors' => null
            ], 500);
        }
    }
}
