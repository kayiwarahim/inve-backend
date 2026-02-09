<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodePrintLog extends Model
{
    protected $fillable = [
        'batch_id',
        'printed_by',
        'copies',
        'printer_name',
        'reason',
        'printed_at'
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}