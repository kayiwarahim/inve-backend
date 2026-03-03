<?php

namespace App\Services;

use App\Models\Barcode;
use App\Models\Batch;
use Exception;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeService
{
    /**
     * Generate barcode for a batch
     */
    public function generateForBatch(Batch $batch): Barcode
    {
        if ($batch->barcode) {
            throw new Exception('Barcode already exists for this batch.');
        }

        $code = $this->generateBatchCode($batch);
        $generatorType = $this->resolveGeneratorType($code);

        $generator = new BarcodeGeneratorPNG;
        $barcodeImage = $generator->getBarcode(
            $code,
            $generatorType,
            2,
            70
        );

        $path = "barcodes/{$code}.png";

        Storage::disk('public')->put($path, $barcodeImage);

        return Barcode::create([
            'batch_id' => $batch->id,
            'code' => $code,
            'type' => $this->resolveBarcodeTypeName($code),
            'generated_at' => now(),
        ]);
    }

    /**
     * Build unique barcode string
     */
    protected function generateBatchCode(Batch $batch): string
    {
        return sprintf(
            '%s%06d',
            now()->format('ymd'),
            $batch->id
        );
    }

    protected function resolveGeneratorType(string $code): string
    {
        if (preg_match('/^\d+$/', $code) === 1 && strlen($code) % 2 === 0) {
            return BarcodeGeneratorPNG::TYPE_CODE_128_C;
        }

        return BarcodeGeneratorPNG::TYPE_CODE_128;
    }

    protected function resolveBarcodeTypeName(string $code): string
    {
        if (preg_match('/^\d+$/', $code) === 1 && strlen($code) % 2 === 0) {
            return 'CODE_128_C';
        }

        return 'CODE_128';
    }
}
