<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'role',
        'phone',
        'location',
        'headline',
        'company_name',
        'industry',
        'company_size',
        'website',
        'avatar_path',
        'company_logo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'employer_id');
    }

    public function candidateProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }
}
