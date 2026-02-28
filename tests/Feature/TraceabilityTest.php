<?php

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ProcessStep;
use App\Models\Product;
use App\Models\SourceLot;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('traces mixed portions barcode back to multiple source lots', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Mukono Kiyunga',
        'code' => 'MUK',
        'location' => 'Mukono',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Production Room',
        'code' => 'PRD-01',
        'type' => 'storage',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier A',
        'code' => 'SUP-A',
    ]);

    $thighProduct = Product::query()->create([
        'sku' => 'THIGH',
        'name' => 'Thighs',
        'unit' => 'kg',
    ]);
    $legProduct = Product::query()->create([
        'sku' => 'LEG',
        'name' => 'Legs',
        'unit' => 'kg',
    ]);
    $mixedProduct = Product::query()->create([
        'sku' => 'MIX',
        'name' => 'Mixed Portions',
        'unit' => 'kg',
    ]);

    $sourceA = SourceLot::query()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'source_lot_code' => 'SRC-A',
        'received_weight_kg' => 100,
        'received_at' => now(),
    ]);
    $sourceB = SourceLot::query()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'source_lot_code' => 'SRC-B',
        'received_weight_kg' => 120,
        'received_at' => now(),
    ]);

    $thighBatch = Batch::query()->create([
        'product_id' => $thighProduct->id,
        'source_lot_id' => $sourceA->id,
        'batch_code' => 'B-THIGH-001',
        'manufactured_at' => now()->toDateString(),
    ]);
    $legBatch = Batch::query()->create([
        'product_id' => $legProduct->id,
        'source_lot_id' => $sourceB->id,
        'batch_code' => 'B-LEG-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $thighBatch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 60.000,
    ]);
    $legBatch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 40.000,
    ]);

    $step = ProcessStep::query()->create([
        'code' => 'MIX_BUILD',
        'name' => 'Mixed Portions Build',
        'sequence' => 60,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/lot-transformations', [
        'process_step_id' => $step->id,
        'warehouse_id' => $warehouse->id,
        'branch_id' => $branch->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            [
                'batch_id' => $thighBatch->id,
                'weight_kg' => 50.000,
            ],
            [
                'batch_id' => $legBatch->id,
                'weight_kg' => 30.000,
            ],
        ],
        'outputs' => [
            [
                'product_id' => $mixedProduct->id,
                'weight_kg' => 80.000,
            ],
        ],
    ]);

    $response->assertCreated();

    $mixedBatch = Batch::query()
        ->where('product_id', $mixedProduct->id)
        ->latest('id')
        ->firstOrFail();

    $barcode = Barcode::query()->where('batch_id', $mixedBatch->id)->firstOrFail();

    $traceResponse = $this->getJson('/api/trace/'.$barcode->code);
    $traceResponse->assertOk();
    $traceResponse->assertJsonPath('batch.batch_code', $mixedBatch->batch_code);
    $traceResponse->assertJsonCount(2, 'source_lots');
    $traceResponse->assertJsonFragment(['source_lot_code' => 'SRC-A']);
    $traceResponse->assertJsonFragment(['source_lot_code' => 'SRC-B']);
});

it('returns not found for unknown trace code', function () {
    $this->withoutMiddleware();

    $response = $this->getJson('/api/trace/UNKNOWN-CODE');

    $response->assertNotFound();
});

it('replenishes mixed portions from cut stocks and preserves multi-source traceability', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Mukono Kiyunga',
        'code' => 'MUK2',
        'location' => 'Mukono',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Production Room',
        'code' => 'PRD-02',
        'type' => 'storage',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier B',
        'code' => 'SUP-B',
    ]);

    $thighProduct = Product::query()->create([
        'sku' => 'THIGH2',
        'name' => 'Thighs',
        'unit' => 'kg',
    ]);
    $legProduct = Product::query()->create([
        'sku' => 'LEG2',
        'name' => 'Legs',
        'unit' => 'kg',
    ]);
    $mixedProduct = Product::query()->create([
        'sku' => 'MIX2',
        'name' => 'Mixed Portions',
        'unit' => 'kg',
    ]);

    $sourceA = SourceLot::query()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'source_lot_code' => 'SRC-C',
        'received_weight_kg' => 200,
        'received_at' => now(),
    ]);
    $sourceB = SourceLot::query()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'source_lot_code' => 'SRC-D',
        'received_weight_kg' => 180,
        'received_at' => now(),
    ]);

    $thighBatch = Batch::query()->create([
        'product_id' => $thighProduct->id,
        'source_lot_id' => $sourceA->id,
        'batch_code' => 'B-THIGH-002',
        'manufactured_at' => now()->toDateString(),
    ]);
    $legBatch = Batch::query()->create([
        'product_id' => $legProduct->id,
        'source_lot_id' => $sourceB->id,
        'batch_code' => 'B-LEG-002',
        'manufactured_at' => now()->toDateString(),
    ]);
    $mixedBatch = Batch::query()->create([
        'product_id' => $mixedProduct->id,
        'batch_code' => 'B-MIX-EXISTING',
        'manufactured_at' => now()->toDateString(),
    ]);

    $thighBatch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 40.000,
    ]);
    $legBatch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 50.000,
    ]);
    $mixedBatch->batchStocks()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 10.000,
    ]);

    ProcessStep::query()->create([
        'code' => 'MIX_BUILD',
        'name' => 'Mixed Portions Build',
        'sequence' => 60,
        'is_active' => true,
    ]);

    $repackResponse = $this->postJson('/api/lot-transformations/repack-mixed', [
        'warehouse_id' => $warehouse->id,
        'branch_id' => $branch->id,
        'mixed_product_id' => $mixedProduct->id,
        'mixed_batch_id' => $mixedBatch->id,
        'transformed_at' => now()->toDateTimeString(),
        'inputs' => [
            ['batch_id' => $thighBatch->id, 'weight_kg' => 15.000],
            ['batch_id' => $legBatch->id, 'weight_kg' => 10.000],
        ],
        'waste_kg' => 1.000,
    ]);

    $repackResponse->assertCreated();

    expect((float) $mixedBatch->batchStocks()->where('warehouse_id', $warehouse->id)->firstOrFail()->quantity)->toBe(34.000);
    expect((float) $thighBatch->batchStocks()->where('warehouse_id', $warehouse->id)->firstOrFail()->quantity)->toBe(25.000);
    expect((float) $legBatch->batchStocks()->where('warehouse_id', $warehouse->id)->firstOrFail()->quantity)->toBe(40.000);

    $barcode = Barcode::query()->where('batch_id', $mixedBatch->id)->firstOrFail();
    $traceResponse = $this->getJson('/api/trace/'.$barcode->code);
    $traceResponse->assertOk();
    $traceResponse->assertJsonFragment(['source_lot_code' => 'SRC-C']);
    $traceResponse->assertJsonFragment(['source_lot_code' => 'SRC-D']);
});
