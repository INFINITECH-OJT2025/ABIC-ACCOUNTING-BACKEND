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

            // Filter by status if provided, otherwise default to active
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                $query->where('status', 'active');
            }

            // Filter by owner type if provided
            if ($request->filled('owner_type') && $request->owner_type !== 'ALL') {
                $query->where('owner_type', $request->owner_type);
            }

            // APPLIES SEARCH FILTERING
            if ($request->filled('search')) 
            {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                        $q->where('owner_type', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                    });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'date'); // 'date' or 'name'
            $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'
            
            if ($sortBy === 'date') {
                // Sort by created_at (date created) - desc by default
                $query->orderBy('created_at', $sortOrder === 'asc' ? 'ASC' : 'DESC');
            } elseif ($sortBy === 'name') {
                // Sort alphabetically by name - asc by default
                $query->orderBy('name', $sortOrder === 'asc' ? 'ASC' : 'DESC');
            } else {
                // Default: newest first
                $query->orderBy('created_at', 'DESC');
            }

            // Orders & Paginates Results
            $perPage = $request->input('per_page', 10);
            
            // If per_page is 'all' or a very large number, get all results without pagination
            if ($perPage === 'all' || (is_numeric($perPage) && $perPage > 1000)) {
                $owners = $query->get();
                return response()->json([
                    'success' => true,
                    'message' => 'Owners retrieved successfully',
                    'data' => $owners
                ]);
            }
            
            $owners = $query->paginate((int)$perPage);

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
                'owner_type' => ['required', 'string', 'max:255', 'in:COMPANY,CLIENT,EMPLOYEE,INDIVIDUAL,MAIN,PARTNER,PROPERTY,PROJECT'],
                'name' => ['required', 'string', 'min:2', 'unique:owners,name'],
                'email' => ['nullable', 'email', 'unique:owners,email'],
                'phone_number' => ['nullable', 'string', 'min:2'],
                'address' => ['nullable', 'string', 'min:2']
            ]);

            $validated['status'] = 'active';
            // Map phone_number to phone if needed
            if (isset($validated['phone_number'])) {
                $validated['phone'] = $validated['phone_number'];
                unset($validated['phone_number']);
            }

            $owner = Owner::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Owner created successfully',
                'data'  => $owner
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
                'owner_type' => ['required', 'string', 'min:2', 'in:COMPANY,CLIENT,EMPLOYEE,INDIVIDUAL,MAIN,PARTNER,PROPERTY,PROJECT'],
                'name' => ['required', 'string', 'min:2', 'unique:owners,name,' . $owner->id], 
                'email' => ['nullable', 'email', 'unique:owners,email,' . $owner->id],
                'phone_number' => ['nullable', 'string', 'min:2'],
                'address' => ['nullable', 'string', 'min:2']
            ]);

            // Map phone_number to phone if needed
            if (isset($validated['phone_number'])) {
                $validated['phone'] = $validated['phone_number'];
                unset($validated['phone_number']);
            }

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
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }

    }

    public function inactive($id) 
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
            if ($owner->status === 'inactive') {
                return response()->json(['error' => 'Owner already inactive'], 400);
            }

            $owner->update(['status' => 'inactive']);

            return response()->json([
                'success' => true,
                'message' => 'Owner successfully archived',
                'data' => $owner
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
