<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\MovementApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class StockMovementService
{
    protected StockLevelService $stockLevelService;

    public function __construct(StockLevelService $stockLevelService)
    {
        $this->stockLevelService = $stockLevelService;
    }

    /**
     * STOCK IN (production / receiving)
     */
    public function stockIn(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {

            $movement = StockMovement::create([
                'batch_id'           => $data['batch_id'],
                'to_warehouse_id'    => $data['to_warehouse_id'],
                'movement_type'      => 'IN',
                'quantity'           => $data['quantity'],
                'created_by'         => Auth::id(),
                'performed_at'       => Carbon::now(),
                'remarks'            => $data['remarks'] ?? null,
            ]);

            // IN movements usually don't need approval
            $this->applyMovementIfApproved($movement);

            return $movement;
        });
    }

    /**
     * STOCK OUT
     */
    public function stockOut(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {

            $this->ensureSufficientStock(
                $data['batch_id'],
                $data['from_warehouse_id'],
                $data['quantity']
            );

            $movement = StockMovement::create([
                'batch_id'           => $data['batch_id'],
                'from_warehouse_id'  => $data['from_warehouse_id'],
                'movement_type'      => 'OUT',
                'quantity'           => $data['quantity'],
                'created_by'         => Auth::id(),
                'performed_at'       => Carbon::now(),
                'remarks'            => $data['remarks'] ?? null,
            ]);

            $this->handleApprovalFlow($movement);

            return $movement;
        });
    }

    /**
     * TRANSFER BETWEEN WAREHOUSES
     */
    public function transfer(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {

            $this->ensureSufficientStock(
                $data['batch_id'],
                $data['from_warehouse_id'],
                $data['quantity']
            );

            $movement = StockMovement::create([
                'batch_id'           => $data['batch_id'],
                'from_warehouse_id'  => $data['from_warehouse_id'],
                'to_warehouse_id'    => $data['to_warehouse_id'],
                'movement_type'      => 'TRANSFER',
                'quantity'           => $data['quantity'],
                'created_by'         => Auth::id(),
                'performed_at'       => Carbon::now(),
                'remarks'            => $data['remarks'] ?? null,
            ]);

            $this->handleApprovalFlow($movement);

            return $movement;
        });
    }

    /**
     * Check available stock before OUT / TRANSFER
     */
    protected function ensureSufficientStock(
        int $batchId,
        int $warehouseId,
        float $quantity
    ): void {
        $available = $this->stockLevelService
            ->getWarehouseBatchStock($warehouseId, $batchId);

        if ($available < $quantity) {
            throw new Exception('Insufficient stock available.');
        }
    }

    /**
     * Approval workflow entry point
     */
    protected function handleApprovalFlow(StockMovement $movement): void
    {
        if ($this->requiresApproval($movement)) {
            MovementApproval::create([
                'stock_movement_id' => $movement->id,
                'status' => 'PENDING'
            ]);
        } else {
            $this->applyMovementIfApproved($movement);
        }
    }

    /**
     * Apply stock movement immediately (no approval required)
     */
    protected function applyMovementIfApproved(StockMovement $movement): void
    {
        match ($movement->movement_type) {
            'IN' => $this->stockLevelService->increase(
                $movement->to_warehouse_id,
                $movement->batch_id,
                $movement->quantity
            ),

            'OUT' => $this->stockLevelService->decrease(
                $movement->from_warehouse_id,
                $movement->batch_id,
                $movement->quantity
            ),

            'TRANSFER' => $this->stockLevelService->transfer(
                $movement->from_warehouse_id,
                $movement->to_warehouse_id,
                $movement->batch_id,
                $movement->quantity
            ),
        };
    }

    /**
     * Approval rules (configurable)
     */
    protected function requiresApproval(StockMovement $movement): bool
    {
        return in_array($movement->movement_type, ['OUT', 'TRANSFER'])
            && $movement->quantity > 100;
    }
}
