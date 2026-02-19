<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id', 
        'from_warehouse_id', 
        'to_warehouse_id', 
        'quantity',
        'type', 
        'reason', 
        'user_id'
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeTransfers($query)
    {
        return $query->where('type', 'transfer');
    }
}