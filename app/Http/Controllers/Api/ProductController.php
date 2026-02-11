<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * List all products
     */
    public function index()
    {
        return response()->json(Product::all(), 200);
    }

    /**
     * POST /api/products
     * Create a new product
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string|unique:products,sku',
            'name' => 'required|string',
            'unit' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * GET /api/products/{id}
     * Show single product
     */
    public function show($id)
    {
        $product = Product::findOrFail($id);

        return response()->json($product, 200);
    }

    /**
     * PUT/PATCH /api/products/{id}
     * Update product
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'name' => 'required|string',
            'unit' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
        ], 200);
    }

    /**
     * DELETE /api/products/{id}
     * Delete product
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ], 200);
    }
}
