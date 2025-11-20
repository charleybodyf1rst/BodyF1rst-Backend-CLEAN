<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoachChallenge extends Model
{
    use HasFactory;

    protected $table = 'coach_challenges';

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'challenge_type',
        'duration_days',
        'daily_tasks',
        'rules',
        'rewards',
        'thumbnail_url',
        'notes',
        'cloned_from_library_id'
    ];

    protected $casts = [
        'daily_tasks' => 'array',
        'rules' => 'array',
        'rewards' => 'array',
        'duration_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the coach who owns this challenge
     */
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Get the library challenge this was cloned from
     */
    public function sourceLibraryChallenge()
    {
        return $this->belongsTo(ChallengeLibrary::class, 'cloned_from_library_id');
    }

    /**
     * Scope to filter by coach
     */
    public function scopeForCoach($query, $coachId)
    {
        return $query->where('coach_id', $coachId);
    }

    /**
     * Scope to filter by challenge type
     */
    public function scopeChallengeType($query, $type)
    {
        return $query->where('challenge_type', $type);
    }
}
