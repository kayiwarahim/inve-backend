<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Warehouse;

class WarehouseController extends Controller
{
    /**
     * GET /api/warehouses
     * List all warehouses
     */
    public function index()
    {
        return response()->json(Warehouse::all(), 200);
    }

    /**
     * POST /api/warehouses
     * Create a new warehouse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name'      => 'required|string',
            'code'      => 'required|string|unique:warehouses,code',
            'type'      => 'required|string',
        ]);

        $warehouse = Warehouse::create($data);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data'    => $warehouse,
        ], 200);
    }

    /**
     * GET /api/warehouses/{id}
     * Show single warehouse
     */
    public function show($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        return response()->json($warehouse, 200);
    }

    /**
     * PUT/PATCH /api/warehouses/{id}
     * Update warehouse
     */
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name'      => 'required|string',
            'code'      => 'required|string|unique:warehouses,code,' . $warehouse->id,
            'type'      => 'required|string',
        ]);

        $warehouse->update($data);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data'    => $warehouse,
        ], 200);
    }

    /**
     * DELETE /api/warehouses/{id}
     * Delete warehouse
     */
    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully',
        ], 200);
    }
}
