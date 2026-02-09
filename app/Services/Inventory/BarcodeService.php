<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use Illuminate\Support\Str;

class BarcodeService
{
    public function generate(): string
    {
        return strtoupper(
            'BC-' . now()->format('Y') . '-' . Str::random(6)
        );
    }

    public function createForBatch(Batch $batch): Batch
    {
        if ($batch->barcode) {
            return $batch;
        }

        $batch->update([
            'barcode' => $this->generate()
        ]);

        return $batch;
    }
}
