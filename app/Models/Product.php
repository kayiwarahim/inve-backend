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
        'category',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => 'string',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function transformationLines(): HasMany
    {
        return $this->hasMany(LotTransformationLine::class);
    }

    public function canBeInput(): bool
    {
        return in_array($this->category, ['input', 'both'], true);
    }

    public function canBeOutput(): bool
    {
        return in_array($this->category, ['output', 'both'], true);
    }
}
