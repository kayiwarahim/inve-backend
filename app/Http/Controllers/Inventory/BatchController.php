<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\Inventory\BarcodeService;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function store(Request $request, BarcodeService $barcodeService)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_code' => 'nullable|string',
            'manufactured_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $batch = Batch::create($data);

        $barcodeService->createForBatch($batch);

        return $batch;
    }
}
