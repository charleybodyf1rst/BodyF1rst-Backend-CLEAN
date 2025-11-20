<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM AI Agent Model
 * Configuration for AI agents (SMS, Email, Voice)
 */
class CrmAiAgent extends Model
{
    use HasFactory;

    protected $table = 'crm_ai_agents';

    protected $fillable = [
        'name',
        'type',
        'description',
        'is_active',
        'model',
        'system_prompt',
        'max_tokens',
        'temperature',
        'voice_id',
        'voice_language',
        'voice_speed',
        'voice_greeting',
        'email_from_address',
        'email_signature',
        'email_tracking_enabled',
        'sms_phone_number',
        'max_conversations_per_day',
        'active_hours_start',
        'active_hours_end',
        'timezone',
        'trigger_on_new_lead',
        'trigger_delay_minutes',
        'trigger_conditions',
        'handoff_keywords',
        'handoff_to_human',
        'success_keywords',
        'total_conversations',
        'successful_conversations',
        'failed_conversations',
        'avg_sentiment_score',
        'total_cost',
        'total_tokens_used',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_tokens' => 'integer',
        'temperature' => 'decimal:2',
        'voice_speed' => 'decimal:2',
        'email_tracking_enabled' => 'boolean',
        'max_conversations_per_day' => 'integer',
        'trigger_on_new_lead' => 'boolean',
        'trigger_delay_minutes' => 'integer',
        'trigger_conditions' => 'array',
        'handoff_keywords' => 'array',
        'handoff_to_human' => 'boolean',
        'success_keywords' => 'array',
        'total_conversations' => 'integer',
        'successful_conversations' => 'integer',
        'failed_conversations' => 'integer',
        'avg_sentiment_score' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'total_tokens_used' => 'integer',
    ];

    /**
     * Relationships
     */
    public function communications()
    {
        return $this->hasMany(CrmCommunication::class, 'ai_agent_id');
    }

    public function campaigns()
    {
        return $this->hasMany(CrmCampaign::class, 'ai_agent_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeVoice($query)
    {
        return $query->where('type', 'voice');
    }

    /**
     * Helper Methods
     */
    public function getSuccessRateAttribute()
    {
        if ($this->total_conversations == 0) return 0;
        return round(($this->successful_conversations / $this->total_conversations) * 100, 2);
    }

    public function isWithinActiveHours()
    {
        $now = now($this->timezone ?? 'America/New_York');
        $currentTime = $now->format('H:i');

        return $currentTime >= $this->active_hours_start &&
               $currentTime <= $this->active_hours_end;
    }

    public function canSendToday()
    {
        $todayConversations = $this->communications()
            ->whereDate('created_at', today())
            ->count();

        return $todayConversations < $this->max_conversations_per_day;
    }

    public function incrementConversations($successful = true)
    {
        $this->increment('total_conversations');

        if ($successful) {
            $this->increment('successful_conversations');
        } else {
            $this->increment('failed_conversations');
        }
    }

    public function addCost($cost)
    {
        $this->increment('total_cost', $cost);
    }

    public function addTokens($tokens)
    {
        $this->increment('total_tokens_used', $tokens);
    }
}
