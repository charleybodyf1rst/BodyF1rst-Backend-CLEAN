<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
class Coach extends Authenticatable
{
    use HasApiTokens,Notifiable,HasFactory,SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'profile_image',
        'gender',
        // PT Studio fields
        'role',
        'studio_id',
        'specialties',
        'bio',
        'hourly_rate',
        'certifications',
        'years_experience',
        'is_accepting_clients'
    ];

    protected $hidden = [
      'password'
    ];

    protected $casts = [
        'specialties' => 'array',
        'studio_id' => 'integer',
        'hourly_rate' => 'decimal:2',
        'years_experience' => 'integer',
        'is_accepting_clients' => 'boolean'
    ];

    protected $appends = ['name'];

    public function getProfileImageAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/coach_profiles/{$value}";
        } else {
            return null;
        }
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes["password"] = bcrypt($value);
    }
    public function getNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // public function organizations()
    // {
    //     return $this->hasMany(Organization::class,'coach_id','id');
    // }

    // PT Studio relationship
    public function studio()
    {
        return $this->belongsTo(Organization::class, 'studio_id');
    }

    // If this coach is a Lead PT, this returns their owned studio
    public function ownedStudio()
    {
        return $this->hasOne(Organization::class, 'owner_id');
    }

    public function users()
    {
        return $this->hasMany(User::class,'coach_id','id');
    }

    // Helper methods for role checking
    public function isLeadTrainer()
    {
        return $this->role === 'lead_trainer';
    }

    public function isAssistantCoach()
    {
        return $this->role === 'assistant_coach';
    }

    public function canManageStudio()
    {
        return $this->role === 'lead_trainer';
    }
    public function organization_pivots()
    {
        return $this->belongsToMany(Organization::class, 'coach_organizations', 'coach_id', 'organization_id')->withTimestamps();
    }

    public function organizations()
    {
        return $this->hasManyThrough(Organization::class, CoachOrganization::class, 'coach_id', 'id', 'id', 'organization_id');
    }
    // public function user_pivots()
    // {
    //     return $this->belongsToMany(User::class, 'coach_users', 'coach_id', 'user_id')->withTimestamps();
    // }

    // public function users()
    // {
    //     return $this->hasManyThrough(User::class, CoachUser::class, 'coach_id', 'id', 'id', 'user_id');
    // }
}
