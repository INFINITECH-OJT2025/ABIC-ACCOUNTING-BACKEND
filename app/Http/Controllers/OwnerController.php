<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use Illuminate\Http\Request;
use \Exception;

class OwnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {

            // Start a Query Builder/ Prepares only
            $query = Owner::query();

            // ALWAYS FILTER ACTIVE OWNERS
            $query->where('status', 'active');

            // APPLIES SEARCH FILTERING
            if ($request->filled('search')) 
            {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                        $q->where('account_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('bank_details', 'like', "%{$search}%");
                    });
            }

            // Orders & Paginates Results
            $owners = $query->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Owners retrieved successfully',
                'data' => $owners
            ]);

        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function createOwner(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_name' => ['required', 'string', 'min:2', 'max:255'],
                'account_number' => ['required', 'string', 'min:2', 'unique:owners,account_number'],
                'bank_details' => ['required', 'string', 'min:2'],
            ]);

            $validated['status'] = 'active';

            $owner = Owner::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Owner created successfully',
                'data'  => $owner
            ], 201);
            } 
        catch (\Exception $e) 
            {
                return response()->json([
                    'message' => 'Something went wrong',
                    'success' => false,
                    'data' => null
                ], 500);
            }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
        $owner = Owner::find($id);

        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Owner not found',
                'data' => null
            ], 404);
        }

        if ($owner->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Owner is archived',
                'data' => null
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Owner retrieved successfully',
            'data' => $owner
        ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            //LANCE to! - Find the Owner id first
            $owner = Owner::find($id);

            //If Owner doesnt exist  display Unauntenticated
            if (!$owner) {
                return response()->json([
                    'success'=> false,
                    'message' =>'Owner not found',
                    'data' => null,
                ], 404);
            }

            if ($owner->status === 'archived') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update archived owner',
                    'data' => null
                ], 403);
            }

            //Validate data
            $validated = $request->validate([
                'account_name' => ['required', 'string', 'min:2',],
                'account_number' => ['required', 'string', 'min:2', 'unique:owners,account_number,' . $owner->id], 
                'bank_details' => ['required', 'string', 'min:2'],
            ]);

            //Update data
            $owner->update($validated);

            //Return success response
            return response()->json([
                'success' => true,
                'message' => 'Owner Details Successfully updated',
                'data' => $owner
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }

    }

    public function archive($id) 
    {
        try {
            //Find the owner user id
            $owner = Owner::find($id);

            if (!$owner) {
                return response()->json([
                    'error' => 'Owner not found'
                ], 404);
            }

            //UPDATE!
            if ($owner->status === 'archived') {
                return response()->json(['error' => 'Owner already archived'], 400);
            }

            $owner->update(['status' => 'archived']);

            return response()->json([
                'success' => true,
                'message' => 'Owner successfully archived',
                'data' => $owner
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }

    public function restore($id) 
    {
        try {
            $owner = Owner::find($id);

            if (!$owner) {
                return response()->json([
                    'error' => 'Owner not found'
                ], 404);
            }

            if ($owner->status === 'active') {
                return response()->json(['error' => 'Owner already active'], 400);
            }

            $owner->status = 'active';
            $owner->save();

            return response()->json([
                'success' => true,
                'message' => 'Owner successfully restored',
                'data' => $owner
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }
}
