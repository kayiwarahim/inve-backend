<?php

use App\Exports\StockLevelReportExport;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

it('exports current stock levels filtered by warehouse', function () {
    $this->withoutMiddleware();
    Excel::fake();

    $branch = Branch::query()->create([
        'name' => 'Kampala',
        'code' => 'KLA',
        'location' => 'Kampala',
    ]);

    $targetWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Store A',
        'code' => 'CSA',
        'type' => 'storage',
    ]);

    $otherWarehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Store B',
        'code' => 'CSB',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'SKU-EX-001',
        'name' => 'Whole Chicken',
        'unit' => 'kg',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-EX-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $targetWarehouse->id,
        'quantity' => 250.500,
    ]);

    $batch->batchStocks()->create([
        'warehouse_id' => $otherWarehouse->id,
        'quantity' => 100.000,
    ]);

    Carbon::setTestNow('2026-03-04 12:00:00');
    $response = $this->get('/api/stock-levels/export?warehouse_id='.$targetWarehouse->id);
    Carbon::setTestNow();

    $response->assertOk();

    Excel::assertDownloaded('stock-level-report-20260304_120000.xlsx', function (StockLevelReportExport $export): bool {
        $rows = $export->array();
        expect($rows)->toHaveCount(1);
        expect($rows[0][0])->toBe('current');
        expect($rows[0][5])->toBe('Cold Store A');
        expect($rows[0][6])->toBe('B-EX-001');
        expect($rows[0][11])->toBe('250.500');

        return true;
    });
});

it('exports day stock levels with opening in out and closing quantities', function () {
    $this->withoutMiddleware();
    Excel::fake();

    $branch = Branch::query()->create([
        'name' => 'Mukono',
        'code' => 'MUK',
        'location' => 'Mukono',
    ]);

    $warehouse = Warehouse::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Cold Room 1',
        'code' => 'CR1',
        'type' => 'storage',
    ]);

    $product = Product::query()->create([
        'sku' => 'SKU-EX-002',
        'name' => 'Drumsticks',
        'unit' => 'kg',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-EX-002',
        'manufactured_at' => '2026-03-01',
    ]);

    Carbon::setTestNow('2026-03-01 09:00:00');
    $batch->stockTransactions()->create([
        'type' => 'in',
        'quantity' => 100.000,
        'to_warehouse_id' => $warehouse->id,
    ]);

    Carbon::setTestNow('2026-03-02 10:00:00');
    $batch->stockTransactions()->create([
        'type' => 'out',
        'quantity' => 20.000,
        'from_warehouse_id' => $warehouse->id,
    ]);

    Carbon::setTestNow('2026-03-02 14:00:00');
    $batch->stockTransactions()->create([
        'type' => 'in',
        'quantity' => 10.000,
        'to_warehouse_id' => $warehouse->id,
    ]);
    Carbon::setTestNow();

    Carbon::setTestNow('2026-03-04 14:30:00');
    $response = $this->get('/api/stock-levels/export?day=2026-03-02&warehouse_id='.$warehouse->id);
    Carbon::setTestNow();

    $response->assertOk();

    Excel::assertDownloaded('stock-level-report-20260304_143000.xlsx', function (StockLevelReportExport $export): bool {
        $rows = $export->array();
        expect($rows)->toHaveCount(1);
        expect($rows[0][0])->toBe('day');
        expect($rows[0][1])->toBe('2026-03-02');
        expect($rows[0][8])->toBe('100.000');
        expect($rows[0][9])->toBe('10.000');
        expect($rows[0][10])->toBe('20.000');
        expect($rows[0][11])->toBe('90.000');

        return true;
    });
});

it('validates conflicting day and range filters for export', function () {
    $this->withoutMiddleware();

    $response = $this->getJson('/api/stock-levels/export?day=2026-03-02&start_date=2026-03-01&end_date=2026-03-03');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['day', 'start_date', 'end_date']);
});
