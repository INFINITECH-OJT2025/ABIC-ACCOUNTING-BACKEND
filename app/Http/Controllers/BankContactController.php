<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;
use App\Models\BankContact;
use App\Models\BankContactChannel;
use Exception;

class BankContactController extends Controller
{
    /**
     * Create Bank Contact (with optional channels)
     */
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'bank_id'        => ['required', 'exists:banks,id'],
                'branch_name'    => ['required', 'string', 'min:2'],
                'contact_person' => ['required', 'string', 'min:2'],
                'position'       => ['required', 'string', 'min:2'],
                'notes'          => ['nullable', 'string'],

                // optional new channels
                'channels'                       => ['nullable', 'array'],
                'channels.*.channel_type'        => ['required_with:channels', 'string'],
                'channels.*.value'               => ['required_with:channels', 'string'],
                'channels.*.label'               => ['nullable', 'string'],

                // optional attach existing channels
                'existing_channel_ids'   => ['nullable', 'array'],
                'existing_channel_ids.*' => ['exists:bank_contact_channels,id'],
            ]);

            // Create contact
            $contact = BankContact::create([
                'bank_id'        => $validated['bank_id'],
                'branch_name'    => $validated['branch_name'],
                'contact_person' => $validated['contact_person'],
                'position'       => $validated['position'],
                'notes'          => $validated['notes'] ?? null,
            ]);

            // Attach existing channels (re-assign them)
            if (!empty($validated['existing_channel_ids'])) {
                BankContactChannel::whereIn('id', $validated['existing_channel_ids'])
                    ->update(['bank_contact_id' => $contact->id]);
            }

            // Create new channels
            if (!empty($validated['channels'])) {
                foreach ($validated['channels'] as $channel) {
                    $contact->channels()->create($channel);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank contact created successfully',
                'data'    => $contact->load('channels')
            ], 201);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Contacts per Bank
     */
    public function index(Request $request)
    {
        try {

            $request->validate([
                'bank_id' => ['required', 'exists:banks,id']
            ]);

            $query = BankContact::where('bank_id', $request->bank_id)
                ->with('channels');

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('branch_name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('position', 'like', "%{$search}%");
                });
            }

            $contacts = $query->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Contacts retrieved successfully',
                'data'    => $contacts
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data'    => null
            ], 500);
        }
    }

    /**
     * Show Single Contact
     */
    public function show($id)
    {
        try {

            $contact = BankContact::with('channels')->find($id);

            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                    'data'    => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contact retrieved successfully',
                'data'    => $contact
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data'    => null
            ], 500);
        }
    }

    /**
     * Update Contact
     */
    public function update(Request $request, $id)
    {
        try {

            $contact = BankContact::find($id);

            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                    'data'    => null
                ], 404);
            }

            $validated = $request->validate([
                'branch_name'    => ['required', 'string', 'min:2'],
                'contact_person' => ['required', 'string', 'min:2'],
                'position'       => ['required', 'string', 'min:2'],
                'notes'          => ['nullable', 'string'],
            ]);

            $contact->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully',
                'data'    => $contact->load('channels')
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data'    => null
            ], 500);
        }
    }

    /**
     * Delete Contact (cascade deletes channels)
     */
    public function destroy($id)
    {
        try {

            $contact = BankContact::find($id);

            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                    'data'    => null
                ], 404);
            }

            $contact->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully',
                'data'    => null
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data'    => null
            ], 500);
        }
    }
}
