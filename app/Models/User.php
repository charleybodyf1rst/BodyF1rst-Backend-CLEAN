<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'department',
        'email',
        'phone',
        'gender',
        'dob',
        'age',
        'weight',
        'height',
        'protein',
        'carb',
        'calorie',
        'fat',
        'bmr',
        'tdee',
        'activity_level',
        'goal',
        'daily_meal',
        'accountability',
        'dietary_restrictions',
        'equipment_preferences',
        'training_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'otp',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'organization_id' => 'integer',
        'coach_id' => 'integer',
        'daily_meal' => 'integer',
        'age' => 'integer',
        'dietary_restrictions' => 'array',
        'equipment_preferences' => 'array',
        'training_preferences' => 'array',
        "uploaded_by" => "integer",
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes["password"] = bcrypt($value);
    }
    public function getProfileImageAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null)
        {
            return url("/") . "/upload/user_profiles/{$value}";
        }else{
            return null;
        }
    }
    public function getProfileImageThumbnailAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null)
        {
            return url("/") . "/upload/user_profiles/thumbnails/{$value}";
        }else{
            return null;
        }
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function coaches()
    {
        return $this->hasManyThrough(Coach::class, CoachUser::class, 'user_id', 'id', 'id', 'coach_id');
    }

    public function assign_plans()
    {
        return $this->hasManyThrough(Plan::class, AssignPlan::class, 'user_id', 'id', 'id', 'plan_id');
    }

    public function assigned_plans()
    {
        return $this->hasMany(AssignPlan::class,'user_id','id');
    }

    public function upload_by()
    {
        return $this->morphTo('upload_by','uploader','uploaded_by');
    }

}
