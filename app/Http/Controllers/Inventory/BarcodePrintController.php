<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\BarcodePrintService;
use Illuminate\Http\Request;

class BarcodePrintController extends Controller
{
    public function print(Request $request, Batch $batch, BarcodePrintService $service)
    {
        $data = $request->validate([
            'copies' => 'nullable|integer|min:1',
            'printer_name' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        $service->logPrint(
            $batch,
            $data['copies'] ?? 1,
            $data['printer_name'] ?? null,
            $data['reason'] ?? null
        );

        return ['status' => 'printed'];
    }
}
