<?php


namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class StockLevelService
{
    /**
     * Apply an approved stock movement
     */
    public function applyMovement(StockMovement $movement): void
    {
        if ($movement->status !== 'approved') {
            throw new Exception('Stock movement not approved.');
        }

        DB::transaction(function () use ($movement) {

            match ($movement->movement_type) {
                'INBOUND'     => $this->handleInbound($movement),
                'OUTBOUND'    => $this->handleOutbound($movement),
                'TRANSFER'    => $this->handleTransfer($movement),
                'ADJUSTMENT'  => $this->handleAdjustment($movement),
                default       => throw new Exception('Invalid movement type.')
            };

            $this->createSnapshot($movement);
        });
    }

    /**
     * INBOUND stock (production / purchase)
     */
    protected function handleInbound(StockMovement $movement): void
    {
        $stock = $this->getStockForUpdate(
            $movement->to_warehouse_id,
            $movement->batch_id
        );

        $stock->increment('quantity', $movement->quantity);
    }

    /**
     * OUTBOUND stock (sale / disposal)
     */
    protected function handleOutbound(StockMovement $movement): void
    {
        $stock = $this->getStockForUpdate(
            $movement->from_warehouse_id,
            $movement->batch_id
        );

        $this->ensureSufficientStock($stock, $movement->quantity);

        $stock->decrement('quantity', $movement->quantity);
    }

    /**
     * TRANSFER stock between warehouses
     */
    protected function handleTransfer(StockMovement $movement): void
    {
        $fromStock = $this->getStockForUpdate(
            $movement->from_warehouse_id,
            $movement->batch_id
        );

        $this->ensureSufficientStock($fromStock, $movement->quantity);

        $toStock = $this->getStockForUpdate(
            $movement->to_warehouse_id,
            $movement->batch_id
        );

        $fromStock->decrement('quantity', $movement->quantity);
        $toStock->increment('quantity', $movement->quantity);
    }

    /**
     * ADJUSTMENT stock (cycle count corrections)
     */
    protected function handleAdjustment(StockMovement $movement): void
    {
        $stock = $this->getStockForUpdate(
            $movement->to_warehouse_id,
            $movement->batch_id
        );

        if ($movement->quantity < 0 && abs($movement->quantity) > $stock->quantity) {
            throw new Exception('Adjustment would cause negative stock.');
        }

        $stock->quantity += $movement->quantity;
        $stock->save();
    }

    /**
     * Lock stock row for update or create new
     */
    protected function getStockForUpdate(int $warehouseId, int $batchId): Stock
    {
        return Stock::where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->lockForUpdate()
            ->firstOrCreate(
                ['warehouse_id' => $warehouseId, 'batch_id' => $batchId],
                ['quantity' => 0]
            );
    }

    /**
     * Ensure stock is sufficient
     */
    protected function ensureSufficientStock(Stock $stock, int $qty): void
    {
        if ($stock->quantity < $qty) {
            throw new Exception('Insufficient stock.');
        }
    }

    /**
     * Snapshot stock after movement (performance optimization)
     */
    protected function createSnapshot(StockMovement $movement): void
    {
        $warehouseIds = array_filter([
            $movement->from_warehouse_id,
            $movement->to_warehouse_id
        ]);

        foreach ($warehouseIds as $warehouseId) {
            $stock = Stock::where('warehouse_id', $warehouseId)
                ->where('batch_id', $movement->batch_id)
                ->first();

            if ($stock) {
                StockSnapshot::create([
                    'warehouse_id' => $warehouseId,
                    'batch_id'     => $movement->batch_id,
                    'quantity'     => $stock->quantity,
                    'snapshot_date'=> now()->toDateString()
                ]);
            }
        }
    }

    /**
     * Get current stock of a batch in a warehouse
     */
    public function getWarehouseBatchStock(
        int $warehouseId,
        int $batchId
    ): float {
        return StockSnapshot::where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->value('quantity') ?? 0;
    }

    /**
     * Increase stock (Stock IN)
     */
    public function increase(
        int $warehouseId,
        int $batchId,
        float $quantity
    ): void {
        $this->updateSnapshot(
            $warehouseId,
            $batchId,
            $quantity
        );
    }

    /**
     * Decrease stock (Stock OUT)
     */
    public function decrease(
        int $warehouseId,
        int $batchId,
        float $quantity
    ): void {
        $this->updateSnapshot(
            $warehouseId,
            $batchId,
            -$quantity
        );
    }

    /**
     * Transfer stock between warehouses
     */
    public function transfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $batchId,
        float $quantity
    ): void {
        DB::transaction(function () use (
            $fromWarehouseId,
            $toWarehouseId,
            $batchId,
            $quantity
        ) {
            $this->decrease($fromWarehouseId, $batchId, $quantity);
            $this->increase($toWarehouseId, $batchId, $quantity);
        });
    }

    /**
     * Update or create snapshot atomically
     */
    protected function updateSnapshot(
        int $warehouseId,
        int $batchId,
        float $delta
    ): void {
        StockSnapshot::updateOrCreate(
            [
                'warehouse_id' => $warehouseId,
                'batch_id'     => $batchId,
            ],
            [
                'quantity'     => DB::raw("quantity + ($delta)"),
                'updated_at'   => Carbon::now(),
            ]
        );
    }
}
