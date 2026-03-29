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
        'gross_car_weight_kg',
        'chicken_tray_count',
        'tray_unit_weight_kg',
        'received_weight_kg',
        'received_at',
        'received_by',
        'vehicle_number',
        'delivery_note_number',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_car_weight_kg' => 'decimal:3',
            'chicken_tray_count' => 'integer',
            'tray_unit_weight_kg' => 'decimal:3',
            'received_weight_kg' => 'decimal:3',
            'received_at' => 'datetime',
        ];
    }

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

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
