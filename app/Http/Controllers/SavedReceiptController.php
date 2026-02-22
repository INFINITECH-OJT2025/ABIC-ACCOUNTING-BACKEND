<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedReceipt;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SavedReceiptController extends Controller
{
    /**
     * Save a receipt image.
     */
    public function store(Request $request)
    {
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
                'message' => 'Insufficient permissions.'
            ], 403);
        }

        try {
            $request->validate([
                'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
                'transaction_type' => ['required', 'string', 'in:DEPOSIT,WITHDRAWAL'],
                'receipt_image' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:10240'], // 10MB max
                'receipt_data' => ['nullable', 'string'], // JSON string of transaction data
            ]);

            $receiptData = null;
            if ($request->filled('receipt_data')) {
                $receiptData = json_decode($request->receipt_data, true);
            }

            $file = $request->file('receipt_image');
            $basePath = 'receipts/' . date('Y/m');
            
            // Generate unique file name
            $extension = $file->getClientOriginalExtension();
            $uniqueFileName = 'receipt_' . Str::uuid() . '.' . $extension;
            $path = $file->storeAs($basePath, $uniqueFileName, 'local');

            $savedReceipt = SavedReceipt::create([
                'transaction_id' => $request->transaction_id,
                'transaction_type' => $request->transaction_type,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'receipt_data' => $receiptData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Receipt saved successfully',
                'data' => $savedReceipt
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all saved receipts.
     */
    public function index(Request $request)
    {
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
                'message' => 'Insufficient permissions.'
            ], 403);
        }

        try {
            $query = SavedReceipt::with('transaction')
                ->orderBy('created_at', 'desc');

            // Filter by transaction type if provided
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            $receipts = $query->get();

            // Add file URL for each receipt (relative path for frontend proxy)
            $receipts->transform(function ($receipt) {
                $receipt->file_url = "/api/accountant/saved-receipts/{$receipt->id}/file";
                return $receipt;
            });

            return response()->json([
                'success' => true,
                'data' => $receipts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch receipts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single saved receipt.
     */
    public function show($id)
    {
        try {
            $receipt = SavedReceipt::with('transaction')->findOrFail($id);
            $receipt->file_url = "/api/accountant/saved-receipts/{$receipt->id}/file";

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found'
            ], 404);
        }
    }

    /**
     * Get a saved receipt file.
     */
    public function getFile(Request $request, $id)
    {
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
                'message' => 'Insufficient permissions.'
            ], 403);
        }

        try {
            $receipt = SavedReceipt::findOrFail($id);

            if (!Storage::disk('local')->exists($receipt->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $file = Storage::disk('local')->get($receipt->file_path);
            $mimeType = Storage::disk('local')->mimeType($receipt->file_path) ?? 'image/png';

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $receipt->file_name . '"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found'
            ], 404);
        }
    }

    /**
     * Delete a saved receipt.
     */
    public function destroy($id)
    {
        $user = request()->user();
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
                'message' => 'Insufficient permissions.'
            ], 403);
        }

        try {
            $receipt = SavedReceipt::findOrFail($id);

            // Delete file from storage
            if (Storage::exists($receipt->file_path)) {
                Storage::delete($receipt->file_path);
            }

            // Delete record
            $receipt->delete();

            return response()->json([
                'success' => true,
                'message' => 'Receipt deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete receipt: ' . $e->getMessage()
            ], 500);
        }
    }
}
