<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use App\Models\BatchStock;
use Illuminate\Http\Request;

class StockLevelController extends Controller
{
    /**
     * GET /api/stock-levels
     * Optional filters:
     *  - warehouse_id
     *  - batch_id
     */
    public function index(Request $request)
    {
        $query = BatchStock::with([
            'warehouse.branch',
            'batch.product',
        ]);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * GET /api/stock-levels/warehouse/{warehouseId}/batch/{batchId}
     */
    public function show(
        int $warehouseId,
        int $batchId
    ) {
        $quantity = BatchStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->value('quantity') ?? 0;

        return response()->json([
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Scan barcode and return LIVE location
     */
    public function scan(string $barcode)
    {
        $barcode = Barcode::with('batch.product')
            ->where('code', $barcode)
            ->firstOrFail();

        $locations = BatchStock::with('warehouse.branch')
            ->where('batch_id', $barcode->batch_id)
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'barcode' => $barcode->code,
            'batch' => $barcode->batch,
            'product' => $barcode->batch->product,
            'locations' => $locations,
        ]);
    }
}
