<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'type',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function batchStocks(): HasMany
    {
        return $this->hasMany(BatchStock::class);
    }

    public function lotTransformations(): HasMany
    {
        return $this->hasMany(LotTransformation::class);
    }

    public function inputLotTransformations(): HasMany
    {
        return $this->hasMany(LotTransformation::class, 'input_warehouse_id');
    }

    public function outputLotTransformations(): HasMany
    {
        return $this->hasMany(LotTransformation::class, 'output_warehouse_id');
    }
}
