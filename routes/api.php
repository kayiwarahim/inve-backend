<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LotTransformationController;
use App\Http\Controllers\Api\PasswordOtpController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProcessStepController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\SourceLotController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Inventory\BarcodePrintController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth or permissions needed)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/request-otp', [PasswordOtpController::class, 'requestOtp'])->middleware('throttle:5,1');
Route::post('/password/verify-otp', [PasswordOtpController::class, 'verifyOtp']);
Route::post('/password/reset', [PasswordOtpController::class, 'resetPassword']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/change-password', [AuthController::class, 'changePassword']);

    // Users (with permissions, specifying 'api' guard)
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view,api');
    Route::get('/users/access-meta', [UserController::class, 'accessMeta'])->middleware('permission:users.view,api');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view,api');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage,api');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}/role', [UserController::class, 'assignRole'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('permission:users.manage,api');
    Route::put('/users/{user}/permissions', [UserController::class, 'syncPermissions'])->middleware('permission:users.manage,api');
    Route::delete('/users/{user}/permissions', [UserController::class, 'revokePermissions'])->middleware('permission:users.manage,api');

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view,api');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create,api');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view,api');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit,api');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete,api');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view,api');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create,api');
    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.view,api');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.edit,api');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete,api');

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
    Route::get('/barcodes/code/{code}', [BarcodeController::class, 'showByCode'])->middleware('permission:batches.view,api');
    Route::get('/barcodes/{barcode}', [BarcodeController::class, 'show'])->middleware('permission:batches.view,api');
    Route::put('/barcodes/{barcode}', [BarcodeController::class, 'update'])->middleware('permission:batches.edit,api');
    Route::delete('/barcodes/{barcode}', [BarcodeController::class, 'destroy'])->middleware('permission:batches.delete,api');

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

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view,api');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create,api');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view,api');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.edit,api');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete,api');

    // Source lots
    Route::get('/source-lots', [SourceLotController::class, 'index'])->middleware('permission:source-lots.view,api');
    Route::post('/source-lots', [SourceLotController::class, 'store'])->middleware('permission:source-lots.create,api');
    Route::get('/source-lots/{sourceLot}', [SourceLotController::class, 'show'])->middleware('permission:source-lots.view,api');

    // Process steps
    Route::get('/process-steps', [ProcessStepController::class, 'index'])->middleware('permission:process-steps.view,api');
    Route::post('/process-steps', [ProcessStepController::class, 'store'])->middleware('permission:process-steps.create,api');
    Route::get('/process-steps/{processStep}', [ProcessStepController::class, 'show'])->middleware('permission:process-steps.view,api');
    Route::put('/process-steps/{processStep}', [ProcessStepController::class, 'update'])->middleware('permission:process-steps.edit,api');
    Route::delete('/process-steps/{processStep}', [ProcessStepController::class, 'destroy'])->middleware('permission:process-steps.delete,api');

    // Lot transformations
    Route::get('/lot-transformations', [LotTransformationController::class, 'index'])->middleware('permission:transformations.view,api');
    Route::post('/lot-transformations', [LotTransformationController::class, 'store'])->middleware('permission:transformations.create,api');
    Route::post('/lot-transformations/repack-mixed', [LotTransformationController::class, 'repackMixedPortions'])->middleware('permission:transformations.create,api');
    Route::get('/trace/{code}', [TraceabilityController::class, 'show'])->middleware('permission:traceability.view,api');

    // Stock movements
    Route::post('/stock/in/{batch_code}', [StockMovementController::class, 'stockIn'])->middleware('permission:stock.in,api');
    Route::post('/stock/out/{batch_code}', [StockMovementController::class, 'stockOut'])->middleware('permission:stock.out,api');
    Route::post('/stock/transfer/{batch_code}', [StockMovementController::class, 'transfer'])->middleware('permission:stock.transfer,api');

    // Scan
    Route::get('scan/{code}', [ScanController::class, 'scan'])->middleware('permission:stock.in|stock.out,api'); // Adjust based on use case

    // Stock levels
    Route::prefix('stock-levels')->group(function () {
        Route::get('/', [StockLevelController::class, 'index'])->middleware('permission:stock.view,api'); // Or stock.view if you add it
        Route::get('/export', [StockLevelController::class, 'export'])->middleware('permission:reports.export,api');
        Route::get('/warehouse/{warehouseId}/batch/{batchId}', [StockLevelController::class, 'show'])->middleware('permission:reports.view,api');
        Route::get('/scan/{barcode}', [StockLevelController::class, 'scan'])->middleware('permission:reports.view,api');
    });

    // Dashboard routes (with auth and permissions)
    Route::prefix('dashboard')->group(function () {
        Route::get('product-stock/{product_id}', [DashboardController::class, 'productStock'])->middleware('permission:reports.view,api');
        Route::get('product-stock', [DashboardController::class, 'allProductStock'])->middleware('permission:reports.view,api');
        Route::get('recent-transfers', [DashboardController::class, 'recentTransfers'])->middleware('permission:reports.view,api');
        Route::get('movement-history', [DashboardController::class, 'movementHistory'])->middleware('permission:reports.view,api');
        Route::get('warehouse-products/{warehouse_id}', [DashboardController::class, 'warehouseProducts'])->middleware('permission:reports.view,api');
    });

    Route::get('/auditlogs', [AuditLogController::class, 'index'])->middleware('permission:reports.view,api');

});
