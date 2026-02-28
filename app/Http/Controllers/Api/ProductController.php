<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * List all products
     */
    public function index()
    {
        $usage = request()->query('usage');
        $products = Product::query();

        if ($usage === 'input') {
            $products->whereIn('category', ['input', 'both']);
        } elseif ($usage === 'output') {
            $products->whereIn('category', ['output', 'both']);
        } elseif ($usage === 'both') {
            $products->where('category', 'both');
        }

        return response()->json($products->orderBy('name')->get(), 200);
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
            'category' => 'nullable|string|in:input,output,both',
            'description' => 'nullable|string',
        ]);

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 200);
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
            'sku' => 'required|string|unique:products,sku,'.$product->id,
            'name' => 'required|string',
            'unit' => 'required|string',
            'category' => 'nullable|string|in:input,output,both',
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
