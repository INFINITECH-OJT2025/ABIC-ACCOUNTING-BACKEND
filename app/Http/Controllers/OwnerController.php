<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;

class OwnerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ACTIVE OWNERS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        try {

            $query = Owner::query()
                ->where('status', 'ACTIVE');

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('owner_type', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            }

            $owners = $query->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Owners retrieved successfully',
                'data' => $owners
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }


    /*
|--------------------------------------------------------------------------
| SELECT OWNER (for Bank Account Creation)
|--------------------------------------------------------------------------
*/
    public function selectOwner(Request $request)
    {
        try {

            $query = Owner::query()
                ->where('status', 'ACTIVE')
                ->whereIn('owner_type', [
                    'MAIN',
                    'COMPANY',
                    'EMPLOYEE',
                    'CLIENT',
                    'UNIT',
                    'PROJECT'
                ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('owner_type', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $owners = $query->orderBy('name')->get([
                'id',
                'owner_type',
                'name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Owners retrieved successfully',
                'data' => $owners
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE OWNER
    |--------------------------------------------------------------------------
    */
    public function createOwner(Request $request)
    {
        try {

            $validated = $request->validate([
                'owner_type' => [
                    'required',
                    Rule::in(['MAIN','COMPANY','EMPLOYEE','CLIENT','UNIT','PROJECT'])
                ],
                'name' => [
                    'required','string','min:2',
                    'unique:owners,name'
                ],
                'email' => [
                    'required','email',
                    'unique:owners,email'
                ],
                'phone_number' => ['required','string','min:2'],
                'address' => ['required','string','min:2']
            ]);

            $validated['status'] = 'ACTIVE';
            $validated['created_by'] = auth()->id();

            $owner = Owner::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Owner created successfully',
                'data' => $owner
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW OWNER
    |--------------------------------------------------------------------------
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

            return response()->json([
                'success' => true,
                'message' => 'Owner retrieved successfully',
                'data' => $owner
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE OWNER
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        try {

            $owner = Owner::find($id);

            if (!$owner) {
                return response()->json([
                    'success'=> false,
                    'message' =>'Owner not found',
                    'data' => null,
                ], 404);
            }

            if ($owner->status === 'INACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update inactive owner',
                    'data' => null
                ], 403);
            }

            $validated = $request->validate([
                'owner_type' => [
                    'required',
                    Rule::in(['MAIN','COMPANY','EMPLOYEE','CLIENT','UNIT','PROJECT'])
                ],
                'name' => [
                    'required','string','min:2',
                    'unique:owners,name,' . $owner->id
                ],
                'email' => [
                    'required','email',
                    'unique:owners,email,' . $owner->id
                ],
                'phone_number' => ['required','string','min:2'],
                'address' => ['required','string','min:2']
            ]);

            $owner->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Owner successfully updated',
                'data' => $owner
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INACTIVATE OWNER
    |--------------------------------------------------------------------------
    */
    public function inactive($id)
    {
        try {

            $owner = Owner::find($id);

            if (!$owner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Owner not found'
                ], 404);
            }

            if ($owner->status === 'INACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Owner already inactive'
                ], 400);
            }

            $owner->update(['status' => 'INACTIVE']);

            return response()->json([
                'success' => true,
                'message' => 'Owner successfully inactivated',
                'data' => $owner
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESTORE OWNER
    |--------------------------------------------------------------------------
    */
    public function restore($id)
    {
        try {

            $owner = Owner::find($id);

            if (!$owner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Owner not found'
                ], 404);
            }

            if ($owner->status === 'ACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Owner already active'
                ], 400);
            }

            $owner->update(['status' => 'ACTIVE']);

            return response()->json([
                'success' => true,
                'message' => 'Owner successfully restored',
                'data' => $owner
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => null
            ], 500);
        }
    }
}