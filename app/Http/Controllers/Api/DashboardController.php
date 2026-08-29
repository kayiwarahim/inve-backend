<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Current stock levels for a specific product across warehouses
    public function productStock($product_id)
    {
        $product = Product::findOrFail($product_id);
        $stocks = BatchStock::active()
            ->whereHas('batch', fn ($q) => $q->where('product_id', $product_id))
            ->with(['warehouse.branch'])
            ->selectRaw('warehouse_id, SUM(quantity) as total_quantity')
            ->groupBy('warehouse_id')
            ->get()
            ->map(function ($stock) {
                return [
                    'warehouse' => $stock->warehouse->name,
                    'branch' => $stock->warehouse->branch->name,
                    'total_quantity' => $stock->total_quantity,
                ];
            });

        return response()->json([
            'product' => $product->name,
            'stocks' => $stocks,
            'grand_total' => $stocks->sum('total_quantity'),
        ]);
    }

    // Current stock levels for all products (with optional filters)
    // Current stock levels for all products (with optional filters)
    // Current stock levels for all products (with optional filters)
    public function allProductStock(Request $request)
    {
        $query = BatchStock::query()
            ->active()
            ->join('batches', 'batch_stocks.batch_id', '=', 'batches.id')
            ->join('products', 'batches.product_id', '=', 'products.id')
            ->join('warehouses', 'batch_stocks.warehouse_id', '=', 'warehouses.id')
            ->join('branches', 'warehouses.branch_id', '=', 'branches.id')
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                warehouses.name as warehouse_name,
                branches.name as branch_name,
                SUM(batch_stocks.quantity) as total_quantity
            ')
            ->groupBy(
                'products.id',
                'products.name',
                'warehouses.name',
                'branches.name'
            );

        if ($request->product_ids) {
            $ids = explode(',', $request->product_ids);
            $query->whereIn('products.id', $ids);
        }

        $rows = $query->get();

        $result = $rows->groupBy('product_id')->map(function ($group) {

            return [
                'product_id' => $group->first()->product_id,
                'product_name' => $group->first()->product_name,
                'stocks' => $group->map(function ($row) {
                    return [
                        'warehouse' => $row->warehouse_name,
                        'branch' => $row->branch_name,
                        'total_quantity' => (float) $row->total_quantity,
                    ];
                })->values(),
                'grand_total' => (float) $group->sum('total_quantity'),
            ];
        })->values();

        return response()->json($result);
    }

    // Recent transfer histories (backward-compatible)
    public function recentTransfers(Request $request)
    {
        $request->merge([
            'type' => $request->query('type', 'transfer'),
        ]);

        return $this->movementHistory($request);
    }

    // General movement history
    public function movementHistory(Request $request)
    {
        $limit = $request->limit ?? 50;
        $since = $request->since ? Carbon::parse($request->since) : null;
        $type = (string) $request->query('type', 'all');

        $query = StockTransaction::query()
            ->with(['batch.product', 'fromWarehouse.branch', 'toWarehouse.branch', 'user'])
            ->orderBy('created_at', 'desc');

        if ($type === 'transfer') {
            $query->where('type', 'transfer');
        } elseif ($type === 'process') {
            $query->whereIn('type', ['in', 'out'])
                ->where('reason', 'like', 'Transformation %');
        }

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $transactions = $query->limit($limit)->get();

        $transformationCodes = $transactions
            ->filter(function ($movement) {
                return $movement->type === 'in' && $movement->from_warehouse_id === null;
            })
            ->map(function ($movement) {
                return $this->extractTransformationCode((string) ($movement->reason ?? ''));
            })
            ->filter()
            ->unique()
            ->values();

        $processFromWarehouseByCode = collect();
        if ($transformationCodes->isNotEmpty()) {
            $outMovements = StockTransaction::query()
                ->with(['fromWarehouse.branch'])
                ->where('type', 'out')
                ->where(function ($nested) use ($transformationCodes) {
                    foreach ($transformationCodes as $code) {
                        $nested->orWhere('reason', 'like', 'Transformation '.$code.' %');
                    }
                })
                ->latest('id')
                ->get();

            $processFromWarehouseByCode = $outMovements->reduce(function ($carry, $movement) {
                $code = $this->extractTransformationCode((string) ($movement->reason ?? ''));
                if (! $code || $carry->has($code)) {
                    return $carry;
                }

                $carry->put(
                    $code,
                    $movement->fromWarehouse
                        ? $movement->fromWarehouse->name.' ('.$movement->fromWarehouse->branch->name.')'
                        : null
                );

                return $carry;
            }, collect());
        }

        $movements = $transactions
            ->map(function ($movement) use ($processFromWarehouseByCode) {
                $reason = (string) ($movement->reason ?? '');
                $isProcessMovement = str_starts_with($reason, 'Transformation ');
                $processCode = $this->extractTransformationCode($reason);
                $fromWarehouse = $movement->fromWarehouse
                    ? $movement->fromWarehouse->name.' ('.$movement->fromWarehouse->branch->name.')'
                    : ($processCode ? ($processFromWarehouseByCode->get($processCode) ?? 'Unknown process source') : 'External source');
                $toWarehouse = $movement->toWarehouse
                    ? $movement->toWarehouse->name.' ('.$movement->toWarehouse->branch->name.')'
                    : (($isProcessMovement && $movement->type === 'out') ? 'Consumed in process' : 'N/A');

                return [
                    'date' => $movement->created_at->format('Y-m-d H:i:s'),
                    'movement_type' => $movement->type,
                    'movement_scope' => $movement->type === 'transfer'
                        ? 'transfer'
                        : ($isProcessMovement ? 'process' : 'stock'),
                    'product' => $movement->batch?->product?->name,
                    'batch_code' => $movement->batch?->batch_code,
                    'quantity' => (float) $movement->quantity,
                    'from' => $fromWarehouse,
                    'to' => $toWarehouse,
                    'reason' => $movement->reason,
                    'user' => $movement->user ? $movement->user->name : 'System',
                ];
            });

        return response()->json($movements);
    }

    // All products in a given warehouse with quantities
    public function warehouseProducts($warehouse_id)
    {
        $warehouse = Warehouse::with('branch')->findOrFail($warehouse_id);

        $stocks = BatchStock::active()
            ->join('batches', 'batch_stocks.batch_id', '=', 'batches.id')
            ->join('products', 'batches.product_id', '=', 'products.id')
            ->where('batch_stocks.warehouse_id', $warehouse_id)
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.unit as unit,
                SUM(batch_stocks.quantity) as total_quantity
            ')
            ->groupBy('products.id', 'products.name', 'products.unit')
            ->get();

        return response()->json([
            'warehouse' => $warehouse->name,
            'branch' => $warehouse->branch->name,
            'products' => $stocks,
            'total_items' => $stocks->count(),
            'grand_total_quantity' => $stocks->sum('total_quantity'),
        ]);
    }

    private function extractTransformationCode(string $reason): ?string
    {
        if (preg_match('/^Transformation\s+(\S+)/', $reason, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
