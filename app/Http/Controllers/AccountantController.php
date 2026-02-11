<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Mail\AccountantCredentialsMail;

class AccountantController extends Controller
{
    /**
     * Generate a secure random password
     */
    private function generateSecurePassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // Ensure at least one character from each category
        $password .= Str::lower(Str::random(1)); // lowercase
        $password .= Str::upper(Str::random(1)); // uppercase
        $password .= Str::random(1, '0123456789'); // number
        $password .= Str::random(1, '!@#$%^&*'); // symbol
        
        // Fill the rest with random characters
        for ($i = 4; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Send accountant credentials via email
     */
    private function sendAccountantCredentials(User $accountant, string $plainPassword): bool
    {
        try {
            Mail::to($accountant->email)->send(new AccountantCredentialsMail($accountant, $plainPassword));
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send accountant credentials email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set password expiration for new accounts
     */
    private function setPasswordExpiration(User $user): void
    {
        $user->password_expires_at = now()->addMinutes(30);
        $user->is_password_expired = false;
        $user->account_status = 'pending';
        $user->save();
    }

    /**
     * Clear password expiration after successful first login
     */
    private function clearPasswordExpiration(User $user): void
    {
        $user->password_expires_at = null;
        $user->is_password_expired = false;
        $user->last_password_change = now();
        $user->account_status = 'active';
        $user->save();
    }

    // ✅ List all accountants
    public function index()
    {
        $data = User::where('role', 'accountant')
            ->latest()
            ->get(['id', 'name', 'email', 'role', 'account_status', 'password_expires_at', 'last_password_change', 'created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'message' => 'Accountants retrieved successfully',
            'data' => $data
        ]);
    }

    // ✅ Show one accountant
    public function show($id)
    {
        $user = User::where('role', 'accountant')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Accountant not found',
                'errors' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Accountant retrieved successfully',
            'data' => $user
        ]);
    }

    // ✅ Create accountant user with auto-generated password and email
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:150|min:2',
                'email' => 'required|email|unique:users,email|max:255',
            ]);

            // Generate secure random password
            $plainPassword = $this->generateSecurePassword(12);
            $hashedPassword = Hash::make($plainPassword);

            // Create the accountant user
            $accountant = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $hashedPassword,
                'role' => 'accountant',
                'email_verified_at' => now(), // Auto-verify since we're sending credentials
                'account_status' => 'pending'
            ]);

            // Set password expiration
            $this->setPasswordExpiration($accountant);

            // Send credentials via email
            $emailSent = $this->sendAccountantCredentials($accountant, $plainPassword);

            // Log the activity for security purposes
            \Log::info('Accountant account created', [
                'accountant_id' => $accountant->id,
                'accountant_email' => $accountant->email,
                'created_by' => auth()->user()->id,
                'email_sent' => $emailSent,
                'password_expires_at' => $accountant->password_expires_at,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => $emailSent 
                    ? 'Accountant created successfully. Credentials have been sent to their email and will expire in 30 minutes.'
                    : 'Accountant created successfully, but there was an issue sending the email. Please contact the accountant directly.',
                'data' => [
                    'id' => $accountant->id,
                    'name' => $accountant->name,
                    'email' => $accountant->email,
                    'role' => $accountant->role,
                    'account_status' => $accountant->account_status,
                    'password_expires_at' => $accountant->password_expires_at,
                    'created_at' => $accountant->created_at,
                    'email_sent' => $emailSent
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to create accountant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create accountant due to server error',
                'errors' => null
            ], 500);
        }
    }

    // ✅ Update accountant
    public function update(Request $request, $id)
    {
        try {
            $user = User::where('role', 'accountant')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accountant not found',
                    'errors' => null
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:150|min:2',
                'email' => 'sometimes|required|email|unique:users,email,' . $id . '|max:255',
                'password' => 'nullable|min:8',
                'send_new_password' => 'sometimes|boolean'
            ]);

            $emailSent = false;
            $newPasswordMessage = '';

            // Handle password update
            if (!empty($validated['password'])) {
                $plainPassword = $validated['password'];
                $validated['password'] = Hash::make($plainPassword);
                
                // Send new password via email if requested
                if (!empty($validated['send_new_password'])) {
                    $emailSent = $this->sendAccountantCredentials($user, $plainPassword);
                    $newPasswordMessage = $emailSent 
                        ? ' New password has been sent to their email.'
                        : ' New password set, but there was an issue sending the email.';
                }
                
                // Log password change for security
                \Log::info('Accountant password updated', [
                    'accountant_id' => $user->id,
                    'updated_by' => auth()->user()->id,
                    'email_sent' => $emailSent,
                    'updated_at' => now()
                ]);
            } else {
                unset($validated['password']);
            }

            // Remove the send_new_password field as it's not a model attribute
            unset($validated['send_new_password']);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Accountant updated successfully.' . $newPasswordMessage,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'account_status' => $user->account_status,
                    'updated_at' => $user->updated_at,
                    'password_updated' => !empty($validated['password']),
                    'email_sent' => $emailSent
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to update accountant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update accountant due to server error',
                'errors' => null
            ], 500);
        }
    }

    // ✅ Delete accountant
    public function destroy($id)
    {
        try {
            $user = User::where('role', 'accountant')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accountant not found',
                    'errors' => null
                ], 404);
            }

            // Log the deletion for security purposes
            \Log::info('Accountant account deleted', [
                'accountant_id' => $user->id,
                'accountant_email' => $user->email,
                'deleted_by' => auth()->user()->id,
                'deleted_at' => now()
            ]);

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Accountant deleted successfully',
                'data' => null
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to delete accountant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete accountant due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Resend accountant credentials (with new password and 30-minute expiration)
     */
    public function resendCredentials(Request $request, $id)
    {
        try {
            $user = User::where('role', 'accountant')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accountant not found',
                    'errors' => null
                ], 404);
            }

            // Generate a new temporary password
            $plainPassword = $this->generateSecurePassword(12);
            $hashedPassword = Hash::make($plainPassword);

            // Update user password and reset expiration
            $user->update([
                'password' => $hashedPassword,
                'last_password_change' => null // Reset to force first login
            ]);

            // Set new password expiration
            $this->setPasswordExpiration($user);

            // Send new credentials via email
            $emailSent = $this->sendAccountantCredentials($user, $plainPassword);

            // Log the activity
            \Log::info('Accountant credentials resent', [
                'accountant_id' => $user->id,
                'accountant_email' => $user->email,
                'resent_by' => auth()->user()->id,
                'email_sent' => $emailSent,
                'password_expires_at' => $user->password_expires_at,
                'resent_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => $emailSent 
                    ? 'New credentials have been sent to the accountant\'s email and will expire in 30 minutes.'
                    : 'Password reset, but there was an issue sending the email.',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'account_status' => $user->account_status,
                    'password_expires_at' => $user->password_expires_at,
                    'email_sent' => $emailSent
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to resend accountant credentials: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend credentials due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Suspend accountant account
     */
    public function suspend(Request $request, $id)
    {
        try {
            $user = User::where('role', 'accountant')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accountant not found',
                    'errors' => null
                ], 404);
            }

            $validated = $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Update user status
            $user->update([
                'account_status' => 'suspended',
                'suspended_at' => now(),
                'suspended_reason' => $validated['reason']
            ]);

            // Log the suspension
            \Log::info('Accountant account suspended', [
                'accountant_id' => $user->id,
                'accountant_email' => $user->email,
                'suspended_by' => auth()->user()->id,
                'reason' => $validated['reason'],
                'suspended_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Accountant suspended successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'account_status' => $user->account_status,
                    'suspended_at' => $user->suspended_at,
                    'suspended_reason' => $user->suspended_reason
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to suspend accountant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend accountant due to server error',
                'errors' => null
            ], 500);
        }
    }

    /**
     * Unsuspend accountant account
     */
    public function unsuspend(Request $request, $id)
    {
        try {
            $user = User::where('role', 'accountant')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accountant not found',
                    'errors' => null
                ], 404);
            }

            // Update user status
            $user->update([
                'account_status' => 'active',
                'suspended_at' => null,
                'suspended_reason' => null
            ]);

            // Log the unsuspension
            \Log::info('Accountant account unsuspended', [
                'accountant_id' => $user->id,
                'accountant_email' => $user->email,
                'unsuspended_by' => auth()->user()->id,
                'unsuspended_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Accountant unsuspended successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'account_status' => $user->account_status
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to unsuspend accountant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsuspend accountant due to server error',
                'errors' => null
            ], 500);
        }
    }
}