<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * List all products
     */
    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        $usage = $request->query('usage');
        $products = Product::query();

        if ($usage === 'input') {
            $products->whereIn('category', ['input', 'both']);
        } elseif ($usage === 'output') {
            $products->whereIn('category', ['output', 'both']);
        } elseif ($usage === 'both') {
            $products->where('category', 'both');
        }

        return $products
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage());
    }

    /**
     * POST /api/products
     * Create a new product
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 200);
    }

    /**
     * GET /api/products/{id}
     * Show single product
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return response()->json($product, 200);
    }

    /**
     * PUT/PATCH /api/products/{id}
     * Update product
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $product->update($request->validated());

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
        ], 200);
    }

    /**
     * DELETE /api/products/{id}
     * Delete product
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        // Reject deletion if product has any batches in the system.
        $hasBatches = $product->batches()->exists();
        if ($hasBatches) {
            return response()->json([
                'message' => 'Cannot delete product with active batches in the system.',
            ], 422);
        }

        // Reject deletion if product is used in any transformation lines.
        $usedInTransformations = $product->transformationLines()->exists();
        if ($usedInTransformations) {
            return response()->json([
                'message' => 'Cannot delete product that is used in transformations.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ], 200);
    }
}
