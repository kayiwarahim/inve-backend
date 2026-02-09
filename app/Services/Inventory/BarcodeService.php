<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Barcode;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Storage;
use Exception;

class BarcodeService
{
    /**
     * Create barcode for a batch
     */
    public function generateForBatch(Batch $batch): Barcode
    {
        if ($batch->barcode) {
            throw new Exception('Barcode already exists for this batch.');
        }

        $code = $this->generateBatchCode($batch);

        $generator = new BarcodeGeneratorPNG();
        $barcodeImage = $generator->getBarcode(
            $code,
            $generator::TYPE_CODE_128,
            3,
            100
        );

        $path = "barcodes/{$code}.png";

        Storage::disk('public')->put($path, $barcodeImage);

        return Barcode::create([
            'batch_id'     => $batch->id,
            'code'         => $code,
            'type'         => 'CODE_128',
            'generated_at' => now(),
        ]);
    }

    /**
     * Generate unique batch barcode string
     */
    protected function generateBatchCode(Batch $batch): string
    {
        return sprintf(
            '%s-%s-%06d',
            strtoupper($batch->product->sku),
            now()->format('Ymd'),
            $batch->id
        );
    }
}
