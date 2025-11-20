<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactDiscovery extends Model
{
    use HasFactory;

    protected $table = 'contact_discovery';

    protected $fillable = [
        'user_id',
        'contact_type',
        'contact_value_hash',
        'display_name',
        'discoverable'
    ];

    protected $casts = [
        'discoverable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who owns this contact
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hash a contact value for privacy
     */
    public static function hashContact($value)
    {
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Find users by contact hash
     */
    public static function findMatchingUsers($contactType, $contactValues)
    {
        $hashes = array_map(function($value) {
            return self::hashContact($value);
        }, $contactValues);

        return self::where('contact_type', $contactType)
            ->whereIn('contact_value_hash', $hashes)
            ->where('discoverable', true)
            ->with('user:id,first_name,last_name,email,avatar')
            ->get();
    }
}
