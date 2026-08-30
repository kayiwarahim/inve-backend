<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreBranchRequest;
use App\Http\Requests\Api\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    /**
     * GET /api/branches
     * List all branches
     */
    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        return Branch::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage());
    }

    /**
     * POST /api/branches
     * Create a new branch
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return response()->json([
            'message' => 'Branch created successfully',
            'data' => $branch,
        ], 200);
    }

    /**
     * GET /api/branches/{id}
     * Show single branch
     */
    public function show(int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        return response()->json($branch, 200);
    }

    /**
     * PUT/PATCH /api/branches/{id}
     * Update branch
     */
    public function update(UpdateBranchRequest $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $branch->update($request->validated());

        return response()->json([
            'message' => 'Branch updated successfully',
            'data' => $branch,
        ], 200);
    }

    /**
     * DELETE /api/branches/{id}
     * Delete branch
     */
    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        // Check if branch has any warehouses.
        $hasWarehouses = $branch->warehouses()->exists();
        if ($hasWarehouses) {
            return response()->json([
                'message' => 'Cannot delete branch with active warehouses.',
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully',
        ], 200);
    }
}
