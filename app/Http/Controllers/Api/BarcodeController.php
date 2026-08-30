<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreBarcodeRequest;
use App\Http\Requests\Api\UpdateBarcodeRequest;
use App\Models\Barcode;
use App\Models\Batch;
use App\Services\BarcodeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;

class BarcodeController extends Controller
{
    public function __construct(protected BarcodeService $barcodeService) {}

    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        return Barcode::query()
            ->with('batch.product')
            ->latest()
            ->paginate($request->perPage());
    }

    public function store(StoreBarcodeRequest $request)
    {
        $data = $request->validated();

        $batch = Batch::with(['product', 'barcode'])->findOrFail($data['batch_id']);

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

    public function update(UpdateBarcodeRequest $request, Barcode $barcode)
    {
        $barcode->update($request->validated());

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
