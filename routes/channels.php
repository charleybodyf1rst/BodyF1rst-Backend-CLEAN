<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Inbox channel - users can only access their own inboxes
Broadcast::channel('inbox.{inboxId}', function ($user, $inboxId) {
    // Check if user is a participant in this inbox
    $inbox = \DB::table('inboxes')
        ->where('id', $inboxId)
        ->first();

    if (!$inbox) {
        return false;
    }

    // User must be either the coach or the client in the inbox
    return (int) $user->id === (int) $inbox->coach_id ||
           (int) $user->id === (int) $inbox->user_id;
});

// Private messaging channel - Coach to Client
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Group chat channel
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    // Check if user is member of the group
    return \DB::table('group_members')
        ->where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();
});

// Presence channel for online status
Broadcast::channel('online', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->profile_image ?? null
        ];
    }
});
