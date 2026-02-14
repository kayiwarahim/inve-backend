<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\BarcodeService;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * GET /api/batches
     */
    public function index()
    {
        return response()->json(
            Batch::with('product', 'barcode')->get(),
            200
        );
    }

    /**
     * POST /api/batches
     * Create batch + barcode
     */
    public function store(Request $request, BarcodeService $barcodeService)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'manufactured_at' => 'required|date',
            'expires_at' => 'nullable|date'
        ]);

        $batch = Batch::create([
            'product_id' => $data['product_id'],
            'batch_code' => 'B-' . now()->format('Ymd-His'),
            'manufactured_at' => $data['manufactured_at'],
            'expires_at' => $data['expires_at'] ?? null
        ]);


        $barcode = $barcodeService->generateForBatch($batch);

        return response()->json([
            'message' => 'Batch and barcode created successfully',
            'batch' => $batch->load('product'),
            'barcode' => [
                'code' => $barcode->code,
                'image_url' => asset('storage/barcodes/' . $barcode->code . '.png')
            ]
        ], 200);
    }

    /**
     * GET /api/batches/{id}
     */
    public function show($id)
    {
        return response()->json(
            Batch::with('product', 'barcode')->findOrFail($id),
            200
        );
    }

    /**
     * PUT /api/batches/{id}
     */
    public function update(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'manufactured_at' => 'required|date',
            'expires_at' => 'nullable|date'
        ]);

        $batch->update($data);

        return response()->json([
            'message' => 'Batch updated successfully',
            'data' => $batch
        ], 200);
    }

    /**
     * DELETE /api/batches/{id}
     */
    public function destroy($id)
    {
        Batch::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Batch deleted successfully'
        ], 200);
    }
}

