<?php

namespace App\Events;

use App\Models\InboxChat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $inboxId;

    public function __construct($message)
    {
        $this->message = $message;
        $this->inboxId = is_object($message) && isset($message->inbox_id) ? $message->inbox_id : null;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('inbox.' . $this->inboxId);
    }

    public function broadcastWith()
    {
        $value =  [
            'sender_id' => $this->message->sender_id,
            'sender_role' => $this->message->sender_role,
            'message' => $this->message->message,
            'attachment' => $this->message->attachment,
            'created_at' => $this->message->created_at,
        ];
        info($value);
        return $value;
    }
}

