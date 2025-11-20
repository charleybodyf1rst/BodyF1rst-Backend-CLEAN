<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'user_type',
        'read_at'
    ];

    protected $casts = [
        'message_id' => 'integer',
        'user_id' => 'integer',
        'read_at' => 'datetime',
    ];

    /**
     * Get the message that owns the read receipt.
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who read the message.
     */
    public function user()
    {
        if ($this->user_type === 'user') {
            return $this->belongsTo(User::class, 'user_id');
        } elseif ($this->user_type === 'coach') {
            return $this->belongsTo(Coach::class, 'user_id');
        } elseif ($this->user_type === 'admin') {
            return $this->belongsTo(Admin::class, 'user_id');
        }
        return null;
    }
}
