<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BankContact;
use App\Models\BankContactChannel;
use Exception;

class BankContactChannelController extends Controller
{
    /**
     * Create Channel
     */
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'bank_contact_id' => ['required', 'exists:bank_contacts,id'],
                'channel_type'    => ['required', 'string', 'min:2'],
                'value'           => ['required', 'string', 'min:2'],
                'label'           => ['nullable', 'string', 'min:2'],
            ]);

            $channel = BankContactChannel::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Channel created successfully',
                'data'    => $channel
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
     * List Channels per Contact
     */
    public function index($contactId)
    {
        try {

            $channels = BankContactChannel::where('bank_contact_id', $contactId)
                ->latest()
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Channels retrieved successfully',
                'data'    => $channels
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
     * Show Single Channel
     */
    public function show($id)
    {
        try {

            $channel = BankContactChannel::find($id);

            if (!$channel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Channel not found',
                    'data'    => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Channel retrieved successfully',
                'data'    => $channel
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
     * Update Channel
     */
    public function update(Request $request, $id)
    {
        try {

            $channel = BankContactChannel::find($id);

            if (!$channel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Channel not found',
                    'data'    => null
                ], 404);
            }

            $validated = $request->validate([
                'channel_type' => ['required', 'string', 'min:2'],
                'value'        => ['required', 'string', 'min:2'],
                'label'        => ['nullable', 'string', 'min:2'],
            ]);

            $channel->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Channel updated successfully',
                'data'    => $channel
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
     * Delete Channel
     */
    public function destroy($id)
    {
        try {

            $channel = BankContactChannel::find($id);

            if (!$channel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Channel not found',
                    'data'    => null
                ], 404);
            }

            $channel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Channel deleted successfully',
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
