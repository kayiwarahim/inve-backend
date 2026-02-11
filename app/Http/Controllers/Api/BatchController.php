<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Client\Batch as ClientBatch;
use Illuminate\Http\Request;

class BatchController extends Controller
{
        /**
     * GET /api/batch
     * List all batch
     */
    public function index()
    {
        return response()->json(Batch::all(), 200);
    }
        /**
     * POST /api/batch
     * Create a new batch
     */
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
        ], 200);
    }

      /**
     * GET /api/batch/{id}
     * Show single batch
     */
    public function show($id)
    {
        $batch = Batch::findOrFail($id);

        return response()->json($batch, 200);
    }

        /**
     * PUT/PATCH /api/batch/{id}
     * Update batch
     */
    public function update(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'manufactured_at' => 'required|date'
        ]);

        $batch->update($data);

        return response()->json([
            'message' => 'Batch updated successfully',
            'data' => $batch,
        ], 200);
    }

        /**
     * DELETE /api/batch/{id}
     * Delete product
     */
    public function destroy($id)
    {
        $batch = Batch::findOrFail($id);
        $batch->delete();

        return response()->json([
            'message' => 'Batch deleted successfully',
        ], 200);
    }
}
