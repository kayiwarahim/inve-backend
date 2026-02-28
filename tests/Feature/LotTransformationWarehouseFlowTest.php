<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\ProcessStep;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('consumes stock from input warehouse and posts outputs to destination warehouse', function () {
    $this->withoutMiddleware();
    Storage::fake('public');

    $branch = Branch::query()->create([
        'name' => 'Main Branch',
        'code' => 'MB1',
        'location' => 'Kampala',
    ]);

    $inputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Input Room',
        'code' => 'IN-01',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Output Room',
        'code' => 'OUT-01',
        'type' => 'storage',
    ]);

    $inputProduct = Product::query()->create([
        'sku' => 'INP-WH',
        'name' => 'Input Product',
        'unit' => 'kg',
    ]);

    $outputProduct = Product::query()->create([
        'sku' => 'OUT-WH',
        'name' => 'Output Product',
        'unit' => 'kg',
    ]);

    $inputBatch = Batch::query()->create([
        'product_id' => $inputProduct->id,
        'batch_code' => 'B-IN-WH-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $inputBatch->batchStocks()->create([
        'warehouse_id' => $inputWarehouse->id,
        'quantity' => 20.000,
    ]);

    $step = ProcessStep::query()->create([
        'code' => 'CUTTING',
        'name' => 'Cutting',
        'sequence' => 50,
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
                'weight_kg' => 8.500,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $outputProduct->id,
                'weight_kg' => 8.500,
            ],
        ],
    ]);

    $response->assertCreated();

    expect((float) $inputBatch->fresh()->batchStocks()->where('warehouse_id', $inputWarehouse->id)->firstOrFail()->quantity)
        ->toBe(11.500);

    $createdOutputBatch = Batch::query()
        ->where('product_id', $outputProduct->id)
        ->latest('id')
        ->firstOrFail();

    expect((float) $createdOutputBatch->batchStocks()->where('warehouse_id', $outputWarehouse->id)->firstOrFail()->quantity)
        ->toBe(8.500);
});

it('rejects transformation when selected input warehouse does not have enough stock for input batch', function () {
    $this->withoutMiddleware();
    Storage::fake('public');

    $branch = Branch::query()->create([
        'name' => 'Validation Branch',
        'code' => 'VB1',
        'location' => 'Mukono',
    ]);

    $warehouseWithStock = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Warehouse A',
        'code' => 'WH-A',
        'type' => 'storage',
    ]);

    $selectedInputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Warehouse B',
        'code' => 'WH-B',
        'type' => 'storage',
    ]);

    $outputWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Warehouse C',
        'code' => 'WH-C',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'VAL-01',
        'name' => 'Validation Product',
        'unit' => 'kg',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-VAL-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $warehouseWithStock->id,
        'quantity' => 5.000,
    ]);

    $step = ProcessStep::query()->create([
        'code' => 'EV_ROOM',
        'name' => 'EV Room',
        'sequence' => 20,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $step->id,
        'branch_id' => $branch->id,
        'input_warehouse_id' => $selectedInputWarehouse->id,
        'output_warehouse_id' => $outputWarehouse->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $batch->id,
                'weight_kg' => 2.000,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $product->id,
                'weight_kg' => 2.000,
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => 'Insufficient stock for one or more input lots.',
    ]);
});
