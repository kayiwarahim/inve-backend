<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceLot extends Model
{
    protected $fillable = [
        'supplier_id',
        'branch_id',
        'source_lot_code',
        'received_weight_kg',
        'received_at',
        'received_by',
        'vehicle_number',
        'delivery_note_number',
        'remarks',
    ];

    protected $casts = [
        'received_weight_kg' => 'decimal:3',
        'received_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function transformationLines(): HasMany
    {
        return $this->hasMany(LotTransformationLine::class);
    }
}
