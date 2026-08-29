<?php

namespace App\Services;

use App\Models\BarcodePrintLog;
use App\Models\Batch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class BarcodePrintService
{
    public function logPrint(
        Batch $batch,
        int $copies = 1,
        ?string $printer = null,
        ?string $reason = null
    ): void {
        BarcodePrintLog::create([
            'batch_id' => $batch->id,
            'printed_by' => Auth::id(),
            'copies' => $copies,
            'printer_name' => $printer,
            'reason' => $reason,
            'printed_at' => Carbon::now(),
        ]);
    }
}
