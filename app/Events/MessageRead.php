<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $userId;
    public $userType;
    public $conversationId;

    public function __construct(Message $message, $userId, $userType)
    {
        $this->message = $message;
        $this->userId = $userId;
        $this->userType = $userType;
        $this->conversationId = $message->conversation_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->conversationId);
    }

    public function broadcastWith()
    {
        return [
            'message_id' => $this->message->id,
            'user_id' => $this->userId,
            'user_type' => $this->userType,
            'read_at' => now(),
        ];
    }

    public function broadcastAs()
    {
        return 'message.read';
    }
}
