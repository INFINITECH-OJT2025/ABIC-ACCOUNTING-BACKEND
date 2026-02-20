<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Unit::query();

            // Filter by owner_id if provided
            if ($request->filled('owner_id')) {
                $query->where('owner_id', $request->owner_id);
            }

            // Filter by property_id if provided
            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by status if provided
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Apply search filtering
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('unit_name', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            $units = $query->with('owner', 'property')->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Units retrieved successfully',
                'data' => $units
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve units: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'owner_id' => ['nullable', 'integer', 'exists:owners,id'],
                'property_id' => ['nullable', 'integer'],
                'unit_name' => ['required', 'string', 'max:100'],
                'status' => ['required', 'string', 'in:ACTIVE,INACTIVE'],
                'notes' => ['nullable', 'string'],
            ], [
                'unit_name.required' => 'Unit name is required',
                'unit_name.max' => 'Unit name must not exceed 100 characters',
                'status.required' => 'Status is required',
                'status.in' => 'Status must be ACTIVE or INACTIVE',
                'owner_id.exists' => 'Selected owner does not exist',
            ]);

            $unit = Unit::create($validated);

            Log::info('Unit created', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->unit_name,
                'owner_id' => $unit->owner_id,
                'property_id' => $unit->property_id,
                'created_by' => auth()->user()->id ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unit created successfully',
                'data' => $unit
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create unit: ' . $e->getMessage());
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
            $unit = Unit::find($id);

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit not found',
                    'data' => null
                ], 404);
            }

            $unit->load('owner', 'property');

            return response()->json([
                'success' => true,
                'message' => 'Unit retrieved successfully',
                'data' => $unit
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve unit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit not found',
                    'data' => null,
                ], 404);
            }

            $validated = $request->validate([
                'owner_id' => ['nullable', 'integer', 'exists:owners,id'],
                'property_id' => ['nullable', 'integer'],
                'unit_name' => ['sometimes', 'required', 'string', 'max:100'],
                'status' => ['sometimes', 'required', 'string', 'in:ACTIVE,INACTIVE'],
                'notes' => ['nullable', 'string'],
            ], [
                'unit_name.required' => 'Unit name is required',
                'unit_name.max' => 'Unit name must not exceed 100 characters',
                'status.required' => 'Status is required',
                'status.in' => 'Status must be ACTIVE or INACTIVE',
                'owner_id.exists' => 'Selected owner does not exist',
            ]);

            $unit->update($validated);

            Log::info('Unit updated', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->unit_name,
                'updated_by' => auth()->user()->id ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unit updated successfully',
                'data' => $unit->fresh()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update unit: ' . $e->getMessage());
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
    public function destroy($id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit not found',
                    'data' => null
                ], 404);
            }

            Log::info('Unit deleted', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->unit_name,
                'deleted_by' => auth()->user()->id ?? null,
            ]);

            $unit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Unit deleted successfully',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete unit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }
}
