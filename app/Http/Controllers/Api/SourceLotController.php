<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreSourceLotRequest;
use App\Models\Batch;
use App\Models\Product;
use App\Models\SourceLot;
use App\Models\Warehouse;
use App\Services\BarcodeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SourceLotController extends Controller
{
    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        return SourceLot::query()
            ->with(['supplier', 'branch', 'batches'])
            ->latest('received_at')
            ->paginate($request->perPage())
            ->through(function (SourceLot $sourceLot): SourceLot {
                $sourceLot->setAttribute('batch_code', $sourceLot->batches->first()?->batch_code);

                return $sourceLot;
            });
    }

    public function store(StoreSourceLotRequest $request, BarcodeService $barcodeService): JsonResponse
    {
        $payload = $request->validated();

        $warehouse = Warehouse::query()->findOrFail($payload['warehouse_id']);
        if ((int) $warehouse->branch_id !== (int) $payload['branch_id']) {
            return response()->json([
                'message' => 'Warehouse does not belong to the provided branch.',
            ], 422);
        }

        $product = Product::query()->findOrFail($payload['product_id']);
        if (! $product->canBeInput()) {
            return response()->json([
                'message' => 'Selected product is not allowed as source lot input.',
            ], 422);
        }

        $result = DB::transaction(function () use ($payload, $barcodeService) {
            $sourceLot = SourceLot::query()->create([
                'supplier_id' => $payload['supplier_id'],
                'branch_id' => $payload['branch_id'],
                'source_lot_code' => $this->generateSourceLotCode(),
                'gross_car_weight_kg' => $payload['gross_car_weight_kg'],
                'chicken_tray_count' => $payload['chicken_tray_count'],
                'tray_unit_weight_kg' => $payload['tray_unit_weight_kg'],
                'received_weight_kg' => $payload['received_weight_kg'],
                'received_at' => $payload['received_at'],
                'received_by' => Auth::id(),
                'vehicle_number' => $payload['vehicle_number'] ?? null,
                'delivery_note_number' => $payload['delivery_note_number'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $batch = Batch::query()->create([
                'product_id' => $payload['product_id'],
                'source_lot_id' => $sourceLot->id,
                'batch_code' => $this->generateBatchCode(),
                'manufactured_at' => now()->toDateString(),
                'expires_at' => $payload['expires_at'] ?? null,
            ]);

            $batch->batchStocks()->create([
                'warehouse_id' => $payload['warehouse_id'],
                'quantity' => $payload['received_weight_kg'],
            ]);

            $batch->stockTransactions()->create([
                'type' => 'in',
                'quantity' => $payload['received_weight_kg'],
                'to_warehouse_id' => $payload['warehouse_id'],
                'reason' => 'Live chicken source lot intake: '.$sourceLot->source_lot_code,
                'user_id' => Auth::id(),
            ]);

            $barcodeService->generateForBatch($batch->fresh('product'));

            return [
                'source_lot' => $sourceLot->load(['supplier', 'branch']),
                'batch' => $batch->fresh(['product', 'barcode']),
            ];
        });

        return response()->json([
            'message' => 'Source lot received successfully',
            'data' => $result,
        ], 200);
    }

    public function show(SourceLot $sourceLot): JsonResponse
    {
        $sourceLot->load([
            'supplier',
            'branch',
            'transformationLines.lotTransformation.processStep',
            'transformationLines.batch.product',
        ]);

        $rootBatch = Batch::query()
            ->with(['barcode', 'batchStocks.warehouse'])
            ->where('source_lot_id', $sourceLot->id)
            ->first();

        return response()->json([
            'source_lot' => $sourceLot,
            'root_batch' => $rootBatch,
        ], 200);
    }

    private function generateBatchCode(): string
    {
        return 'BYSRC'.now()->format('YmdHisu');
    }

    private function generateSourceLotCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $sourceLotCode = 'SRC_'.now()->format('Ymd').'_'.random_int(100, 999);

            if (! SourceLot::query()->where('source_lot_code', $sourceLotCode)->exists()) {
                return $sourceLotCode;
            }
        }

        throw new RuntimeException('Unable to generate a unique source lot code.');
    }
}
