<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotTransformationLine extends Model
{
    protected $fillable = [
        'lot_transformation_id',
        'line_type',
        'batch_id',
        'source_lot_id',
        'product_id',
        'weight_kg',
        'remarks',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
    ];

    public function lotTransformation(): BelongsTo
    {
        return $this->belongsTo(LotTransformation::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(SourceLot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
