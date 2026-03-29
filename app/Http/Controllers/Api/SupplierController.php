<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreSupplierRequest;
use App\Http\Requests\Api\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        return Supplier::query()
            ->latest()
            ->paginate($request->perPage());
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        return response()->json([
            'message' => 'Supplier created successfully',
            'data' => $supplier,
        ], 200);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier, 200);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Supplier updated successfully',
            'data' => $supplier->fresh(),
        ], 200);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully',
        ], 200);
    }
}
