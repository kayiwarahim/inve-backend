<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Barcode;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BarcodeController extends Controller
{
    public function scan(string $barcode)
    {
        $batch = Batch::where('barcode', $barcode)
            ->with(['product', 'stockMovements'])
            ->firstOrFail();

        return [
            'batch' => $batch,
            'current_location' => $batch->currentWarehouse(),
            'movement_history' => $batch->stockMovements()->latest()->get()
        ];
    }

        protected BarcodeService $barcodeService;

    public function __construct(BarcodeService $barcodeService)
    {
        $this->barcodeService = $barcodeService;
    }

    /**
     * Generate barcode for a batch
     */
    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id'
        ]);

        $batch = Batch::with('product', 'barcode')->findOrFail($request->batch_id);

        if ($batch->barcode) {
            return response()->json([
                'message' => 'Barcode already exists for this batch'
            ], Response::HTTP_CONFLICT);
        }

        $barcode = $this->barcodeService->generateForBatch($batch);

        return response()->json([
            'message' => 'Barcode generated successfully',
            'data' => [
                'barcode_id'   => $barcode->id,
                'barcode_code' => $barcode->code,
                'barcode_type' => $barcode->type,
                'image_url'    => asset('storage/barcodes/' . $barcode->code . '.png')
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get barcode details by code (scan / manual entry)
     */
    public function showByCode(string $code)
    {
        $barcode = Barcode::with([
            'batch.product',
            'batch'
        ])->where('code', $code)->firstOrFail();

        return response()->json([
            'barcode' => $barcode->code,
            'product' => $barcode->batch->product->name,
            'sku'     => $barcode->batch->product->sku,
            'batch'   => $barcode->batch->batch_code,
            'created_at' => $barcode->generated_at
        ]);
    }
}
