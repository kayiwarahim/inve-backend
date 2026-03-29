<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string',
            'code' => 'required|string|unique:warehouses,code',
            'type' => 'required|string',
        ]);

        $warehouse = Warehouse::create($data);

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
    public function update(Request $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string',
            'code' => 'required|string|unique:warehouses,code,'.$warehouse->id,
            'type' => 'required|string',
        ]);

        $warehouse->update($data);

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
        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully',
        ], 200);
    }
}
