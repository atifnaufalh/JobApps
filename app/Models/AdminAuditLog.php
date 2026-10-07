<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = ['actor_id', 'action', 'target_user_id', 'target_email', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
