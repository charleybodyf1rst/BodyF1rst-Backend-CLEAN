<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class InboxChat extends Model
{
    use HasFactory,Notifiable;

    protected $fillable = [
        'inbox_id',
        'sender_id',
        'message',
        'attachment',
        'has_read',
    ];

    protected $casts = [
        'inbox_id' => 'integer',
        'sender_id' => 'integer',
    ];

    public function inbox()
    {
        return $this->belongsTo(Inbox::class,'inbox_id');
    }

}
