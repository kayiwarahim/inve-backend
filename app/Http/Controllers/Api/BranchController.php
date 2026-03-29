<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:branches,code',
            'location' => 'nullable|string',
        ]);

        $branch = Branch::create($data);

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
    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:branches,code,'.$branch->id,
            'location' => 'nullable|string',
        ]);

        $branch->update($data);

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

        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully',
        ], 200);
    }
}
