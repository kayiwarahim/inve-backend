<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id', 
        'warehouse_id', 
        'quantity'
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('quantity', '>', 0);
    }
}