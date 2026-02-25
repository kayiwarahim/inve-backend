<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Batch extends Model
{
    protected $fillable = [
        'product_id',
        'source_lot_id',
        'batch_code',
        'manufactured_at',
        'expires_at',
    ];

    protected $casts = [
        'manufactured_at' => 'date',
        'expires_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(SourceLot::class);
    }

    public function batchStocks(): HasMany
    {
        return $this->hasMany(BatchStock::class);
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function barcode(): HasOne
    {
        return $this->hasOne(Barcode::class);
    }

    public function transformationLines(): HasMany
    {
        return $this->hasMany(LotTransformationLine::class);
    }
}
