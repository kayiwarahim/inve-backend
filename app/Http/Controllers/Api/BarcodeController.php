<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use App\Models\Batch;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BarcodeController extends Controller
{
    public function __construct(protected BarcodeService $barcodeService) {}

    public function index()
    {
        return Barcode::with('batch.product')->latest()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
        ]);

        $batch = Batch::with(['product', 'barcode'])->findOrFail($request->integer('batch_id'));

        if ($batch->barcode) {
            return response()->json([
                'message' => 'Barcode already exists for this batch',
            ], Response::HTTP_CONFLICT);
        }

        $barcode = $this->barcodeService->generateForBatch($batch);

        return response()->json([
            'message' => 'Barcode generated successfully',
            'data' => [
                'barcode_id' => $barcode->id,
                'barcode_code' => $barcode->code,
                'barcode_type' => $barcode->type,
                'image_url' => asset('storage/barcodes/'.$barcode->code.'.png'),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Barcode $barcode)
    {
        return $barcode->load('batch.product');
    }

    public function update(Request $request, Barcode $barcode)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
        ]);

        $barcode->update($data);

        return $barcode->fresh('batch.product');
    }

    public function destroy(Barcode $barcode)
    {
        $barcode->delete();

        return response()->json(null, 204);
    }

    public function showByCode(string $code)
    {
        $barcode = Barcode::with('batch.product')
            ->where('code', $code)
            ->firstOrFail();

        return response()->json([
            'barcode' => $barcode->code,
            'product' => $barcode->batch->product->name,
            'sku' => $barcode->batch->product->sku,
            'batch' => $barcode->batch->batch_code,
            'created_at' => $barcode->generated_at,
        ]);
    }
}
