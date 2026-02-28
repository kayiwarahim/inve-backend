<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not return zero-quantity locations after transferring all stock out of a warehouse', function () {
    $this->withoutMiddleware();

    $branch = Branch::query()->create([
        'name' => 'MUKONO',
        'code' => 'MUK',
        'location' => 'Mukono',
    ]);

    $coldRoomOne = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold room_1',
        'code' => 'CR-1',
        'type' => 'storage',
    ]);

    $coldRoomTwo = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold room_2',
        'code' => 'CR-2',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'SKU-014',
        'name' => 'Output Product',
        'unit' => 'kg',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-OUTPUT-20260227-185328-804279',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $coldRoomTwo->id,
        'quantity' => 3300.000,
    ]);

    $transferResponse = $this->postJson('/api/stock/transfer/'.$batch->batch_code, [
        'from_warehouse_id' => $coldRoomTwo->id,
        'to_warehouse_id' => $coldRoomOne->id,
        'quantity' => 3300.000,
        'reason' => null,
    ]);

    $transferResponse->assertOk();

    expect($batch->fresh()->batchStocks()->where('warehouse_id', $coldRoomTwo->id)->exists())->toBeFalse();
    expect((float) $batch->fresh()->batchStocks()->where('warehouse_id', $coldRoomOne->id)->firstOrFail()->quantity)->toBe(3300.000);

    $searchResponse = $this->getJson('/api/search/'.$batch->batch_code);
    $searchResponse->assertOk();
    $searchResponse->assertJsonPath('total_quantity', 3300);
    $searchResponse->assertJsonPath('is_depleted', false);
    $searchResponse->assertJsonCount(1, 'locations');
    $searchResponse->assertJsonFragment([
        'branch' => 'MUKONO',
        'warehouse' => 'Cold room_1',
        'quantity' => '3300.000',
    ]);
});
