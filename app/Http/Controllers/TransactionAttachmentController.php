<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class TransactionAttachmentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ATTACHMENTS PER TRANSACTION
    |--------------------------------------------------------------------------
    */
    public function index($transactionId)
    {
        try {

            $attachments = TransactionAttachment::where('transaction_id', $transactionId)
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Attachments retrieved successfully',
                'data' => $attachments
            ]);

        } catch (Exception $e) {

            Log::error('Attachment Index Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attachments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD ATTACHMENT
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        try {

            return DB::transaction(function () use ($request) {

                $validated = $request->validate([
                    'transaction_id' => ['required', 'exists:transactions,id'],
                    'attachment_type' => ['required', 'in:VOUCHER,SUPPORTING'],
                    'file' => ['required', 'file', 'max:5120']
                ]);

                $transaction = Transaction::findOrFail($validated['transaction_id']);

                $file = $request->file('file');

                $fileName = time() . '_' . $file->getClientOriginalName();

                $filePath = $file->storeAs(
                    'transactions/' . $transaction->id,
                    $fileName,
                    'public'
                );

                if (!$filePath) {
                    throw new Exception('File upload failed.');
                }

                $attachment = TransactionAttachment::create([
                    'transaction_id' => $transaction->id,
                    'attachment_type' => $validated['attachment_type'],
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_type' => $file->getClientMimeType(),
                    'uploaded_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Attachment uploaded successfully',
                    'data' => $attachment
                ], 201);
            });

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {

            Log::error('Attachment Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ATTACHMENT
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        try {

            return DB::transaction(function () use ($id) {

                $attachment = TransactionAttachment::findOrFail($id);

                // Delete file from storage
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }

                $attachment->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Attachment deleted successfully'
                ]);
            });

        } catch (Exception $e) {

            Log::error('Attachment Delete Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
