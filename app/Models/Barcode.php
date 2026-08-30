<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barcode extends Model
{
    protected $fillable = [
        'batch_id',
        'code',
        'type',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(BarcodePrintLog::class, 'batch_id', 'batch_id');
    }
}
