<?php

namespace App\Http\Controllers\Api;

use App\Exports\StockLevelReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExportStockLevelsRequest;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockLevelController extends Controller
{
    /**
     * GET /api/stock-levels
     * Optional filters:
     *  - warehouse_id
     *  - batch_id
     */
    public function index(Request $request): JsonResponse
    {
        $query = BatchStock::with([
            'warehouse.branch',
            'batch.product',
        ]);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * GET /api/stock-levels/warehouse/{warehouseId}/batch/{batchId}
     */
    public function show(int $warehouseId, int $batchId): JsonResponse
    {
        $quantity = BatchStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->value('quantity') ?? 0;

        return response()->json([
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Scan barcode and return LIVE location
     */
    public function scan(string $barcode): JsonResponse
    {
        $barcode = Barcode::with('batch.product')
            ->where('code', $barcode)
            ->firstOrFail();

        $locations = BatchStock::with('warehouse.branch')
            ->where('batch_id', $barcode->batch_id)
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'barcode' => $barcode->code,
            'batch' => $barcode->batch,
            'product' => $barcode->batch->product,
            'locations' => $locations,
        ]);
    }

    public function export(ExportStockLevelsRequest $request): BinaryFileResponse
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $day = $request->input('day');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $hasDateFilter = filled($day) || (filled($startDate) && filled($endDate));

        if ($hasDateFilter) {
            $resolvedStartDate = filled($day) ? $day : $startDate;
            $resolvedEndDate = filled($day) ? $day : $endDate;
            $periodStart = Carbon::parse($resolvedStartDate)->startOfDay();
            $periodEnd = Carbon::parse($resolvedEndDate)->endOfDay();
            $rows = $this->buildPeriodRows($periodStart, $periodEnd, $warehouseId, $branchId, $day, $startDate, $endDate);
        } else {
            $rows = $this->buildCurrentRows($warehouseId, $branchId);
        }

        return Excel::download(
            new StockLevelReportExport($rows),
            'stock-level-report-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function buildCurrentRows(?int $warehouseId, ?int $branchId): array
    {
        $query = BatchStock::query()
            ->with(['warehouse.branch', 'batch.product'])
            ->where('quantity', '>', 0);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($branchId !== null) {
            $query->whereHas('warehouse', function (Builder $builder) use ($branchId): void {
                $builder->where('branch_id', $branchId);
            });
        }

        return $query->get()
            ->map(function (BatchStock $stock): array {
                return [
                    'current',
                    '',
                    '',
                    '',
                    $stock->warehouse?->branch?->name ?? '',
                    $stock->warehouse?->name ?? '',
                    $stock->batch?->batch_code ?? '',
                    $stock->batch?->product?->name ?? '',
                    '',
                    '',
                    '',
                    number_format((float) $stock->quantity, 3, '.', ''),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPeriodRows(
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $warehouseId,
        ?int $branchId,
        ?string $day,
        ?string $startDate,
        ?string $endDate
    ): array {
        $query = StockTransaction::query()
            ->with([
                'batch.product',
                'fromWarehouse.branch',
                'toWarehouse.branch',
            ])
            ->where('created_at', '<=', $periodEnd);

        if ($warehouseId !== null) {
            $query->where(function (Builder $builder) use ($warehouseId): void {
                $builder
                    ->where('from_warehouse_id', $warehouseId)
                    ->orWhere('to_warehouse_id', $warehouseId);
            });
        }

        if ($branchId !== null) {
            $query->where(function (Builder $builder) use ($branchId): void {
                $builder
                    ->whereHas('fromWarehouse', function (Builder $inner) use ($branchId): void {
                        $inner->where('branch_id', $branchId);
                    })
                    ->orWhereHas('toWarehouse', function (Builder $inner) use ($branchId): void {
                        $inner->where('branch_id', $branchId);
                    });
            });
        }

        $rows = [];
        $transactions = $query->get();

        foreach ($transactions as $transaction) {
            $this->accumulateTransactionForWarehouse(
                $rows,
                $transaction->batch,
                $transaction->toWarehouse,
                (float) $transaction->quantity,
                true,
                $transaction->created_at->lt($periodStart),
                $warehouseId,
                $branchId
            );

            $this->accumulateTransactionForWarehouse(
                $rows,
                $transaction->batch,
                $transaction->fromWarehouse,
                (float) $transaction->quantity,
                false,
                $transaction->created_at->lt($periodStart),
                $warehouseId,
                $branchId
            );
        }

        $reportType = filled($day) ? 'day' : 'period';

        return collect($rows)
            ->map(function (array $row) use ($reportType, $day, $startDate, $endDate): array {
                $opening = $row['opening_in'] - $row['opening_out'];
                $closing = $opening + $row['period_in'] - $row['period_out'];

                if (abs($opening) < 0.0005 && abs($row['period_in']) < 0.0005 && abs($row['period_out']) < 0.0005 && abs($closing) < 0.0005) {
                    return [];
                }

                return [
                    $reportType,
                    $day ?? '',
                    $day ? '' : ($startDate ?? ''),
                    $day ? '' : ($endDate ?? ''),
                    $row['branch_name'],
                    $row['warehouse_name'],
                    $row['batch_code'],
                    $row['product_name'],
                    number_format($opening, 3, '.', ''),
                    number_format($row['period_in'], 3, '.', ''),
                    number_format($row['period_out'], 3, '.', ''),
                    number_format($closing, 3, '.', ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function accumulateTransactionForWarehouse(
        array &$rows,
        ?Batch $batch,
        ?Warehouse $warehouse,
        float $quantity,
        bool $isIncoming,
        bool $isOpeningMovement,
        ?int $warehouseId,
        ?int $branchId
    ): void {
        if ($batch === null || $warehouse === null) {
            return;
        }

        if ($warehouseId !== null && (int) $warehouse->id !== $warehouseId) {
            return;
        }

        if ($branchId !== null && (int) $warehouse->branch_id !== $branchId) {
            return;
        }

        $key = $warehouse->id.'-'.$batch->id;

        if (! array_key_exists($key, $rows)) {
            $rows[$key] = [
                'branch_name' => $warehouse->branch?->name ?? '',
                'warehouse_name' => $warehouse->name ?? '',
                'batch_code' => $batch->batch_code,
                'product_name' => $batch->product?->name ?? '',
                'opening_in' => 0.0,
                'opening_out' => 0.0,
                'period_in' => 0.0,
                'period_out' => 0.0,
            ];
        }

        if ($isOpeningMovement) {
            if ($isIncoming) {
                $rows[$key]['opening_in'] += $quantity;
            } else {
                $rows[$key]['opening_out'] += $quantity;
            }

            return;
        }

        if ($isIncoming) {
            $rows[$key]['period_in'] += $quantity;
        } else {
            $rows[$key]['period_out'] += $quantity;
        }
    }
}
