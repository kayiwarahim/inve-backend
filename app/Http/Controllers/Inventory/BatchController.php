<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'manufactured_at' => 'required|date'
        ]);

        $batch = Batch::create([
            'product_id' => $request->product_id,
            'batch_code' => 'B-' . now()->format('Ymd-His'),
            'manufactured_at' => $request->manufactured_at
        ]);

        return response()->json([
            'message' => 'Batch created successfully',
            'batch' => $batch
        ], 201);
    }

    
}
