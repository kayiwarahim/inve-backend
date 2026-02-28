<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\LotTransformationLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class TraceabilityController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $batch = $this->resolveBatch($code);
        if (! $batch) {
            return response()->json([
                'message' => 'No batch found for the provided code.',
            ], 404);
        }

        $visitedBatchIds = [];
        $lineage = $this->buildLineageForBatch($batch, $visitedBatchIds);
        $sourceLots = $this->collectSourceLots($lineage)->values();

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'product' => $batch->product?->name,
                'sku' => $batch->product?->sku,
                'barcode' => $batch->barcode?->code,
            ],
            'current_locations' => $batch->batchStocks
                ->where('quantity', '>', 0)
                ->values()
                ->map(fn ($stock): array => [
                    'branch' => $stock->warehouse?->branch?->name,
                    'warehouse' => $stock->warehouse?->name,
                    'quantity_kg' => (float) $stock->quantity,
                ]),
            'movement_history' => $batch->stockTransactions
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($tx): array => [
                    'type' => $tx->type,
                    'quantity_kg' => (float) $tx->quantity,
                    'from_warehouse_id' => $tx->from_warehouse_id,
                    'to_warehouse_id' => $tx->to_warehouse_id,
                    'reason' => $tx->reason,
                    'at' => optional($tx->created_at)->toDateTimeString(),
                ]),
            'lineage' => $lineage,
            'source_lots' => $sourceLots,
        ], 200);
    }

    private function resolveBatch(string $code): ?Batch
    {
        $barcode = Barcode::query()
            ->where('code', $code)
            ->first();

        if ($barcode) {
            return Batch::query()
                ->with([
                    'product',
                    'barcode',
                    'sourceLot.supplier',
                    'batchStocks.warehouse.branch',
                    'stockTransactions',
                ])
                ->find($barcode->batch_id);
        }

        return Batch::query()
            ->with([
                'product',
                'barcode',
                'sourceLot.supplier',
                'batchStocks.warehouse.branch',
                'stockTransactions',
            ])
            ->where('batch_code', $code)
            ->first();
    }

    private function buildLineageForBatch(Batch $batch, array &$visitedBatchIds): array
    {
        if (in_array($batch->id, $visitedBatchIds, true)) {
            return [
                'batch_id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'product' => $batch->product?->name,
                'cycle_detected' => true,
            ];
        }

        $visitedBatchIds[] = $batch->id;

        $producingLines = LotTransformationLine::query()
            ->with([
                'lotTransformation.processStep',
                'lotTransformation.warehouse',
                'lotTransformation.inputWarehouse',
                'lotTransformation.outputWarehouse',
                'lotTransformation.branch',
                'lotTransformation.lines.batch.product',
                'lotTransformation.lines.batch.sourceLot.supplier',
                'lotTransformation.lines.sourceLot.supplier',
                'lotTransformation.lines.product',
            ])
            ->where('batch_id', $batch->id)
            ->whereIn('line_type', ['output', 'byproduct'])
            ->get();

        $nodes = [];
        foreach ($producingLines as $producingLine) {
            $transformation = $producingLine->lotTransformation;
            if (! $transformation) {
                continue;
            }

            $inputLines = $transformation->lines->where('line_type', 'input')->values();
            $inputTotal = (float) $inputLines->sum(fn ($line): float => (float) $line->weight_kg);

            $inputs = $inputLines->map(function ($inputLine) use (&$visitedBatchIds, $inputTotal): array {
                $inputBatch = $inputLine->batch;
                $inputWeight = (float) $inputLine->weight_kg;

                $item = [
                    'line_id' => $inputLine->id,
                    'weight_kg' => $inputWeight,
                    'contribution_percent' => $inputTotal > 0
                        ? round(($inputWeight / $inputTotal) * 100, 4)
                        : 0.0,
                    'source_lot' => $inputLine->sourceLot ? [
                        'id' => $inputLine->sourceLot->id,
                        'source_lot_code' => $inputLine->sourceLot->source_lot_code,
                        'supplier' => $inputLine->sourceLot->supplier?->name,
                    ] : null,
                ];

                if ($inputBatch) {
                    $item['batch'] = [
                        'id' => $inputBatch->id,
                        'batch_code' => $inputBatch->batch_code,
                        'product' => $inputBatch->product?->name,
                        'sku' => $inputBatch->product?->sku,
                    ];
                    $item['upstream'] = $this->buildLineageForBatch($inputBatch, $visitedBatchIds);
                }

                return $item;
            })->values();

            $nodes[] = [
                'transformation_id' => $transformation->id,
                'transformation_code' => $transformation->transformation_code,
                'process_step' => $transformation->processStep?->code,
                'process_name' => $transformation->processStep?->name,
                'warehouse' => $transformation->warehouse?->name,
                'input_warehouse' => $transformation->inputWarehouse?->name ?? $transformation->warehouse?->name,
                'output_warehouse' => $transformation->outputWarehouse?->name ?? $transformation->warehouse?->name,
                'branch' => $transformation->branch?->name,
                'transformed_at' => optional($transformation->transformed_at)->toDateTimeString(),
                'inputs' => $inputs,
                'outputs' => $this->formatOutputSummary($transformation->lines->whereIn('line_type', ['output', 'byproduct'])),
                'waste' => $transformation->lines
                    ->where('line_type', 'waste')
                    ->values()
                    ->map(fn ($line): array => [
                        'weight_kg' => (float) $line->weight_kg,
                        'product' => $line->product?->name,
                    ]),
            ];
        }

        if (count($nodes) === 0 && $batch->sourceLot) {
            return [
                'batch_id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'product' => $batch->product?->name,
                'source_lot' => [
                    'id' => $batch->sourceLot->id,
                    'source_lot_code' => $batch->sourceLot->source_lot_code,
                    'supplier' => $batch->sourceLot->supplier?->name,
                    'received_at' => optional($batch->sourceLot->received_at)->toDateTimeString(),
                ],
                'transformations' => [],
            ];
        }

        return [
            'batch_id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'product' => $batch->product?->name,
            'source_lot' => $batch->sourceLot ? [
                'id' => $batch->sourceLot->id,
                'source_lot_code' => $batch->sourceLot->source_lot_code,
                'supplier' => $batch->sourceLot->supplier?->name,
                'received_at' => optional($batch->sourceLot->received_at)->toDateTimeString(),
            ] : null,
            'transformations' => $nodes,
        ];
    }

    private function formatOutputSummary(Collection $outputLines): Collection
    {
        return $outputLines
            ->values()
            ->map(fn ($line): array => [
                'line_type' => $line->line_type,
                'weight_kg' => (float) $line->weight_kg,
                'batch_code' => $line->batch?->batch_code,
                'product' => $line->batch?->product?->name ?? $line->product?->name,
            ]);
    }

    private function collectSourceLots(array $lineage): Collection
    {
        $sourceLots = collect();

        if (! empty($lineage['source_lot'])) {
            $sourceLots->push($lineage['source_lot']);
        }

        foreach (($lineage['transformations'] ?? []) as $transformation) {
            foreach (($transformation['inputs'] ?? []) as $input) {
                if (! empty($input['source_lot'])) {
                    $sourceLots->push($input['source_lot']);
                }
                if (! empty($input['upstream']) && is_array($input['upstream'])) {
                    $sourceLots = $sourceLots->merge($this->collectSourceLots($input['upstream']));
                }
            }
        }

        return $sourceLots
            ->unique(fn (array $item): string => (string) ($item['source_lot_code'] ?? $item['id'] ?? ''));
    }
}
