<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\BarcodePrintLog;
use Carbon\Carbon;

class BarcodePrintService
{
    public function logPrint(
        Batch $batch,
        int $copies = 1,
        ?string $printer = null,
        ?string $reason = null
    ): void {
        BarcodePrintLog::create([
            'batch_id'   => $batch->id,
            'printed_by'=> auth()->id(),
            'copies'    => $copies,
            'printer_name' => $printer,
            'reason'    => $reason,
            'printed_at'=> Carbon::now(),
        ]);
    }
}
