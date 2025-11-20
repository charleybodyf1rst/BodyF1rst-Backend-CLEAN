<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushNotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'workouts',
        'meals',
        'messages',
        'reminders',
        'progress',
        'social',
    ];

    protected $casts = [
        'workouts' => 'boolean',
        'meals' => 'boolean',
        'messages' => 'boolean',
        'reminders' => 'boolean',
        'progress' => 'boolean',
        'social' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function device()
    {
        return $this->belongsTo(MobileDevice::class, 'device_id');
    }
}
