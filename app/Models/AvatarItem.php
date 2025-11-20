<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvatarItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'item_type',
        'rarity',
        'thumbnail_url',
        'asset_url',
        'unlock_cost',
        'unlock_method',
        'is_premium',
        'unlock_requirements',
        'is_active'
    ];

    protected $casts = [
        'unlock_requirements' => 'array',
        'is_premium' => 'boolean',
        'is_active' => 'boolean',
        'unlock_cost' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get users who own this item
     */
    public function owners()
    {
        return $this->belongsToMany(User::class, 'user_avatar_items')
            ->withPivot('is_equipped', 'unlocked_at', 'unlocked_via')
            ->withTimestamps();
    }

    /**
     * Scope to get active items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by item type
     */
    public function scopeType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Scope to filter by rarity
     */
    public function scopeRarity($query, $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    /**
     * Scope to filter by unlock method
     */
    public function scopeUnlockMethod($query, $method)
    {
        return $query->where('unlock_method', $method);
    }

    /**
     * Check if user can unlock this item
     */
    public function canUnlock($user)
    {
        // Check if already owned
        if ($user->avatarItems()->where('avatar_item_id', $this->id)->exists()) {
            return false;
        }

        // Check unlock requirements based on method
        switch ($this->unlock_method) {
            case 'points':
                return $user->points >= $this->unlock_cost;

            case 'free':
                return true;

            case 'achievement':
                // Check if user has required achievements
                if ($this->unlock_requirements) {
                    // TODO: Implement achievement checking
                    return true;
                }
                return false;

            case 'social_share':
                // Check if user has required shares
                if ($this->unlock_requirements) {
                    // TODO: Implement social share checking
                    return true;
                }
                return false;

            default:
                return false;
        }
    }
}
