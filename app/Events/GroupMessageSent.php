<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $groupId;

    public function __construct($message, $groupId)
    {
        $this->message = $message;
        $this->groupId = $groupId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('group.' . $this->groupId);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id ?? null,
            'sender_id' => $this->message->sender_id ?? null,
            'sender_name' => $this->message->sender_name ?? null,
            'sender_avatar' => $this->message->sender_avatar ?? null,
            'message' => $this->message->message ?? '',
            'attachment' => $this->message->attachment ?? null,
            'created_at' => $this->message->created_at ?? now(),
        ];
    }

    public function broadcastAs()
    {
        return 'group.message';
    }
}
