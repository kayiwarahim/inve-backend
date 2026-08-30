<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodePrintLog extends Model
{
    protected $fillable = [
        'batch_id',
        'printed_by',
        'copies',
        'printer_name',
        'reason',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
