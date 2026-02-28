<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'location',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function sourceLots(): HasMany
    {
        return $this->hasMany(SourceLot::class);
    }

    public function lotTransformations(): HasMany
    {
        return $this->hasMany(LotTransformation::class);
    }
}
