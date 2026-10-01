<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordOtp extends Model
{
    protected $fillable = ['email', 'otp', 'expires_at'];

    public $timestamps = false; // If not needed

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
