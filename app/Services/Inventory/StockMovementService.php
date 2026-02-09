<?php

namespace App\Services\Inventory;

use App\Models\StockMovement;
use App\Models\MovementApproval;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockMovementService
{
    /**
     * Stock IN (production / receiving)
     */
    public function stockIn(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {

            $movement = StockMovement::create([
                'batch_id'        => $data['batch_id'],
                'to_warehouse_id'=> $data['to_warehouse_id'],
                'movement_type'  => 'IN',
                'quantity'       => $data['quantity'],
                'performed_by'   => auth()->id(),
                'performed_at'   => Carbon::now(),
                'remarks'        => $data['remarks'] ?? null,
            ]);

            $this->createApprovalIfRequired($movement);

            return $movement;
        });
    }

    /**
     * Stock OUT
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
                'batch_id'         => $data['batch_id'],
                'from_warehouse_id'=> $data['from_warehouse_id'],
                'movement_type'   => 'OUT',
                'quantity'        => $data['quantity'],
                'performed_by'    => auth()->id(),
                'performed_at'    => Carbon::now(),
                'remarks'         => $data['remarks'] ?? null,
            ]);

            $this->createApprovalIfRequired($movement);

            return $movement;
        });
    }

    /**
     * Warehouse Transfer
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
                'batch_id'          => $data['batch_id'],
                'from_warehouse_id'=> $data['from_warehouse_id'],
                'to_warehouse_id'  => $data['to_warehouse_id'],
                'movement_type'    => 'TRANSFER',
                'quantity'         => $data['quantity'],
                'performed_by'     => auth()->id(),
                'performed_at'     => Carbon::now(),
                'remarks'          => $data['remarks'] ?? null,
            ]);

            $this->createApprovalIfRequired($movement);

            return $movement;
        });
    }

    /**
     * Prevent negative stock
     */
    protected function ensureSufficientStock(
        int $batchId,
        int $warehouseId,
        float $quantity
    ): void {
        $available = app(StockLevelService::class)
            ->getWarehouseBatchStock($warehouseId, $batchId);

        if ($available < $quantity) {
            throw new \Exception('Insufficient stock available.');
        }
    }

    /**
     * Approval logic hook
     */
    protected function createApprovalIfRequired(StockMovement $movement): void
    {
        if ($this->requiresApproval($movement)) {
            MovementApproval::create([
                'stock_movement_id' => $movement->id,
                'status' => 'PENDING'
            ]);
        }
    }

    /**
     * Approval rules
     */
    protected function requiresApproval(StockMovement $movement): bool
    {
        return in_array($movement->movement_type, ['OUT', 'TRANSFER'])
            && $movement->quantity > 100; // configurable rule
    }
}
