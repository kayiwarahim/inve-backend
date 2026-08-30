<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\LotTransformation;
use App\Models\ProcessStep;
use App\Models\Product;
use App\Models\SourceLot;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('rejects invalid stock movement payloads', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-INV',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Room',
        'code' => 'CR-INV',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'INV-001',
        'name' => 'Whole Chicken',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-INV-VALID-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 20.000,
    ]);

    $invalidStockIn = $this->postJson('/api/stock/in/'.$batch->batch_code, [
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
        'reason' => 'Invalid stock in',
    ]);

    $invalidStockIn->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);

    $invalidTransfer = $this->postJson('/api/stock/transfer/'.$batch->batch_code, [
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'reason' => 'Same warehouse transfer',
    ]);

    $invalidTransfer->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Transfer destination must be different from the source warehouse.',
        ]);
});

it('rejects invalid batch creation payloads', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-BATCH',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Room',
        'code' => 'CR-BATCH',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'BATCH-001',
        'name' => 'Whole Chicken',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $response = $this->postJson('/api/batches', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
        'manufactured_at' => now()->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);
});

it('rejects source lot intake when the warehouse does not belong to the selected branch', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-SRC',
        'location' => 'Kampala',
    ]);

    $otherBranch = Branch::query()->create([
        'name' => 'Other Branch',
        'code' => 'OB-SRC',
        'location' => 'Jinja',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $otherBranch->id,
        'name' => 'Wrong Branch Warehouse',
        'code' => 'WB-SRC',
        'type' => 'storage',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier Co',
        'code' => 'SUP-SRC',
    ]);

    $product = Product::query()->create([
        'sku' => 'SRC-001',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $response = $this->postJson('/api/source-lots', [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'gross_car_weight_kg' => 1800,
        'chicken_tray_count' => 100,
        'tray_unit_weight_kg' => 2.5,
        'received_weight_kg' => 250,
        'received_at' => now()->toDateTimeString(),
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('requires a product category when creating or updating a product', function () {
    $this->withoutMiddleware();

    $invalidCreate = $this->postJson('/api/products', [
        'sku' => 'PROD-NULL-CAT',
        'name' => 'Missing Category',
        'unit' => 'kg',
    ]);

    $invalidCreate->assertStatus(422)
        ->assertJsonValidationErrors(['category']);

    $product = Product::query()->create([
        'sku' => 'PROD-VALID',
        'name' => 'Valid Product',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $invalidUpdate = $this->putJson('/api/products/'.$product->id, [
        'sku' => 'PROD-VALID-UPDATED',
        'name' => 'Updated Product',
        'unit' => 'kg',
        'category' => null,
    ]);

    $invalidUpdate->assertStatus(422)
        ->assertJsonValidationErrors(['category']);
});

it('rejects source lot metrics that fail consistency checks', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-METRIC',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Room',
        'code' => 'CR-METRIC',
        'type' => 'storage',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier Co',
        'code' => 'SUP-METRIC',
    ]);

    $product = Product::query()->create([
        'sku' => 'METRIC-001',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    // Test 1: Gross weight less than received weight (impossible).
    $invalidGrossWeight = $this->postJson('/api/source-lots', [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'gross_car_weight_kg' => 100,
        'chicken_tray_count' => 50,
        'tray_unit_weight_kg' => 2.5,
        'received_weight_kg' => 150,
        'received_at' => now()->toDateTimeString(),
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    $invalidGrossWeight->assertStatus(422)
        ->assertJsonValidationErrors(['gross_car_weight_kg']);

    // Test 2: Tray metrics do not align with received weight (outside tolerance).
    $invalidTrayMetrics = $this->postJson('/api/source-lots', [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'gross_car_weight_kg' => 1800,
        'chicken_tray_count' => 10,
        'tray_unit_weight_kg' => 2.5,
        'received_weight_kg' => 300,
        'received_at' => now()->toDateTimeString(),
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    $invalidTrayMetrics->assertStatus(422)
        ->assertJsonValidationErrors(['received_weight_kg']);
});

it('rejects batch deletion when batch has active stock', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-DEL',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Room',
        'code' => 'CR-DEL',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'DEL-001',
        'name' => 'Whole Chicken',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-DEL-WITH-STOCK',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 50.000,
    ]);

    $response = $this->deleteJson('/api/batches/'.$batch->batch_code);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete batch with active stock.',
        ]);
});

it('rejects product deletion when product has active batches', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-PROD-DEL',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Room',
        'code' => 'CR-PROD-DEL',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'PROD-DEL-001',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-PROD-DEL-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 25.000,
    ]);

    $response = $this->deleteJson('/api/products/'.$product->id);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete product with active batches in the system.',
        ]);
});

it('rejects warehouse deletion when warehouse has batch stock', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-WHSE-DEL',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Storage Warehouse',
        'code' => 'SW-DEL',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'WHSE-DEL-001',
        'name' => 'Test Product',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-WHSE-DEL-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 30.000,
    ]);

    $response = $this->deleteJson('/api/warehouses/'.$warehouse->id);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete warehouse with active stock.',
        ]);
});

it('rejects supplier deletion when supplier has source lots', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-SUP-DEL',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Intake Warehouse',
        'code' => 'IW-DEL',
        'type' => 'storage',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Test Supplier',
        'code' => 'TS-DEL',
    ]);

    $product = Product::query()->create([
        'sku' => 'SUP-DEL-001',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $sourceLot = SourceLot::query()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'source_lot_code' => 'SRC-DEL-001',
        'gross_car_weight_kg' => 1800,
        'chicken_tray_count' => 100,
        'tray_unit_weight_kg' => 2.5,
        'received_weight_kg' => 250,
        'received_at' => now(),
    ]);

    // Verify source lot exists and is linked to supplier
    expect($supplier->sourceLots()->exists())->toBeTrue();

    $response = $this->deleteJson('/api/suppliers/'.$supplier->id);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete supplier with active source lots.',
        ]);
});

it('rejects branch deletion when branch has active warehouses', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-BRANCH-DEL',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Storage',
        'code' => 'STG-BRANCH-DEL',
        'type' => 'storage',
    ]);

    $response = $this->deleteJson('/api/branches/'.$branch->id);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete branch with active warehouses.',
        ]);
});

it('rejects transformation with expired input batch', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-EXP',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Storage',
        'code' => 'CS-EXP',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'PROD-EXP-001',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $expiredBatch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-EXP-001',
        'manufactured_at' => now()->subDays(10)->toDateString(),
        'expires_at' => now()->subDays(5)->toDateString(), // expired 5 days ago
    ]);

    $expiredBatch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 25.000,
    ]);

    $processStep = ProcessStep::query()->create([
        'code' => 'TEST_STEP_EXP',
        'name' => 'Test Process',
        'sequence' => 1,
    ]);

    $response = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $processStep->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $warehouse->id,
        'output_warehouse_id' => $warehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $expiredBatch->id,
                'weight_kg' => 10,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $product->id,
                'weight_kg' => 10,
            ],
        ],
        'byproducts' => [],
        'waste' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot use expired batch in transformation.',
        ]);
});

it('rejects transformation output batch with invalid dates', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-DATE-VAL',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input Warehouse',
        'code' => 'IW-DATE-VAL',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output Warehouse',
        'code' => 'OW-DATE-VAL',
        'type' => 'storage',
    ]);

    $inputProduct = Product::query()->create([
        'sku' => 'INPUT-DATE-001',
        'name' => 'Input Product',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $outputProduct = Product::query()->create([
        'sku' => 'OUTPUT-DATE-001',
        'name' => 'Output Product',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $inputBatch = Batch::query()->create([
        'product_id' => $inputProduct->id,
        'batch_code' => 'B-INPUT-DATE-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $inputBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 50.000,
    ]);

    $processStep = ProcessStep::query()->create([
        'code' => 'TEST_DATE_VAL',
        'name' => 'Date Validation Test',
        'sequence' => 1,
    ]);

    $response = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $processStep->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $inputBatch->id,
                'weight_kg' => 30,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $outputProduct->id,
                'weight_kg' => 30,
                'manufactured_at' => now()->addDays(5)->toDateString(),
                'expires_at' => now()->toDateString(), // expires before manufactured_at
            ],
        ],
        'byproducts' => [],
        'waste' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Output batch expiration date cannot be before manufactured date.',
        ]);
});

it('prevents batch stock quantity from becoming negative', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-NEG-QTY',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Storage',
        'code' => 'ST-NEG',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'NEG-QTY-001',
        'name' => 'Test Product',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-NEG-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 10.000, // 10 kg
    ]);

    // Try to stock out more than available
    $response = $this->postJson('/api/stock/out/'.$batch->batch_code, [
        'warehouse_id' => $warehouse->id,
        'quantity' => 15.000, // More than available
        'reason' => 'Test over-withdrawal',
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Insufficient quantity available for stock out.',
        ]);

    // Verify stock is still 10, not negative
    $batch->refresh();
    $stock = $batch->batchStocks()->where('warehouse_id', $warehouse->id)->first();
    expect((float) $stock->quantity)->toBe(10.0);
});

it('rejects process step deletion when it is used in transformations', function () {
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test-proc-step@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::query()->create([
        'name' => 'process-steps.delete',
        'guard_name' => 'api',
    ]);
    $user->givePermissionTo('process-steps.delete');
    Sanctum::actingAs($user);
    $this->actingAs($user, 'api');

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-PROC-STEP',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input',
        'code' => 'IW-PROC-STEP',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output',
        'code' => 'OW-PROC-STEP',
        'type' => 'storage',
    ]);

    $inputProduct = Product::query()->create([
        'sku' => 'INPUT-PROC-STEP',
        'name' => 'Input Product',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $outputProduct = Product::query()->create([
        'sku' => 'OUTPUT-PROC-STEP',
        'name' => 'Output Product',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $inputBatch = Batch::query()->create([
        'product_id' => $inputProduct->id,
        'batch_code' => 'B-INPUT-PROC-STEP',
        'manufactured_at' => now()->toDateString(),
    ]);

    $inputBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 50.000,
    ]);

    $processStep = ProcessStep::query()->create([
        'code' => 'PROC_TEST',
        'name' => 'Process Test',
        'sequence' => 1,
    ]);

    // Create a transformation using this process step
    $transformation = LotTransformation::query()->create([
        'transformation_code' => 'TR-PROC-TEST-001',
        'process_step_id' => $processStep->id,
        'warehouse_id' => $outputWarehouse->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'branch_id' => $branch->id,
        'performed_by' => $user->id,
        'transformed_at' => now(),
    ]);

    $transformation->lines()->create([
        'line_type' => 'output',
        'product_id' => $outputProduct->id,
        'weight_kg' => 30,
    ]);

    // Verify transformation was created with correct process_step_id
    expect(LotTransformation::where('process_step_id', $processStep->id)->exists())->toBeTrue();

    // Verify the process step has transformations
    expect($processStep->lotTransformations()->count())->toBeGreaterThan(0);

    // Get a fresh count before the request
    $countBefore = LotTransformation::where('process_step_id', $processStep->id)->count();
    expect($countBefore)->toBeGreaterThan(0);

    // Now try to delete the process step - should fail
    $response = $this->deleteJson('/api/process-steps/'.$processStep->id);

    // Verify transformation still exists after failed deletion
    $countAfter = LotTransformation::where('process_step_id', $processStep->id)->count();
    expect($countAfter)->toEqual($countBefore);

    // Debug: Let's see what response we get
    if ($response->status() !== 422) {
        $this->fail('Expected 422 but got '.$response->status().'. Response: '.$response->content());
    }

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete process step that is used in transformations.',
        ]);
});

it('rejects product deletion when product is used in transformation lines', function () {
    $this->withoutMiddleware();

    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test-prod-trans@example.com',
        'password' => bcrypt('password'),
    ]);

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-PROD-TRANS',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input',
        'code' => 'IW-PROD-TRANS',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output',
        'code' => 'OW-PROD-TRANS',
        'type' => 'storage',
    ]);

    $inputProduct = Product::query()->create([
        'sku' => 'INPUT-PROD-TRANS',
        'name' => 'Input',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $outputProduct = Product::query()->create([
        'sku' => 'OUTPUT-PROD-TRANS',
        'name' => 'Output',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $inputBatch = Batch::query()->create([
        'product_id' => $inputProduct->id,
        'batch_code' => 'B-INPUT-PROD-TRANS',
        'manufactured_at' => now()->toDateString(),
    ]);

    $inputBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 50.000,
    ]);

    $processStep = ProcessStep::query()->create([
        'code' => 'PROD_TRANS_TEST',
        'name' => 'Product Transformation Test',
        'sequence' => 1,
    ]);

    // Create a transformation that uses outputProduct
    $transformation = LotTransformation::query()->create([
        'transformation_code' => 'TR-PROD-TEST-001',
        'process_step_id' => $processStep->id,
        'warehouse_id' => $outputWarehouse->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'branch_id' => $branch->id,
        'performed_by' => $user->id,
        'transformed_at' => now(),
    ]);

    // Add a line using the output product
    $transformation->lines()->create([
        'line_type' => 'output',
        'product_id' => $outputProduct->id,
        'weight_kg' => 30,
    ]);

    // Now try to delete the output product - should fail
    $response = $this->deleteJson('/api/products/'.$outputProduct->id);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete product that is used in transformations.',
        ]);
});
