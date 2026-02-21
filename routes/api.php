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
use App\Http\Controllers\Api\DashboardController;

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

Route::middleware('auth:sanctum')->get('/sessions', function (Request $request) {
    return $request->user()->tokens;
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });
    Route::apiResource('products', ProductController::class);
    Route::apiResource('batches', BatchController::class)->parameters(['batches' => 'batch_code']); // Uses batch_code instead of id
    // Search by batch_code or barcode code
    Route::get('search/{code}', [BatchController::class, 'search']); 
    Route::apiResource('barcodes', BarcodeController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('branches', BranchController::class);
    // Stock movements
    Route::post('/stock/in/{batch_code}', [StockMovementController::class, 'stockIn']);
    Route::post('/stock/out/{batch_code}', [StockMovementController::class, 'stockOut']);
    Route::post('/stock/transfer/{batch_code}', [StockMovementController::class, 'transfer']);
    // Approvals
    Route::post('movement-approvals/{id}/approve', [MovementApprovalController::class, 'approve']);
    Route::post('movement-approvals/{id}/reject', [MovementApprovalController::class, 'reject']);
    // Barcode scan
    Route::get('scan/{code}', [ScanController::class, 'scan']);
    // Barcode printing
    Route::get('/batches/{id}/print-barcode', [BatchController::class, 'printBarcode']);






 
    // Stock levels
    Route::prefix('stock-levels')->group(function () {
        Route::get('/', [StockLevelController::class, 'index']);
        Route::get('/warehouse/{warehouseId}/batch/{batchId}', [StockLevelController::class, 'show']);
        Route::get('/scan/{barcode}', [StockLevelController::class, 'scan']);
    });

    // Barcode printing
    Route::post('/batches/{batch}/print', [BarcodePrintController::class, 'print']);
    // Barcode generation
    Route::post('/barcodes', [BarcodeController::class, 'store']);
    // Barcode lookup (scan / manual)
    Route::get('/barcodes/{code}', [BarcodeController::class, 'showByCode']);
});

Route::prefix('dashboard')->group(function () {
    Route::get('product-stock/{product_id}', [DashboardController::class, 'productStock']);
    Route::get('product-stock', [DashboardController::class, 'allProductStock']);
    Route::get('recent-transfers', [DashboardController::class, 'recentTransfers']);
    Route::get('warehouse-products/{warehouse_id}', [DashboardController::class, 'warehouseProducts']);
    // Add more as needed, e.g., Route::get('low-stock', ...);
})->middleware('auth:sanctum'); // If auth required