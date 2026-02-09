<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodePrintLog extends Model
{
    protected $fillable = [
        'barcode_id',
        'printed_by',
        'printed_at'
    ];

    protected $casts = [
        'printed_at' => 'datetime'
    ];

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }
}
