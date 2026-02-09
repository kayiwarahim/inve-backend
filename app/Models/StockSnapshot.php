<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockSnapshot extends Model
{
    protected $fillable = [
        'warehouse_id',
        'batch_id',
        'quantity',
        'snapshot_date'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}