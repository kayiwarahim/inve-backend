<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('omits zero-quantity warehouses from dashboard all product stock response', function () {
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
        'sku' => 'MIX-014',
        'name' => 'Mixed portions',
        'unit' => 'kg',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-OUTPUT-20260227-185328-804279',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $coldRoomTwo->id,
        'quantity' => 9900.000,
    ]);

    $this->postJson('/api/stock/transfer/'.$batch->batch_code, [
        'from_warehouse_id' => $coldRoomTwo->id,
        'to_warehouse_id' => $coldRoomOne->id,
        'quantity' => 9900.000,
        'reason' => null,
    ])->assertOk();

    $response = $this->getJson('/api/dashboard/product-stock');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.product_name', 'Mixed portions');
    $response->assertJsonPath('0.grand_total', 9900);
    $response->assertJsonCount(1, '0.stocks');
    $response->assertJsonFragment([
        'warehouse' => 'Cold room_1',
        'branch' => 'MUKONO',
        'total_quantity' => 9900,
    ]);
});
