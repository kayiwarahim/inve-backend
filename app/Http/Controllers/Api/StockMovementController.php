<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockInRequest;
use App\Http\Requests\Api\StockOutRequest;
use App\Http\Requests\Api\TransferStockRequest;
use App\Models\Batch;
use App\Models\BatchStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    // Custom: Stock In
    public function stockIn(StockInRequest $request, $batch_code)
    {
        $data = $request->validated();

        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();

        DB::transaction(function () use ($batch, $data) {
            $stock = $batch->batchStocks()->firstOrCreate(
                ['warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0]
            );
            $stock->increment('quantity', (float) $data['quantity']);

            $batch->stockTransactions()->create([
                'type' => 'in',
                'quantity' => $data['quantity'],
                'to_warehouse_id' => $data['warehouse_id'],
                'reason' => $data['reason'] ?? 'Stock in',
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($batch->fresh(), 200);
    }

    // Custom: Stock Out
    public function stockOut(StockOutRequest $request, $batch_code)
    {
        $data = $request->validated();

        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();

        DB::transaction(function () use ($batch, $data) {
            $stock = $batch->batchStocks()
                ->where('warehouse_id', $data['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                abort(422, 'No stock found for the specified warehouse.');
            }

            if ((float) $stock->quantity < (float) $data['quantity']) {
                abort(422, 'Insufficient quantity available for stock out.');
            }

            $stock->decrement('quantity', (float) $data['quantity']);
            if ((float) $stock->fresh()->quantity <= 0.000) {
                $stock->delete();
            }

            $batch->stockTransactions()->create([
                'type' => 'out',
                'quantity' => $data['quantity'],
                'from_warehouse_id' => $data['warehouse_id'],
                'reason' => $data['reason'] ?? 'Stock out',
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($batch->fresh(), 200);
    }

    // Custom: Transfer
    public function transfer(TransferStockRequest $request, $batch_code)
    {
        $data = $request->validated();

        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();

        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            return response()->json([
                'message' => 'Transfer destination must be different from the source warehouse.',
            ], 422);
        }

        DB::transaction(function () use ($batch, $data) {
            $fromStock = $batch->batchStocks()
                ->where('warehouse_id', $data['from_warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (! $fromStock) {
                abort(422, 'No stock found in the source warehouse.');
            }

            if ((float) $fromStock->quantity < (float) $data['quantity']) {
                abort(422, 'Insufficient quantity available for transfer.');
            }

            $fromStock->decrement('quantity', (float) $data['quantity']);
            if ((float) $fromStock->fresh()->quantity <= 0.000) {
                $fromStock->delete();
            }

            $toStock = BatchStock::query()
                ->where('batch_id', $batch->id)
                ->where('warehouse_id', $data['to_warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (! $toStock) {
                $toStock = $batch->batchStocks()->create([
                    'warehouse_id' => $data['to_warehouse_id'],
                    'quantity' => 0,
                ]);
            }

            $toStock->increment('quantity', (float) $data['quantity']);

            $batch->stockTransactions()->create([
                'type' => 'transfer',
                'quantity' => $data['quantity'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'reason' => $data['reason'] ?? 'Stock transfer',
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($batch->fresh(), 200);
    }
}
