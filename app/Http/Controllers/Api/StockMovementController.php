<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockMovementService;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function stockIn(Request $request, StockMovementService $service)
    {
        return $service->stockIn($request->validate([
            'batch_id' => 'required|exists:batches,id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'remarks' => 'nullable|string'
        ]));
    }

    public function stockOut(Request $request, StockMovementService $service)
    {
        return $service->stockOut($request->validate([
            'batch_id' => 'required|exists:batches,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'remarks' => 'nullable|string'
        ]));
    }

    public function transfer(Request $request, StockMovementService $service)
    {
        return $service->transfer($request->validate([
            'batch_id' => 'required|exists:batches,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'remarks' => 'nullable|string'
        ]));
    }
}