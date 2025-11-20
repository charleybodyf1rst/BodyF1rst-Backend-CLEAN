<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $userType;
    public $status;
    public $lastSeenAt;

    public function __construct($userId, $userType, $status, $lastSeenAt = null)
    {
        $this->userId = $userId;
        $this->userType = $userType;
        $this->status = $status;
        $this->lastSeenAt = $lastSeenAt ?? now();
    }

    public function broadcastOn()
    {
        // Broadcast on a public channel for presence
        return new Channel('presence');
    }

    public function broadcastWith()
    {
        return [
            'user_id' => $this->userId,
            'user_type' => $this->userType,
            'status' => $this->status,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }

    public function broadcastAs()
    {
        return 'user.presence.updated';
    }
}
