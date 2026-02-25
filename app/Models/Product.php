<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'unit',
        'description',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function transformationLines(): HasMany
    {
        return $this->hasMany(LotTransformationLine::class);
    }
}
