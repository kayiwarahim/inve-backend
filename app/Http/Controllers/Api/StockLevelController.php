<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use App\Models\StockSnapshot;
use App\Services\StockLevelService;
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
        $query = StockSnapshot::with([
            'warehouse',
            'batch.product'
        ]);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    /**
     * GET /api/stock-levels/warehouse/{warehouseId}/batch/{batchId}
     * Get current stock for a batch in a warehouse
     */
    public function show(
        int $warehouseId,
        int $batchId,
        StockLevelService $service
    ) {
        return response()->json([
            'warehouse_id' => $warehouseId,
            'batch_id'     => $batchId,
            'quantity'     => $service->getWarehouseBatchStock(
                $warehouseId,
                $batchId
            )
        ]);
    }

    /**
     * GET /api/stock-levels/scan/{barcode}
     * Scan barcode and return:
     *  - batch
     *  - product
     *  - where it is currently stored
     */
    public function scan(string $barcode)
    {
        $barcode = Barcode::with('batch.product')
            ->where('code', $barcode)
            ->firstOrFail();

        $locations = StockSnapshot::with('warehouse')
            ->where('batch_id', $barcode->batch_id)
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'barcode' => $barcode->code,
            'batch'   => $barcode->batch,
            'product' => $barcode->batch->product,
            'locations' => $locations
        ]);
    }
}
