<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RepackMixedPortionsRequest;
use App\Http\Requests\Api\StoreLotTransformationRequest;
use App\Models\Batch;
use App\Models\LotTransformation;
use App\Models\ProcessStep;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LotTransformationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = LotTransformation::query()
            ->with(['processStep', 'warehouse', 'inputWarehouse', 'outputWarehouse', 'branch', 'lines.batch.product', 'lines.sourceLot'])
            ->latest('transformed_at')
            ->get();

        return response()->json($items, 200);
    }

    public function store(StoreLotTransformationRequest $request, BarcodeService $barcodeService): JsonResponse
    {
        $payload = $request->validated();
        $validationError = $this->validateWarehouseAndMassBalance($payload);
        if ($validationError instanceof JsonResponse) {
            return $validationError;
        }

        $processStep = ProcessStep::query()->findOrFail($payload['process_step_id']);
        $transformation = $this->persistTransformation($payload, $processStep, $barcodeService);

        return response()->json([
            'message' => 'Transformation posted successfully',
            'data' => $transformation,
        ], 201);
    }

    public function repackMixedPortions(RepackMixedPortionsRequest $request, BarcodeService $barcodeService): JsonResponse
    {
        $payload = $request->validated();
        $mixBuildStep = ProcessStep::query()->where('code', 'MIX_BUILD')->first();
        if (! $mixBuildStep) {
            return response()->json([
                'message' => 'Process step MIX_BUILD is not configured.',
            ], 422);
        }

        $inputWeight = collect($payload['inputs'])->sum(fn (array $line): float => (float) $line['weight_kg']);
        $wasteWeight = (float) ($payload['waste_kg'] ?? 0);
        $outputWeight = round($inputWeight - $wasteWeight, 3);
        if ($outputWeight <= 0) {
            return response()->json([
                'message' => 'Repack output weight must be greater than zero.',
            ], 422);
        }

        $transformationPayload = [
            'process_step_id' => $mixBuildStep->id,
            'warehouse_id' => $payload['warehouse_id'] ?? $payload['output_warehouse_id'] ?? $payload['input_warehouse_id'],
            'input_warehouse_id' => $payload['input_warehouse_id'] ?? $payload['warehouse_id'],
            'output_warehouse_id' => $payload['output_warehouse_id'] ?? $payload['warehouse_id'],
            'branch_id' => $payload['branch_id'],
            'transformed_at' => $payload['transformed_at'],
            'remarks' => $payload['remarks'] ?? 'Mixed portions replenishment',
            'inputs' => $payload['inputs'],
            'outputs' => [
                [
                    'batch_id' => $payload['mixed_batch_id'] ?? null,
                    'product_id' => $payload['mixed_product_id'],
                    'weight_kg' => $outputWeight,
                    'remarks' => 'Mixed portions replenishment output',
                ],
            ],
            'byproducts' => [],
            'waste' => $wasteWeight > 0 ? [[
                'product_id' => $payload['waste_product_id'] ?? null,
                'weight_kg' => $wasteWeight,
                'remarks' => 'Repacking loss',
            ]] : [],
        ];

        $validationError = $this->validateWarehouseAndMassBalance($transformationPayload);
        if ($validationError instanceof JsonResponse) {
            return $validationError;
        }

        $transformation = $this->persistTransformation($transformationPayload, $mixBuildStep, $barcodeService);

        return response()->json([
            'message' => 'Mixed portions replenished successfully',
            'data' => $transformation,
        ], 201);
    }

    private function validateWarehouseAndMassBalance(array $payload): ?JsonResponse
    {
        $inputWarehouseId = $this->resolveInputWarehouseId($payload);
        $outputWarehouseId = $this->resolveOutputWarehouseId($payload);
        $inputWarehouse = Warehouse::query()->findOrFail($inputWarehouseId);
        $outputWarehouse = Warehouse::query()->findOrFail($outputWarehouseId);

        if ((int) $inputWarehouse->branch_id !== (int) $payload['branch_id']) {
            return response()->json([
                'message' => 'Input warehouse does not belong to the provided branch.',
            ], 422);
        }

        if ((int) $outputWarehouse->branch_id !== (int) $payload['branch_id']) {
            return response()->json([
                'message' => 'Output warehouse does not belong to the provided branch.',
            ], 422);
        }

        $inputTotal = collect($payload['inputs'])->sum(fn (array $line): float => (float) $line['weight_kg']);
        $outputTotal = collect($payload['outputs'])->sum(fn (array $line): float => (float) $line['weight_kg']);
        $byproductsTotal = collect($payload['byproducts'] ?? [])->sum(fn (array $line): float => (float) $line['weight_kg']);
        $wasteTotal = collect($payload['waste'] ?? [])->sum(fn (array $line): float => (float) $line['weight_kg']);
        $diff = round(abs($inputTotal - ($outputTotal + $byproductsTotal + $wasteTotal)), 3);

        if ($diff > 0.02) {
            return response()->json([
                'message' => 'Mass balance validation failed.',
                'details' => [
                    'input_kg' => $inputTotal,
                    'output_kg' => $outputTotal,
                    'byproducts_kg' => $byproductsTotal,
                    'waste_kg' => $wasteTotal,
                    'difference_kg' => $diff,
                ],
            ], 422);
        }

        return null;
    }

    private function persistTransformation(array $payload, ProcessStep $processStep, BarcodeService $barcodeService): LotTransformation
    {
        return DB::transaction(function () use ($payload, $processStep, $barcodeService): LotTransformation {
            $inputWarehouseId = $this->resolveInputWarehouseId($payload);
            $outputWarehouseId = $this->resolveOutputWarehouseId($payload);
            $transformation = LotTransformation::query()->create([
                'transformation_code' => $this->generateTransformationCode($processStep->code),
                'process_step_id' => $payload['process_step_id'],
                'warehouse_id' => $outputWarehouseId,
                'input_warehouse_id' => $inputWarehouseId,
                'output_warehouse_id' => $outputWarehouseId,
                'branch_id' => $payload['branch_id'],
                'performed_by' => Auth::id(),
                'transformed_at' => $payload['transformed_at'],
                'remarks' => $payload['remarks'] ?? null,
            ]);

            foreach ($payload['inputs'] as $inputLine) {
                $batch = $this->resolveInputBatch($inputLine);
                $this->assertInputLineProductConsistency($inputLine, $batch);
                $this->assertProductCanBeInput((int) $batch->product_id);
                $stock = $batch->batchStocks()
                    ->where('warehouse_id', $inputWarehouseId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || (float) $stock->quantity < (float) $inputLine['weight_kg']) {
                    abort(422, 'Insufficient stock for one or more input lots.');
                }

                $stock->decrement('quantity', (float) $inputLine['weight_kg']);
                if ((float) $stock->fresh()->quantity <= 0.000) {
                    $stock->delete();
                }

                $batch->stockTransactions()->create([
                    'type' => 'out',
                    'quantity' => $inputLine['weight_kg'],
                    'from_warehouse_id' => $inputWarehouseId,
                    'reason' => 'Transformation '.$transformation->transformation_code.' input consumption',
                    'user_id' => Auth::id(),
                ]);

                $transformation->lines()->create([
                    'line_type' => 'input',
                    'batch_id' => $batch->id,
                    'source_lot_id' => $inputLine['source_lot_id'] ?? $batch->source_lot_id,
                    'product_id' => $inputLine['product_id'] ?? $batch->product_id,
                    'weight_kg' => $inputLine['weight_kg'],
                    'remarks' => $inputLine['remarks'] ?? null,
                ]);
            }

            $this->applyOutputLines(
                transformation: $transformation,
                lines: $payload['outputs'],
                warehouseId: $outputWarehouseId,
                lineType: 'output',
                barcodeService: $barcodeService
            );

            $this->applyOutputLines(
                transformation: $transformation,
                lines: $payload['byproducts'] ?? [],
                warehouseId: $outputWarehouseId,
                lineType: 'byproduct',
                barcodeService: $barcodeService
            );

            foreach ($payload['waste'] ?? [] as $wasteLine) {
                if (isset($wasteLine['product_id']) && $wasteLine['product_id'] !== null) {
                    $this->assertProductCanBeOutput((int) $wasteLine['product_id']);
                }

                $transformation->lines()->create([
                    'line_type' => 'waste',
                    'batch_id' => null,
                    'source_lot_id' => null,
                    'product_id' => $wasteLine['product_id'] ?? null,
                    'weight_kg' => $wasteLine['weight_kg'],
                    'remarks' => $wasteLine['remarks'] ?? null,
                ]);
            }

            return $transformation->fresh([
                'processStep',
                'warehouse',
                'inputWarehouse',
                'outputWarehouse',
                'branch',
                'lines.batch.product',
                'lines.sourceLot',
            ]);
        });
    }

    private function resolveInputWarehouseId(array $payload): int
    {
        return (int) ($payload['input_warehouse_id'] ?? $payload['warehouse_id']);
    }

    private function resolveOutputWarehouseId(array $payload): int
    {
        return (int) ($payload['output_warehouse_id'] ?? $payload['warehouse_id']);
    }

    private function resolveInputBatch(array $inputLine): Batch
    {
        if (! empty($inputLine['batch_id'])) {
            return Batch::query()->findOrFail($inputLine['batch_id']);
        }

        if (! empty($inputLine['source_lot_id'])) {
            return Batch::query()->where('source_lot_id', $inputLine['source_lot_id'])->firstOrFail();
        }

        abort(422, 'Each input line must include batch_id or source_lot_id.');
    }

    private function assertInputLineProductConsistency(array $inputLine, Batch $batch): void
    {
        if (
            isset($inputLine['product_id']) &&
            (int) $inputLine['product_id'] !== (int) $batch->product_id
        ) {
            abort(422, 'Input line product does not match the selected input batch product.');
        }
    }

    private function applyOutputLines(
        LotTransformation $transformation,
        array $lines,
        int $warehouseId,
        string $lineType,
        BarcodeService $barcodeService
    ): void {
        foreach ($lines as $line) {
            if (! empty($line['batch_id'])) {
                $batch = Batch::query()->findOrFail($line['batch_id']);
                $this->assertProductCanBeOutput((int) $batch->product_id);
                if (
                    isset($line['product_id']) &&
                    (int) $line['product_id'] !== (int) $batch->product_id
                ) {
                    abort(422, 'Output line product does not match the selected output batch product.');
                }
            } else {
                $this->assertProductCanBeOutput((int) $line['product_id']);
                $batch = Batch::query()->create([
                    'product_id' => $line['product_id'],
                    'source_lot_id' => null,
                    'batch_code' => $this->generateOutputBatchCode($lineType),
                    'manufactured_at' => $line['manufactured_at'] ?? now()->toDateString(),
                    'expires_at' => $line['expires_at'] ?? null,
                ]);
            }

            $stock = $batch->batchStocks()
                ->firstOrCreate(
                    ['warehouse_id' => $warehouseId],
                    ['quantity' => 0]
                );

            $stock->increment('quantity', (float) $line['weight_kg']);

            $batch->stockTransactions()->create([
                'type' => 'in',
                'quantity' => $line['weight_kg'],
                'to_warehouse_id' => $warehouseId,
                'reason' => 'Transformation '.$transformation->transformation_code.' '.$lineType.' output',
                'user_id' => Auth::id(),
            ]);

            if (! $batch->barcode) {
                $barcodeService->generateForBatch($batch->fresh('product'));
            }

            $transformation->lines()->create([
                'line_type' => $lineType,
                'batch_id' => $batch->id,
                'source_lot_id' => $batch->source_lot_id,
                'product_id' => $line['product_id'] ?? $batch->product_id,
                'weight_kg' => $line['weight_kg'],
                'remarks' => $line['remarks'] ?? null,
            ]);
        }
    }

    private function generateTransformationCode(string $processCode): string
    {
        return sprintf('TR-%s-%s', strtoupper($processCode), now()->format('Ymd-His-u'));
    }

    private function generateOutputBatchCode(string $lineType): string
    {
        return sprintf('B-%s-%s', strtoupper($lineType), now()->format('Ymd-His-u'));
    }

    private function assertProductCanBeInput(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        if (! $product->canBeInput()) {
            abort(422, 'Selected product is not allowed as transformation input.');
        }
    }

    private function assertProductCanBeOutput(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        if (! $product->canBeOutput()) {
            abort(422, 'Selected product is not allowed as transformation output.');
        }
    }
}
