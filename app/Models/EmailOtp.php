<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $fillable = ['email', 'code_hash', 'mode', 'role', 'profile', 'expires_at', 'attempts', 'last_sent_at'];

    protected function casts(): array
    {
        return ['profile' => 'array', 'expires_at' => 'datetime', 'last_sent_at' => 'datetime'];
    }
}
