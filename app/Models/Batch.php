<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Batch extends Model
{
    protected $fillable = [
        'product_id',
        'batch_code',
        'manufactured_at',
        'expires_at'
    ];

    protected $casts = [
        'manufactured_at' => 'date',
        'expires_at' => 'date'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function barcode(): HasOne
    {
        return $this->hasOne(Barcode::class);
    }
}
