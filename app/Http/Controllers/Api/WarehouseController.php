<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreWarehouseRequest;
use App\Http\Requests\Api\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * GET /api/warehouses
     * List all warehouses
     */
    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        return Warehouse::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage());
    }

    /**
     * POST /api/warehouses
     * Create a new warehouse
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => $warehouse,
        ], 200);
    }

    /**
     * GET /api/warehouses/{id}
     * Show single warehouse
     */
    public function show(int $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        return response()->json($warehouse, 200);
    }

    /**
     * PUT/PATCH /api/warehouses/{id}
     * Update warehouse
     */
    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        $warehouse->update($request->validated());

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse,
        ], 200);
    }

    /**
     * DELETE /api/warehouses/{id}
     * Delete warehouse
     */
    public function destroy(int $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        // Check if warehouse has any batch stocks (active or historical).
        $hasBatchStocks = $warehouse->batchStocks()->exists();
        if ($hasBatchStocks) {
            return response()->json([
                'message' => 'Cannot delete warehouse with active stock.',
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully',
        ], 200);
    }
}
