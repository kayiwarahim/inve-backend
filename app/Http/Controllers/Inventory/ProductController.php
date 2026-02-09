<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Product::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|unique:products',
            'name' => 'required|string',
            'unit_of_measure' => 'required|string',
            'description' => 'nullable|string',
        ]);

        return Product::create($data);
    }
}
