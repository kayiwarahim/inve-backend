<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovementApproval extends Model
{
    protected $fillable = [
        'stock_movement_id',
        'status',
        'approved_by',
        'approved_at',
        'remarks'
    ];

    public function movement()
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
