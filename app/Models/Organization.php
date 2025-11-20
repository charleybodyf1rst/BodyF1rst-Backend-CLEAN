<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'coach_id',
        'logo',
        'address',
        'website',
        'contract_start_date',
        'contract_end_date',
        'poc_name',
        'poc_email',
        'poc_phone',
        'poc_title',
        'rewards',
        'departments',
        // PT Studio fields
        'organization_type',
        'owner_id',
        'subscription_plan',
        'max_coaches',
        'max_clients',
        'status'
    ];

    protected $hidden = [
        "token"
    ];

    protected $casts = [
        "rewards" => "array",
        "departments" => "array",
        "coach_id" => "integer",
        "owner_id" => "integer",
        "max_coaches" => "integer",
        "max_clients" => "integer"
    ];

    public function getLogoAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/organization_profiles/{$value}";
        } else {
            return null;
        }
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class,'coach_id');
    }

    // Lead PT (owner) relationship for PT Studios
    public function owner()
    {
        return $this->belongsTo(Coach::class, 'owner_id');
    }
    public function coach_pivots()
    {
        return $this->belongsToMany(Coach::class, 'coach_organizations', 'organization_id', 'coach_id')->withTimestamps();
    }

    public function coaches()
    {
        return $this->hasManyThrough(Coach::class, CoachOrganization::class, 'organization_id', 'id', 'id', 'coach_id');
    }
    public function employees()
    {
        return $this->hasMany(User::class,'organization_id','id');
    }
    public function submissions()
    {
        return $this->hasMany(OrganizationSubmission::class,'organization_id','id');
    }
    public function submission()
    {
        return $this->hasOne(OrganizationSubmission::class,'organization_id','id')->latest();
    }

    public function assign_plans()
    {
        return $this->hasManyThrough(Plan::class, AssignPlan::class, 'organization_id', 'id', 'id', 'plan_id');
    }
}
