<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Current stock levels for a specific product across warehouses
    public function productStock($product_id)
    {
        $product = Product::findOrFail($product_id);
        $stocks = BatchStock::active()
            ->whereHas('batch', fn($q) => $q->where('product_id', $product_id))
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
                        'total_quantity' => $row->total_quantity,
                    ];
                })->values(),
                'grand_total' => $group->sum('total_quantity'),
            ];
        })->values();

        return response()->json($result);
    }


    // Recent transfer histories
    public function recentTransfers(Request $request)
    {
        $limit = $request->limit ?? 50;
        $since = $request->since ? \Carbon\Carbon::parse($request->since) : null;

        $query = StockTransaction::transfers()
            ->with(['batch.product', 'fromWarehouse.branch', 'toWarehouse.branch', 'user'])
            ->orderBy('created_at', 'desc');

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $transfers = $query->limit($limit)->get()
            ->map(function ($transfer) {
                return [
                    'date' => $transfer->created_at->format('Y-m-d H:i:s'),
                    'product' => $transfer->batch->product->name,
                    'batch_code' => $transfer->batch->batch_code,
                    'quantity' => $transfer->quantity,
                    'from' => $transfer->fromWarehouse ? $transfer->fromWarehouse->name . ' (' . $transfer->fromWarehouse->branch->name . ')' : 'N/A',
                    'to' => $transfer->toWarehouse ? $transfer->toWarehouse->name . ' (' . $transfer->toWarehouse->branch->name . ')' : 'N/A',
                    'reason' => $transfer->reason,
                    'user' => $transfer->user ? $transfer->user->name : 'System',
                ];
            });

        return response()->json($transfers);
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

}