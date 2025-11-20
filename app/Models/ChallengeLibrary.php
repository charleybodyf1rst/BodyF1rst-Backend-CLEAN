<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeLibrary extends Model
{
    use HasFactory;

    protected $table = 'challenge_library';

    protected $fillable = [
        'created_by_admin_id',
        'name',
        'description',
        'challenge_type',
        'duration_days',
        'daily_tasks',
        'rules',
        'rewards',
        'thumbnail_url',
        'is_featured',
        'clone_count',
        'notes'
    ];

    protected $casts = [
        'daily_tasks' => 'array',
        'rules' => 'array',
        'rewards' => 'array',
        'is_featured' => 'boolean',
        'clone_count' => 'integer',
        'duration_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the admin who created this challenge
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Scope to get featured challenges
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by challenge type
     */
    public function scopeChallengeType($query, $type)
    {
        return $query->where('challenge_type', $type);
    }

    /**
     * Increment clone count
     */
    public function incrementCloneCount()
    {
        $this->increment('clone_count');
    }
}
