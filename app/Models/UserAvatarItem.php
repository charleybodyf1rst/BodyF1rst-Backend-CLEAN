<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAvatarItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar_item_id',
        'is_equipped',
        'unlocked_at',
        'unlocked_via'
    ];

    protected $casts = [
        'is_equipped' => 'boolean',
        'unlocked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who owns this item
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the avatar item
     */
    public function avatarItem()
    {
        return $this->belongsTo(AvatarItem::class);
    }

    /**
     * Scope to get equipped items only
     */
    public function scopeEquipped($query)
    {
        return $query->where('is_equipped', true);
    }

    /**
     * Equip this item (and unequip others of same type)
     */
    public function equip()
    {
        // Unequip all items of the same type for this user
        $itemType = $this->avatarItem->item_type;

        self::where('user_id', $this->user_id)
            ->where('is_equipped', true)
            ->whereHas('avatarItem', function($q) use ($itemType) {
                $q->where('item_type', $itemType);
            })
            ->update(['is_equipped' => false]);

        // Equip this item
        $this->update(['is_equipped' => true]);
    }

    /**
     * Unequip this item
     */
    public function unequip()
    {
        $this->update(['is_equipped' => false]);
    }
}
