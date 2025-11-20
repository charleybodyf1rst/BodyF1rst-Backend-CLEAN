<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challenge extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['title','type','start_date','prize','duration','description','challenge_type','challenge_description','is_active'];

    protected $casts = [
        "organization_id" => "integer",
        "coach_id" => "integer",
        "uploaded_by" => "integer",
    ];

    protected $appends = [
        "end_date"
    ];

    public function getCoverImageAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/challenge_profiles/{$value}";
        } else {
            return null;
        }
    }

    public function getEndDateAttribute()
    {
        if (isset($this->start_date) && $this->start_date != "" && $this->start_date != null && isset($this->duration)) {
            return Carbon::parse($this->start_date)->addDays($this->duration)->format('Y-m-d');
        } else {
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

    public function users()
    {
        return $this->hasManyThrough(User::class, ChallengeUser::class, 'challenge_id', 'id', 'id', 'user_id');
    }

    public function coach_pivots()
    {
        return $this->belongsToMany(Coach::class, 'challenge_coaches', 'challenge_id', 'coach_id')->withTimestamps();
    }
    public function coaches()
    {
        return $this->hasManyThrough(Coach::class, ChallengeCoach::class, 'challenge_id', 'id', 'id', 'coach_id');
    }

    public function upload_by()
    {
        return $this->morphTo('upload_by','uploader','uploaded_by');
    }
}
