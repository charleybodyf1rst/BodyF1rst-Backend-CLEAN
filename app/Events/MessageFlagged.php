<?php

namespace App\Events;

use App\Models\MessageFlag;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageFlagged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageFlag;

    public function __construct(MessageFlag $messageFlag)
    {
        $this->messageFlag = $messageFlag;
    }

    public function broadcastOn()
    {
        // Broadcast to admin channel
        return new PrivateChannel('admin.moderation');
    }

    public function broadcastWith()
    {
        return [
            'flag_id' => $this->messageFlag->id,
            'message_id' => $this->messageFlag->message_id,
            'flag_type' => $this->messageFlag->flag_type,
            'flagged_by_type' => $this->messageFlag->flagged_by_type,
            'status' => $this->messageFlag->status,
            'created_at' => $this->messageFlag->created_at,
        ];
    }

    public function broadcastAs()
    {
        return 'message.flagged';
    }
}
