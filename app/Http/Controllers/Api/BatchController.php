<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use App\Models\Batch;
use App\Services\BarcodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BatchController extends Controller
{
    public function index()
    {
        return Batch::with('product')->get();
    }

    public function store(Request $request, BarcodeService $barcodeService)
    {
        $batch = DB::transaction(function () use ($request, $barcodeService) {

            $batch = Batch::create([
                'product_id' => $request->product_id,
                'batch_code' => $this->generateBatchCode(),
                'manufactured_at' => $request->manufactured_at,
                'expires_at' => $request->expires_at,
            ]);

            $batch->batchStocks()->create([
                'warehouse_id' => $request->warehouse_id,
                'quantity' => $request->quantity,
            ]);

            $batch->stockTransactions()->create([
                'type' => 'in',
                'quantity' => $request->quantity,
                'to_warehouse_id' => $request->warehouse_id,
                'reason' => 'Initial receipt',
                'user_id' => Auth::id(),
            ]);

            // Generate barcode
            $barcodeService->generateForBatch($batch->fresh('product'));

            return $batch;
        });

        $batch->load('barcode');

        return response()->json([
            'message' => 'Batch and barcode created successfully',
            'batch' => $batch,
            'barcode' => [
                'code' => $batch->barcode->code,
                'image_url' => asset('storage/barcodes/'.$batch->barcode->code.'.png'),
            ],
        ], 200);
    }

    public function show($batch_code)
    {
        $batch = Batch::where('batch_code', $batch_code)->with('batchStocks.warehouse.branch')->firstOrFail();

        return $batch;
    }

    public function update(Request $request, $batch_code)
    {
        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();
        $batch->update($request->only(['manufactured_at', 'expires_at']));

        return $batch;
    }

    public function destroy($batch_code)
    {
        $batch = Batch::where('batch_code', $batch_code)->firstOrFail();
        $batch->delete();

        return response()->json(null, 204);
    }

    // Custom: Search by code (batch_code or barcode code)
    public function search($code)
    {
        // Try by batch_code first
        $batch = Batch::where('batch_code', $code)
            ->with(['batchStocks.warehouse.branch', 'stockTransactions', 'barcode'])
            ->first();

        // If not, try by barcode code
        if (! $batch) {
            $barcode = Barcode::where('code', $code)->first();

            if ($barcode) {
                $batch = $barcode->batch()
                    ->with(['batchStocks.warehouse.branch', 'stockTransactions', 'barcode'])
                    ->first();
            }
        }

        if (! $batch) {
            abort(404, 'Batch not found');
        }

        // Aggregate for response
        $totalQuantity = $batch->batchStocks->sum('quantity');
        $isDepleted = $totalQuantity === 0;

        $locations = $batch->batchStocks
            ->filter(fn ($stock) => (float) $stock->quantity > 0)
            ->values()
            ->map(function ($stock) {
                return [
                    'branch' => $stock->warehouse->branch->name,
                    'warehouse' => $stock->warehouse->name,
                    'quantity' => $stock->quantity,
                ];
            });

        return response()->json([
            'batch_code' => $batch->batch_code,
            'total_quantity' => $totalQuantity,
            'is_depleted' => $isDepleted,
            'locations' => $locations,
            'history' => $batch->stockTransactions->sortByDesc('created_at'),
            'barcode' => $batch->barcode, // singular
        ]);
    }

    // Helper for batch_code
    private function generateBatchCode()
    {
        return 'B-'.now()->format('Ymd-His');
    }

    public function printBarcode($id)
    {
        $batch = Batch::with('product', 'barcode')->findOrFail($id);

        $generator = new BarcodeGeneratorPNG;
        $barcodeCode = $batch->barcode->code;
        $generatorType = $this->resolveGeneratorType($barcodeCode);

        $barcodeBase64 = base64_encode(
            $generator->getBarcode(
                $barcodeCode,
                $generatorType,
                2,   // width factor
                65   // height
            )
        );

        $pdf = Pdf::loadView('pdf.batch-barcode', [
            'batch' => $batch,
            'barcode' => $barcodeBase64,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('batch-barcode-'.$batch->batch_code.'.pdf');
    }

    private function resolveGeneratorType(string $code): string
    {
        if (preg_match('/^\d+$/', $code) === 1 && strlen($code) % 2 === 0) {
            return BarcodeGeneratorPNG::TYPE_CODE_128_C;
        }

        return BarcodeGeneratorPNG::TYPE_CODE_128;
    }
}
