<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\ProcessStep;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('filters products by usage for frontend selectors', function () {
    $this->withoutMiddleware();

    Product::query()->create([
        'sku' => 'LIVE-01',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);
    Product::query()->create([
        'sku' => 'WASTE-01',
        'name' => 'Waste',
        'unit' => 'kg',
        'category' => 'output',
    ]);
    Product::query()->create([
        'sku' => 'WHOLE-01',
        'name' => 'Whole Chicken 1.2',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $inputResponse = $this->getJson('/api/products?usage=input');
    $inputResponse->assertOk();
    $inputResponse->assertJsonFragment(['sku' => 'LIVE-01']);
    $inputResponse->assertJsonFragment(['sku' => 'WHOLE-01']);
    $inputResponse->assertJsonMissing(['sku' => 'WASTE-01']);

    $outputResponse = $this->getJson('/api/products?usage=output');
    $outputResponse->assertOk();
    $outputResponse->assertJsonFragment(['sku' => 'WASTE-01']);
    $outputResponse->assertJsonFragment(['sku' => 'WHOLE-01']);
    $outputResponse->assertJsonMissing(['sku' => 'LIVE-01']);
});

it('rejects invalid product categories in transformation input and output sections', function () {
    $this->withoutMiddleware();
    Storage::fake('public');

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-CAT',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input Warehouse',
        'code' => 'IN-CAT',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output Warehouse',
        'code' => 'OUT-CAT',
        'type' => 'storage',
    ]);

    $outputOnlyProduct = Product::query()->create([
        'sku' => 'OUT-ONLY',
        'name' => 'Waste Output',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $inputOnlyProduct = Product::query()->create([
        'sku' => 'IN-ONLY',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);
    $bothProduct = Product::query()->create([
        'sku' => 'BOTH-01',
        'name' => 'Whole Chicken',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $outputOnlyBatch = Batch::query()->create([
        'product_id' => $outputOnlyProduct->id,
        'batch_code' => 'B-OUT-ONLY-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $outputOnlyBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 10.000,
    ]);

    $step = ProcessStep::query()->create([
        'code' => 'CUTTING',
        'name' => 'Cutting',
        'sequence' => 50,
        'is_active' => true,
    ]);

    $inputRejectedResponse = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $step->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $outputOnlyBatch->id,
                'weight_kg' => 2.000,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $inputOnlyProduct->id,
                'weight_kg' => 2.000,
            ],
        ],
    ]);

    $inputRejectedResponse->assertStatus(422);
    $inputRejectedResponse->assertJsonFragment([
        'message' => 'Selected product is not allowed as transformation input.',
    ]);

    $bothInputBatch = Batch::query()->create([
        'product_id' => $bothProduct->id,
        'batch_code' => 'B-BOTH-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $bothInputBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 10.000,
    ]);

    $outputRejectedResponse = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $step->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $bothInputBatch->id,
                'weight_kg' => 2.000,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $inputOnlyProduct->id,
                'weight_kg' => 2.000,
            ],
        ],
    ]);

    $outputRejectedResponse->assertStatus(422);
    $outputRejectedResponse->assertJsonFragment([
        'message' => 'Selected product is not allowed as transformation output.',
    ]);
});

it('rejects duplicate input batches in a single transformation payload', function () {
    $this->withoutMiddleware();
    Storage::fake('public');

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-DUP',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input Warehouse',
        'code' => 'IN-DUP',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output Warehouse',
        'code' => 'OUT-DUP',
        'type' => 'storage',
    ]);

    $inputProduct = Product::query()->create([
        'sku' => 'DUP-IN',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $outputProduct = Product::query()->create([
        'sku' => 'DUP-OUT',
        'name' => 'Processed Chicken',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $inputProduct->id,
        'batch_code' => 'B-DUP-INPUT-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 10.000,
    ]);

    $step = ProcessStep::query()->create([
        'code' => 'CUTTING',
        'name' => 'Cutting',
        'sequence' => 40,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $step->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $batch->id,
                'weight_kg' => 4.000,
            ],
            [
                'batch_id' => $batch->id,
                'weight_kg' => 4.000,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $outputProduct->id,
                'weight_kg' => 8.000,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Duplicate batch references are not allowed within a single transformation.',
        ]);
});

it('rejects duplicate output batches in a single transformation payload', function () {
    $this->withoutMiddleware();
    Storage::fake('public');

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB-OUT-DUP',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input Warehouse',
        'code' => 'IN-OUT-DUP',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output Warehouse',
        'code' => 'OUT-OUT-DUP',
        'type' => 'storage',
    ]);

    $inputProduct = Product::query()->create([
        'sku' => 'OUTDUP-IN',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $outputProduct = Product::query()->create([
        'sku' => 'OUTDUP-OUT',
        'name' => 'Processed Chicken',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $inputBatch = Batch::query()->create([
        'product_id' => $inputProduct->id,
        'batch_code' => 'B-OUT-DUP-INPUT-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $inputBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 10.000,
    ]);

    $outputBatch = Batch::query()->create([
        'product_id' => $outputProduct->id,
        'batch_code' => 'B-OUT-DUP-OUTPUT-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $step = ProcessStep::query()->create([
        'code' => 'CUTTING',
        'name' => 'Cutting',
        'sequence' => 45,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $step->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $inputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $inputBatch->id,
                'weight_kg' => 4.000,
            ],
        ],
        'outputs' => [
            [
                'batch_id' => $outputBatch->id,
                'weight_kg' => 2.000,
            ],
            [
                'batch_id' => $outputBatch->id,
                'weight_kg' => 2.000,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Duplicate output batch references are not allowed within a single transformation.',
        ]);
});
