<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use Illuminate\Support\Facades\DB;
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
                'IN'       => $this->handleInbound($movement),
                'OUT'      => $this->handleOutbound($movement),
                'TRANSFER' => $this->handleTransfer($movement),
                default    => throw new Exception('Invalid movement type.')
            };

            // snapshots are created AFTER stock changes
            $this->createSnapshot($movement);
        });
    }

    /**
     * STOCK IN
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
     * STOCK OUT
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
     * TRANSFER between warehouses
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
     * Lock stock row or create it
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
     * Ensure sufficient stock
     */
    protected function ensureSufficientStock(Stock $stock, float $qty): void
    {
        if ($stock->quantity < $qty) {
            throw new Exception('Insufficient stock.');
        }
    }

    /**
     * Snapshot AFTER movement (reporting only)
     */
    protected function createSnapshot(StockMovement $movement): void
    {
        $warehouseIds = array_filter([
            $movement->from_warehouse_id,
            $movement->to_warehouse_id,
        ]);

        foreach ($warehouseIds as $warehouseId) {
            $stock = Stock::where('warehouse_id', $warehouseId)
                ->where('batch_id', $movement->batch_id)
                ->first();

            if ($stock) {
                StockSnapshot::create([
                    'warehouse_id'  => $warehouseId,
                    'batch_id'      => $movement->batch_id,
                    'quantity'      => $stock->quantity,
                    'snapshot_date' => now()->toDateString(),
                ]);
            }
        }
    }

    /**
     * Current stock of batch in warehouse (SOURCE OF TRUTH)
     */
    public function getWarehouseBatchStock(
        int $warehouseId,
        int $batchId
    ): float {
        return Stock::where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->value('quantity') ?? 0;
    }
}
