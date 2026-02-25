<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LotTransformation extends Model
{
    protected $fillable = [
        'transformation_code',
        'process_step_id',
        'warehouse_id',
        'branch_id',
        'performed_by',
        'transformed_at',
        'remarks',
    ];

    protected $casts = [
        'transformed_at' => 'datetime',
    ];

    public function processStep(): BelongsTo
    {
        return $this->belongsTo(ProcessStep::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LotTransformationLine::class);
    }
}
