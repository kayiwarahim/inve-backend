<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Inventory\{
    ProductController,
    BatchController,
    BarcodeController,
    StockMovementController,
    StockLevelController,
    MovementApprovalController,
    BarcodePrintController
};

Route::middleware('auth:sanctum')->group(function () {

    // Products & batches
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/batches', [BatchController::class, 'store']);

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
        // Batches
    Route::post('/batches', [BatchController::class, 'store']);

    // Barcode generation
    Route::post('/barcodes', [BarcodeController::class, 'store']);

    // Barcode lookup (scan / manual)
    Route::get('/barcodes/{code}', [BarcodeController::class, 'showByCode']);
});
