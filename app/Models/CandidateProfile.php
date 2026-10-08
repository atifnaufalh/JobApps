<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateProfile extends Model
{
    protected $fillable = [
        'user_id',
        'summary',
        'skills',
        'experiences',
        'educations',
        'links',
        'cv_path',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'experiences' => 'array',
            'educations' => 'array',
            'links' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
