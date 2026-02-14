<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordOtpController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BatchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Inventory\{
    BarcodeController,
    StockMovementController,
    StockLevelController,
    MovementApprovalController,
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

    // Barcode scan
    Route::get('/scan/{barcode}', [BarcodeController::class, 'scan']);

    // Stock movements
    Route::post('/stock/in', [StockMovementController::class, 'stockIn']);
    Route::post('/stock/out', [StockMovementController::class, 'stockOut']);
    Route::post('/stock/transfer', [StockMovementController::class, 'transfer']);

    // Stock levels
    Route::get('/stock-levels', [StockLevelController::class, 'index']);

    // Approvals
    Route::post('/approvals/{approval}/approve', [MovementApprovalController::class, 'approve']);
    Route::post('/approvals/{approval}/reject', [MovementApprovalController::class, 'reject']);

    // Barcode printing
    Route::post('/batches/{batch}/print', [BarcodePrintController::class, 'print']);

    // Barcode generation
    Route::post('/barcodes', [BarcodeController::class, 'store']);

    // Barcode lookup (scan / manual)
    Route::get('/barcodes/{code}', [BarcodeController::class, 'showByCode']);
});