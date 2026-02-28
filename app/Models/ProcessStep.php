<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessStep extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sequence',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lotTransformations(): HasMany
    {
        return $this->hasMany(LotTransformation::class);
    }
}
