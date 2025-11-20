<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageEditHistory extends Model
{
    use HasFactory;

    protected $table = 'message_edit_history';

    protected $fillable = [
        'message_id',
        'original_content',
        'new_content',
        'edited_at'
    ];

    protected $casts = [
        'message_id' => 'integer',
        'edited_at' => 'datetime',
    ];

    /**
     * Get the message that owns the edit history.
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
