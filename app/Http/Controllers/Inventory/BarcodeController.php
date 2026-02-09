<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Batch;

class BarcodeController extends Controller
{
    public function scan(string $barcode)
    {
        $batch = Batch::where('barcode', $barcode)
            ->with(['product', 'stockMovements'])
            ->firstOrFail();

        return [
            'batch' => $batch,
            'current_location' => $batch->currentWarehouse(),
            'movement_history' => $batch->stockMovements()->latest()->get()
        ];
    }
}
