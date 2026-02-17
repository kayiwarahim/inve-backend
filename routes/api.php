<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordOtpController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\MovementApprovalController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\ScanController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Inventory\{
    BarcodeController,
    BarcodePrintController
};

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/otp/request', [PasswordOtpController::class, 'requestOtp']);
Route::post('/password/otp/verify', [PasswordOtpController::class, 'verifyOtp']);
Route::post('/password/otp/reset', [PasswordOtpController::class, 'resetPassword']);
Route::get('/test-mail', function () {
    Mail::raw('Test email', function ($message) {
        $message->to('kayiwarahim@gmail.com')
                ->subject('Test Mail');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });
    Route::apiResource('products', ProductController::class);
    Route::apiResource('batches', BatchController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('branches', BranchController::class);
    // Stock movements
    Route::post('/stock/in', [StockMovementController::class, 'stockIn']);
    Route::post('/stock/out', [StockMovementController::class, 'stockOut']);
    Route::post('/stock/transfer', [StockMovementController::class, 'transfer']);
    // Approvals
    Route::post('movement-approvals/{id}/approve', [MovementApprovalController::class, 'approve']);
    Route::post('movement-approvals/{id}/reject', [MovementApprovalController::class, 'reject']);
   // Barcode scan
    Route::get('scan/{code}', [ScanController::class, 'scan']);





 
    // Stock levels
    Route::get('/stock-levels', [StockLevelController::class, 'index']);
    // Barcode printing
    Route::post('/batches/{batch}/print', [BarcodePrintController::class, 'print']);
    // Barcode generation
    Route::post('/barcodes', [BarcodeController::class, 'store']);
    // Barcode lookup (scan / manual)
    Route::get('/barcodes/{code}', [BarcodeController::class, 'showByCode']);
});