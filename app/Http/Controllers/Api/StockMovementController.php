<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    // Custom: Stock In
    public function stockIn(Request $request, $batch_code)
    {
        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();
        // Validate: warehouse_id, quantity > 0
        DB::transaction(function () use ($batch, $request) {
            $stock = $batch->batchStocks()->firstOrCreate(
                ['warehouse_id' => $request->warehouse_id],
                ['quantity' => 0]
            );
            $stock->increment('quantity', $request->quantity);

            $batch->stockTransactions()->create([
                'type' => 'in',
                'quantity' => $request->quantity,
                'to_warehouse_id' => $request->warehouse_id,
                'reason' => $request->reason,
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($batch->fresh(), 200);
    }

    // Custom: Stock Out
    public function stockOut(Request $request, $batch_code)
    {
        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();
        // Validate: warehouse_id, quantity <= available
        DB::transaction(function () use ($batch, $request) {
            $stock = $batch->batchStocks()->where('warehouse_id', $request->warehouse_id)->firstOrFail();
            if ($stock->quantity < $request->quantity) {
                abort(422, 'Insufficient quantity');
            }
            $stock->decrement('quantity', $request->quantity);
            if ((float) $stock->fresh()->quantity <= 0.000) {
                $stock->delete();
            }

            $batch->stockTransactions()->create([
                'type' => 'out',
                'quantity' => $request->quantity,
                'from_warehouse_id' => $request->warehouse_id,
                'reason' => $request->reason,
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($batch->fresh(), 200);
    }

    // Custom: Transfer
    public function transfer(Request $request, $batch_code)
    {
        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();
        // Validate: from_warehouse_id, to_warehouse_id, quantity <= available
        DB::transaction(function () use ($batch, $request) {
            $fromStock = $batch->batchStocks()->where('warehouse_id', $request->from_warehouse_id)->firstOrFail();
            if ($fromStock->quantity < $request->quantity) {
                abort(422, 'Insufficient quantity');
            }
            $fromStock->decrement('quantity', $request->quantity);
            if ((float) $fromStock->fresh()->quantity <= 0.000) {
                $fromStock->delete();
            }

            $toStock = $batch->batchStocks()->firstOrCreate(
                ['warehouse_id' => $request->to_warehouse_id],
                ['quantity' => 0]
            );
            $toStock->increment('quantity', $request->quantity);

            $batch->stockTransactions()->create([
                'type' => 'transfer',
                'quantity' => $request->quantity,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'reason' => $request->reason,
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($batch->fresh(), 200);
    }
}
