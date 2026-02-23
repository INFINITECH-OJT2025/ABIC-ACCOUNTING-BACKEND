<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedReceipt;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\FirebaseStorageService;

class SavedReceiptController extends Controller
{
    /**
     * Check if user has permission to access receipts.
     */
    protected function checkAuthorization(Request $request): void
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'Unauthorized');
        }

        $userRole = strtolower($user->role ?? '');
        $allowedRoles = ['accountant', 'super_admin', 'admin'];
        
        if (!in_array($userRole, $allowedRoles)) {
            abort(403, 'Insufficient permissions.');
        }
    }

    /**
     * Check if a file path is a Firebase Storage URL.
     */
    protected function isFirebaseUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    /**
     * Get file URL for a receipt (Firebase URL or API proxy).
     */
    protected function getFileUrl(SavedReceipt $receipt): string
    {
        return $this->isFirebaseUrl($receipt->file_path) 
            ? $receipt->file_path 
            : "/api/accountant/saved-receipts/{$receipt->id}/file";
    }

    /**
     * Save a receipt image.
     */
    public function store(Request $request)
    {
        $this->checkAuthorization($request);

        try {
            $validated = $request->validate([
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
            $uniqueFileName = 'receipt_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $firebasePath = $basePath . '/' . $uniqueFileName;
            
            // Upload to Firebase Storage
            $firebaseStorage = new FirebaseStorageService();
            $firebaseUrl = $firebaseStorage->uploadFile($file, $firebasePath);

            $savedReceipt = SavedReceipt::create([
                'transaction_id' => $validated['transaction_id'] ?? null,
                'transaction_type' => $validated['transaction_type'],
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $firebaseUrl,
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
            Log::error('Failed to save receipt', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
        $this->checkAuthorization($request);

        try {
            $query = SavedReceipt::with('transaction')
                ->orderBy('created_at', 'desc');

            // Filter by transaction type if provided
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            $receipts = $query->get();

            // Add file URL for each receipt
            $receipts->transform(function ($receipt) {
                $receipt->file_url = $this->getFileUrl($receipt);
                return $receipt;
            });

            return response()->json([
                'success' => true,
                'data' => $receipts
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch receipts', [
                'error' => $e->getMessage()
            ]);
            
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
            $receipt->file_url = $this->getFileUrl($receipt);

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
        $this->checkAuthorization($request);

        try {
            $receipt = SavedReceipt::findOrFail($id);

            // If Firebase URL, redirect to it
            if ($this->isFirebaseUrl($receipt->file_path)) {
                return redirect($receipt->file_path);
            }

            // Legacy local storage fallback
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
            Log::error('Failed to get receipt file', [
                'receipt_id' => $id,
                'error' => $e->getMessage()
            ]);
            
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
        $this->checkAuthorization(request());

        try {
            $receipt = SavedReceipt::findOrFail($id);

            // Delete file from storage (Firebase or local)
            if ($this->isFirebaseUrl($receipt->file_path)) {
                try {
                    $firebaseStorage = new FirebaseStorageService();
                    $firebaseStorage->deleteFile($receipt->file_path);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete Firebase file', [
                        'receipt_id' => $id,
                        'file_path' => $receipt->file_path,
                        'error' => $e->getMessage()
                    ]);
                    // Continue with record deletion even if file deletion fails
                }
            } else {
                // Local storage fallback
                if (Storage::exists($receipt->file_path)) {
                    Storage::delete($receipt->file_path);
                }
            }

            // Delete record
            $receipt->delete();

            return response()->json([
                'success' => true,
                'message' => 'Receipt deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete receipt', [
                'receipt_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete receipt: ' . $e->getMessage()
            ], 500);
        }
    }
}
