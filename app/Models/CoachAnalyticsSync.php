<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CoachAnalyticsSync Model
 *
 * Tracks when users sync their health data to coaches for analytics
 * Stores complete health metrics snapshot for coach dashboard visibility
 */
class CoachAnalyticsSync extends Model
{
    use HasFactory;

    protected $table = 'coach_analytics_sync';

    public $timestamps = false; // Using custom synced_at field

    protected $fillable = [
        'user_id',
        'coach_id',
        'health_data',
        'synced_at',
    ];

    protected $casts = [
        'health_data' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Get the user (client) who synced the data
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the coach who received the synced data
     */
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Sync health data to coach analytics
     */
    public static function syncToCoach($userId, $coachId, $healthData)
    {
        return self::create([
            'user_id' => $userId,
            'coach_id' => $coachId,
            'health_data' => $healthData,
            'synced_at' => now(),
        ]);
    }

    /**
     * Get latest sync for a user-coach pair
     */
    public static function getLatestSync($userId, $coachId)
    {
        return self::where('user_id', $userId)
            ->where('coach_id', $coachId)
            ->orderBy('synced_at', 'desc')
            ->first();
    }

    /**
     * Get all clients' latest health data for a coach
     */
    public static function getCoachClientsData($coachId)
    {
        return self::where('coach_id', $coachId)
            ->orderBy('synced_at', 'desc')
            ->get()
            ->unique('user_id')
            ->load('user');
    }

    /**
     * Get sync history for a specific client
     */
    public static function getClientSyncHistory($userId, $coachId, $limit = 30)
    {
        return self::where('user_id', $userId)
            ->where('coach_id', $coachId)
            ->orderBy('synced_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
