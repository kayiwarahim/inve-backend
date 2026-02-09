<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'batch_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'quantity',
        'movement_type',
        'status',
        'reference',
        'created_by'
    ];

    protected $casts = [
        'quantity' => 'integer'
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function approvals(): BelongsTo
    {
        return $this->belongsTo(MovementApproval::class);
    }
}
