<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use Illuminate\Support\Facades\DB;

class ScanController extends Controller
{
    /**
     * Scan batch barcode / QR
     * GET /api/scan/{code}
     */
    public function scan(string $code)
    {
        $barcode = Barcode::with([
            'batch.product',
        ])->where('code', $code)->first();

        if (! $barcode) {
            return response()->json([
                'message' => 'Barcode not found',
            ], 404);
        }

        $batch = $barcode->batch;

        // Get current locations (REAL TIME)
        $locations = DB::table('batch_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'batch_stocks.warehouse_id')
            ->where('batch_stocks.batch_id', $batch->id)
            ->where('batch_stocks.quantity', '>', 0)
            ->select(
                'warehouses.id',
                'warehouses.name',
                'batch_stocks.quantity'
            )
            ->get();

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'manufactured_at' => $batch->manufactured_at,
                'expires_at' => $batch->expires_at,
            ],
            'product' => [
                'id' => $batch->product->id,
                'name' => $batch->product->name,
                'sku' => $batch->product->sku ?? null,
            ],
            'locations' => $locations,
        ]);
    }
}
