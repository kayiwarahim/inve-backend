<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\SourceLot;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores source lot intake receiving metrics and creates stock', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Kampala Main',
        'code' => 'KLA',
        'location' => 'Kampala',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Raw Intake',
        'code' => 'RAW-01',
        'type' => 'storage',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Fresh Birds Ltd',
        'code' => 'FBL',
    ]);

    $product = Product::query()->create([
        'sku' => 'LIVE-BIRD',
        'name' => 'Live Chicken',
        'unit' => 'kg',
        'category' => 'input',
    ]);

    $response = $this->postJson('/api/source-lots', [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'gross_car_weight_kg' => 1850.500,
        'chicken_tray_count' => 120,
        'tray_unit_weight_kg' => 2.500,
        'received_weight_kg' => 300.750,
        'received_at' => now()->toDateTimeString(),
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'vehicle_number' => 'UBM 123A',
        'delivery_note_number' => 'DN-001',
        'remarks' => 'Morning intake',
    ]);

    $response->assertOk();
    expect($response->json('data.source_lot.source_lot_code'))->toStartWith('SRC-');
    $response->assertJsonPath('data.source_lot.gross_car_weight_kg', '1850.500');
    $response->assertJsonPath('data.source_lot.chicken_tray_count', 120);
    $response->assertJsonPath('data.source_lot.tray_unit_weight_kg', '2.500');
    $response->assertJsonPath('data.source_lot.received_weight_kg', '300.750');

    $sourceLot = SourceLot::query()->findOrFail($response->json('data.source_lot.id'));

    expect($sourceLot->source_lot_code)->toStartWith('SRC-')
        ->and($sourceLot->gross_car_weight_kg)->toBe('1850.500')
        ->and($sourceLot->chicken_tray_count)->toBe(120)
        ->and($sourceLot->tray_unit_weight_kg)->toBe('2.500')
        ->and($sourceLot->received_weight_kg)->toBe('300.750')
        ->and((float) $sourceLot->batches()->firstOrFail()->batchStocks()->firstOrFail()->quantity)->toBe(300.75);
});

it('lists source lots with their attached batch code', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'Mukono Intake',
        'code' => 'MUKI',
        'location' => 'Mukono',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Lake View Poultry',
        'code' => 'LVP',
    ]);

    $sourceLot = SourceLot::query()->create([
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'source_lot_code' => 'SRC-LIST-001',
        'gross_car_weight_kg' => 1600.000,
        'chicken_tray_count' => 90,
        'tray_unit_weight_kg' => 2.000,
        'received_weight_kg' => 240.000,
        'received_at' => now(),
    ]);

    $sourceLot->batches()->create([
        'product_id' => Product::query()->create([
            'sku' => 'LIVE-LIST',
            'name' => 'Live Chicken Listing',
            'unit' => 'kg',
            'category' => 'input',
        ])->id,
        'batch_code' => 'B-SRC-LIST-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $response = $this->getJson('/api/source-lots');

    $response->assertOk();
    $response->assertJsonFragment([
        'source_lot_code' => 'SRC-LIST-001',
        'batch_code' => 'B-SRC-LIST-001',
    ]);
});
