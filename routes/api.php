
<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {

    Route::get('/admin-only', function () {
        return response()->json([
            'message' => 'Super Admin Access Granted'
        ]);
    });

});

