<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MovementApprovalController;
use App\Http\Controllers\Api\PasswordOtpController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Inventory\BarcodeController;
use App\Http\Controllers\Inventory\BarcodePrintController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth or permissions needed)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/password/request-otp', [PasswordOtpController::class, 'requestOtp'])->middleware('throttle:5,1');
Route::post('/password/verify-otp', [PasswordOtpController::class, 'verifyOtp']);
Route::post('/password/reset', [PasswordOtpController::class, 'resetPassword']);
Route::get('/test-mail', function () {
    try {
        \Illuminate\Support\Facades\Mail::raw('This is a test email.', function ($message) {
            $message->to('kayiwarahim@gmail.com')->subject('Test Email');
        });

        return response()->json(['message' => 'Test email sent']);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/sessions', function (Request $request) {
        return $request->user()->tokens;
    });

    // Users (with permissions, specifying 'api' guard)
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view,api');
    Route::get('/users/access-meta', [UserController::class, 'accessMeta'])->middleware('permission:users.view,api');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view,api');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}/role', [UserController::class, 'assignRole'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}/permissions', [UserController::class, 'syncPermissions'])->middleware('permission:users.manage,api');
    Route::delete('/users/{user}/permissions', [UserController::class, 'revokePermissions'])->middleware('permission:users.manage,api');

    // Products (apiResource with per-method permissions)
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view,api');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create,api');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view,api');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit,api');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete,api');

    // Batches (apiResource with per-method permissions)
    Route::get('/batches', [BatchController::class, 'index'])->middleware('permission:batches.view,api');
    Route::post('/batches', [BatchController::class, 'store'])->middleware('permission:batches.create,api');
    Route::get('/batches/{batch_code}', [BatchController::class, 'show'])->middleware('permission:batches.view,api');
    Route::put('/batches/{batch_code}', [BatchController::class, 'update'])->middleware('permission:batches.edit,api');
    Route::delete('/batches/{batch_code}', [BatchController::class, 'destroy'])->middleware('permission:batches.delete,api');
    Route::get('/batches/{id}/print-barcode', [BatchController::class, 'printBarcode'])->middleware('permission:batches.view,api'); // Assuming view permission for printing
    Route::post('/batches/{batch}/print', [BarcodePrintController::class, 'print'])->middleware('permission:batches.view,api'); // Assuming view for printing

    // Search (related to batches/products)
    Route::get('search/{code}', [BatchController::class, 'search'])->middleware('permission:batches.view|products.view,api'); // Using role_or_permission if needed, or adjust

    // Barcodes
    Route::get('/barcodes', [BarcodeController::class, 'index'])->middleware('permission:batches.view,api'); // Tie to batches if barcodes are batch-related
    Route::post('/barcodes', [BarcodeController::class, 'store'])->middleware('permission:batches.create,api');
    Route::get('/barcodes/{barcode}', [BarcodeController::class, 'show'])->middleware('permission:batches.view,api');
    Route::put('/barcodes/{barcode}', [BarcodeController::class, 'update'])->middleware('permission:batches.edit,api');
    Route::delete('/barcodes/{barcode}', [BarcodeController::class, 'destroy'])->middleware('permission:batches.delete,api');
    Route::get('/barcodes/{code}', [BarcodeController::class, 'showByCode'])->middleware('permission:batches.view,api');

    // Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view,api');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create,api');
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view,api');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.edit,api');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete,api');

    // Branches
    Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:branches.view,api');
    Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:branches.create,api');
    Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.view,api');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.edit,api');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete,api');

    // Stock movements
    Route::post('/stock/in/{batch_code}', [StockMovementController::class, 'stockIn'])->middleware('permission:stock.in,api');
    Route::post('/stock/out/{batch_code}', [StockMovementController::class, 'stockOut'])->middleware('permission:stock.out,api');
    Route::post('/stock/transfer/{batch_code}', [StockMovementController::class, 'transfer'])->middleware('permission:stock.transfer,api');

    // Approvals (tie to stock.transfer or similar)
    Route::post('movement-approvals/{id}/approve', [MovementApprovalController::class, 'approve'])->middleware('permission:stock.transfer,api');
    Route::post('movement-approvals/{id}/reject', [MovementApprovalController::class, 'reject'])->middleware('permission:stock.transfer,api');

    // Scan
    Route::get('scan/{code}', [ScanController::class, 'scan'])->middleware('permission:stock.in|stock.out,api'); // Adjust based on use case

    // Stock levels
    Route::prefix('stock-levels')->group(function () {
        Route::get('/', [StockLevelController::class, 'index'])->middleware('permission:reports.view,api'); // Or stock.view if you add it
        Route::get('/warehouse/{warehouseId}/batch/{batchId}', [StockLevelController::class, 'show'])->middleware('permission:reports.view,api');
        Route::get('/scan/{barcode}', [StockLevelController::class, 'scan'])->middleware('permission:reports.view,api');
    });
});

// Dashboard routes (with auth and permissions)
Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('product-stock/{product_id}', [DashboardController::class, 'productStock'])->middleware('permission:reports.view,api');
    Route::get('product-stock', [DashboardController::class, 'allProductStock'])->middleware('permission:reports.view,api');
    Route::get('recent-transfers', [DashboardController::class, 'recentTransfers'])->middleware('permission:reports.view,api');
    Route::get('warehouse-products/{warehouse_id}', [DashboardController::class, 'warehouseProducts'])->middleware('permission:reports.view,api');
});
