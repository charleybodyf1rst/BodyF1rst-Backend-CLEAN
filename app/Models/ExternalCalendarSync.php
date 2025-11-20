<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalCalendarSync extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'coach_id',
        'provider',
        'provider_calendar_id',
        'provider_email',
        'sync_enabled',
        'sync_direction',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_synced_at',
        'next_sync_at',
        'sync_status',
        'last_error',
        'sync_error_count',
        'event_types_to_sync',
        'sync_past_events',
        'sync_days_ahead',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'sync_past_events' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'next_sync_at' => 'datetime',
        'event_types_to_sync' => 'array',
        'sync_error_count' => 'integer',
        'sync_days_ahead' => 'integer',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('sync_enabled', true)
                     ->where('sync_status', 'active');
    }

    public function scopeNeedingSync($query)
    {
        return $query->where('sync_enabled', true)
                     ->where('sync_status', 'active')
                     ->where(function ($q) {
                         $q->whereNull('next_sync_at')
                           ->orWhere('next_sync_at', '<=', now());
                     });
    }

    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeWithErrors($query)
    {
        return $query->where('sync_status', 'error')
                     ->orWhere('sync_error_count', '>', 0);
    }

    // Methods

    public function markSyncSuccess()
    {
        $this->last_synced_at = now();
        $this->next_sync_at = now()->addMinutes(15); // Sync every 15 minutes
        $this->sync_status = 'active';
        $this->sync_error_count = 0;
        $this->last_error = null;
        $this->save();
    }

    public function markSyncError($errorMessage)
    {
        $this->sync_error_count++;
        $this->last_error = $errorMessage;

        // If too many errors, pause sync
        if ($this->sync_error_count >= 5) {
            $this->sync_status = 'error';
        }

        // Exponential backoff for next sync
        $backoffMinutes = min(60 * pow(2, $this->sync_error_count - 1), 1440); // Max 24 hours
        $this->next_sync_at = now()->addMinutes($backoffMinutes);

        $this->save();
    }

    public function disconnect()
    {
        $this->sync_enabled = false;
        $this->sync_status = 'disconnected';
        $this->access_token = null;
        $this->refresh_token = null;
        $this->token_expires_at = null;
        $this->save();
    }

    public function needsTokenRefresh()
    {
        if (!$this->token_expires_at) {
            return false;
        }

        // Refresh if token expires in less than 5 minutes
        return $this->token_expires_at->subMinutes(5) <= now();
    }

    public function updateTokens($accessToken, $refreshToken = null, $expiresIn = null)
    {
        $this->access_token = $accessToken;

        if ($refreshToken) {
            $this->refresh_token = $refreshToken;
        }

        if ($expiresIn) {
            $this->token_expires_at = now()->addSeconds($expiresIn);
        }

        $this->save();
    }

    public function shouldSyncEventType($eventType)
    {
        if (!$this->event_types_to_sync) {
            return true; // Sync all if not specified
        }

        return in_array($eventType, $this->event_types_to_sync);
    }
}
