<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\BlockedUser;
use App\Models\MessageReaction;
use App\Services\MessageEncryptionService;
use App\Services\ContentModerationService;
use App\Services\PushNotificationService;
use App\Events\MessageSent as MessageSentEvent;
use App\Events\UserTyping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MessagingController extends Controller
{
    protected $encryptionService;
    protected $moderationService;
    protected $notificationService;

    public function __construct(
        MessageEncryptionService $encryptionService,
        ContentModerationService $moderationService,
        PushNotificationService $notificationService
    ) {
        $this->encryptionService = $encryptionService;
        $this->moderationService = $moderationService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get authenticated user info
     */
    private function getAuthUser(Request $request)
    {
        $role = strtolower($request->role ?? 'user');

        if (!in_array($role, ['user', 'coach', 'admin'])) {
            return null;
        }

        $user = Auth::guard($role)->user();

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'type' => $role,
            'user' => $user
        ];
    }

    /**
     * Get or create conversation
     */
    public function getOrCreateConversation(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:private,group,organization',
            'participant_ids' => 'required_if:type,private|array',
            'participant_ids.*' => 'integer',
            'participant_types' => 'array',
            'name' => 'required_if:type,group|string|max:255',
            'organization_id' => 'required_if:type,organization|integer|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->type === 'private') {
                // Check for existing private conversation
                $participantId = $request->participant_ids[0];
                $participantType = $request->participant_types[0] ?? 'user';

                // Check if blocked
                if (BlockedUser::isBlocked($authUser['id'], $authUser['type'], $participantId, $participantType) ||
                    BlockedUser::isBlocked($participantId, $participantType, $authUser['id'], $authUser['type'])) {
                    return response(['status' => 403, 'message' => 'Cannot create conversation with blocked user'], 403);
                }

                $conversation = Conversation::whereHas('participants', function($q) use ($authUser) {
                    $q->where('participant_id', $authUser['id'])
                      ->where('participant_type', $authUser['type']);
                })->whereHas('participants', function($q) use ($participantId, $participantType) {
                    $q->where('participant_id', $participantId)
                      ->where('participant_type', $participantType);
                })->where('type', 'private')->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'type' => 'private',
                        'created_by' => $authUser['id'],
                    ]);

                    $conversation->addParticipant($authUser['id'], $authUser['type']);
                    $conversation->addParticipant($participantId, $participantType);
                }
            } elseif ($request->type === 'group') {
                $conversation = Conversation::create([
                    'type' => 'group',
                    'name' => $request->name,
                    'description' => $request->description,
                    'created_by' => $authUser['id'],
                ]);

                // Add creator as admin
                $conversation->addParticipant($authUser['id'], $authUser['type'], true);

                // Add other participants
                foreach ($request->participant_ids as $index => $participantId) {
                    $participantType = $request->participant_types[$index] ?? 'user';
                    $conversation->addParticipant($participantId, $participantType);
                }
            } elseif ($request->type === 'organization') {
                $conversation = Conversation::create([
                    'type' => 'organization',
                    'name' => $request->name ?? 'Organization Chat',
                    'organization_id' => $request->organization_id,
                    'created_by' => $authUser['id'],
                ]);

                $conversation->addParticipant($authUser['id'], $authUser['type'], true);
            }

            $conversation->load(['participants', 'lastMessage']);

            DB::commit();

            return response([
                'status' => 200,
                'message' => 'Conversation retrieved/created successfully',
                'conversation' => $conversation
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating conversation: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to create conversation'], 500);
        }
    }

    /**
     * Send message
     */
    public function sendMessage(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|integer|exists:conversations,id',
            'message' => 'required_without:attachments|string',
            'attachments' => 'array',
            'message_type' => 'in:text,image,video,audio,file,voice,gif',
            'reply_to_message_id' => 'nullable|integer|exists:messages,id',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $conversation = Conversation::find($request->conversation_id);

            // Verify user is participant
            if (!$conversation->hasParticipant($authUser['id'], $authUser['type'])) {
                return response(['status' => 403, 'message' => 'Not a participant of this conversation'], 403);
            }

            DB::beginTransaction();

            // Check for profanity and moderate content
            $messageContent = $request->message;
            $moderationResult = null;

            if ($messageContent) {
                $profanityCheck = $this->moderationService->checkProfanity($messageContent);
                if ($profanityCheck['has_profanity']) {
                    $messageContent = $profanityCheck['clean_message'];
                }
            }

            // Encrypt message
            $encryptedMessage = $messageContent ? $this->encryptionService->encrypt($messageContent) : null;

            // Create message
            $message = Message::create([
                'conversation_id' => $request->conversation_id,
                'sender_id' => $authUser['id'],
                'sender_type' => $authUser['type'],
                'message' => $messageContent,
                'message_encrypted' => $encryptedMessage,
                'attachments' => $request->attachments,
                'message_type' => $request->message_type ?? 'text',
                'reply_to_message_id' => $request->reply_to_message_id,
                'is_scheduled' => $request->scheduled_at ? true : false,
                'scheduled_at' => $request->scheduled_at,
                'delivered_at' => $request->scheduled_at ? null : now(),
            ]);

            // Update conversation last message time
            $conversation->update(['last_message_at' => now()]);

            // Moderate message
            $moderationResult = $this->moderationService->moderateMessage($message);

            // Auto-flag if needed
            if ($moderationResult['needs_review']) {
                foreach ($moderationResult['flags'] as $flag) {
                    $message->flagMessage(
                        $flag['type'],
                        json_encode($flag['details']),
                        null,
                        'system',
                        $flag
                    );
                }
            }

            $message->load(['sender', 'replyTo', 'reactions', 'reads']);

            DB::commit();

            // Broadcast message
            broadcast(new MessageSentEvent($message))->toOthers();

            // Send push notifications
            $participants = $conversation->participants()
                ->where('participant_id', '!=', $authUser['id'])
                ->get();

            $recipients = $participants->map(function($p) {
                return ['id' => $p->participant_id, 'type' => $p->participant_type];
            })->toArray();

            $senderName = $authUser['user']->first_name ?? $authUser['user']->name ?? 'Someone';
            $this->notificationService->sendNewMessageNotification(
                $recipients,
                $senderName,
                $messageContent ?? '[Attachment]',
                $conversation->id
            );

            return response([
                'status' => 200,
                'message' => 'Message sent successfully',
                'data' => $message,
                'moderation' => $moderationResult
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending message: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to send message'], 500);
        }
    }

    /**
     * Get messages for conversation
     */
    public function getMessages(Request $request, $conversationId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Conversation not found'], 404);
            }

            // Verify user is participant
            if (!$conversation->hasParticipant($authUser['id'], $authUser['type'])) {
                return response(['status' => 403, 'message' => 'Not a participant'], 403);
            }

            $limit = $request->query('limit', 50);
            $beforeId = $request->query('before_id');

            $messagesQuery = $conversation->messages()
                ->with(['sender', 'replyTo', 'reactions', 'reads'])
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc');

            if ($beforeId) {
                $messagesQuery->where('id', '<', $beforeId);
            }

            $messages = $messagesQuery->limit($limit)->get();

            // Mark messages as read
            $participant = $conversation->participants()
                ->where('participant_id', $authUser['id'])
                ->where('participant_type', $authUser['type'])
                ->first();

            if ($participant) {
                $participant->markAsRead();
            }

            return response([
                'status' => 200,
                'message' => 'Messages retrieved successfully',
                'data' => $messages,
                'conversation' => $conversation
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting messages: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get messages'], 500);
        }
    }

    /**
     * Get conversations list
     */
    public function getConversations(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversations = Conversation::whereHas('participants', function($q) use ($authUser) {
                $q->where('participant_id', $authUser['id'])
                  ->where('participant_type', $authUser['type'])
                  ->whereNull('left_at');
            })
            ->with(['participants', 'lastMessage', 'lastMessage.sender'])
            ->orderBy('last_message_at', 'desc')
            ->paginate($request->query('per_page', 20));

            return response([
                'status' => 200,
                'message' => 'Conversations retrieved successfully',
                'data' => $conversations
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting conversations: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get conversations'], 500);
        }
    }

    /**
     * Add reaction to message
     */
    public function addReaction(Request $request, $messageId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'reaction' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $message = Message::find($messageId);

            if (!$message) {
                return response(['status' => 404, 'message' => 'Message not found'], 404);
            }

            $message->addReaction($authUser['id'], $authUser['type'], $request->reaction);

            return response([
                'status' => 200,
                'message' => 'Reaction added successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error adding reaction: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to add reaction'], 500);
        }
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction(Request $request, $messageId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'reaction' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $message = Message::find($messageId);

            if (!$message) {
                return response(['status' => 404, 'message' => 'Message not found'], 404);
            }

            $message->removeReaction($authUser['id'], $authUser['type'], $request->reaction);

            return response([
                'status' => 200,
                'message' => 'Reaction removed successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error removing reaction: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to remove reaction'], 500);
        }
    }

    /**
     * Edit message
     */
    public function editMessage(Request $request, $messageId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $message = Message::find($messageId);

            if (!$message) {
                return response(['status' => 404, 'message' => 'Message not found'], 404);
            }

            // Verify ownership
            if ($message->sender_id !== $authUser['id'] || $message->sender_type !== $authUser['type']) {
                return response(['status' => 403, 'message' => 'Cannot edit this message'], 403);
            }

            DB::beginTransaction();

            // Save edit history
            $message->editHistory()->create([
                'original_content' => $message->message,
                'new_content' => $request->message,
                'edited_at' => now(),
            ]);

            // Update message
            $message->update([
                'message' => $request->message,
                'message_encrypted' => $this->encryptionService->encrypt($request->message),
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            DB::commit();

            return response([
                'status' => 200,
                'message' => 'Message edited successfully',
                'data' => $message
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error editing message: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to edit message'], 500);
        }
    }

    /**
     * Delete message (soft delete)
     */
    public function deleteMessage(Request $request, $messageId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $message = Message::find($messageId);

            if (!$message) {
                return response(['status' => 404, 'message' => 'Message not found'], 404);
            }

            // Verify ownership
            if ($message->sender_id !== $authUser['id'] || $message->sender_type !== $authUser['type']) {
                return response(['status' => 403, 'message' => 'Cannot delete this message'], 403);
            }

            $message->update(['is_deleted' => true]);

            return response([
                'status' => 200,
                'message' => 'Message deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting message: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to delete message'], 500);
        }
    }

    /**
     * Pin/Unpin message
     */
    public function togglePinMessage(Request $request, $messageId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $message = Message::find($messageId);

            if (!$message) {
                return response(['status' => 404, 'message' => 'Message not found'], 404);
            }

            $message->update(['is_pinned' => !$message->is_pinned]);

            return response([
                'status' => 200,
                'message' => $message->is_pinned ? 'Message pinned' : 'Message unpinned',
                'data' => $message
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error toggling pin: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to toggle pin'], 500);
        }
    }

    /**
     * Block user
     */
    public function blockUser(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_type' => 'required|in:user,coach,admin',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            BlockedUser::create([
                'blocker_id' => $authUser['id'],
                'blocker_type' => $authUser['type'],
                'blocked_id' => $request->user_id,
                'blocked_type' => $request->user_type,
                'reason' => $request->reason,
            ]);

            return response([
                'status' => 200,
                'message' => 'User blocked successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error blocking user: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to block user'], 500);
        }
    }

    /**
     * Unblock user
     */
    public function unblockUser(Request $request, $blockedUserId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            BlockedUser::where('blocker_id', $authUser['id'])
                ->where('blocker_type', $authUser['type'])
                ->where('id', $blockedUserId)
                ->delete();

            return response([
                'status' => 200,
                'message' => 'User unblocked successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error unblocking user: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to unblock user'], 500);
        }
    }

    /**
     * Report message
     */
    public function reportMessage(Request $request, $messageId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'flag_type' => 'required|in:profanity,nudity,harassment,spam,other',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $message = Message::find($messageId);

            if (!$message) {
                return response(['status' => 404, 'message' => 'Message not found'], 404);
            }

            $message->flagMessage(
                $request->flag_type,
                $request->reason,
                $authUser['id'],
                $authUser['type']
            );

            // Notify admins
            $this->notificationService->sendMessageFlaggedNotification(
                $request->flag_type,
                $messageId,
                $request->reason
            );

            return response([
                'status' => 200,
                'message' => 'Message reported successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error reporting message: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to report message'], 500);
        }
    }

    /**
     * Update typing status
     */
    public function updateTypingStatus(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|integer|exists:conversations,id',
            'is_typing' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            broadcast(new UserTyping(
                $request->conversation_id,
                $authUser['id'],
                $authUser['type'],
                $request->is_typing
            ))->toOthers();

            return response([
                'status' => 200,
                'message' => 'Typing status updated'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating typing status: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to update typing status'], 500);
        }
    }

    /**
     * Search messages
     */
    public function searchMessages(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $query = Message::whereHas('conversation.participants', function($q) use ($authUser) {
                $q->where('participant_id', $authUser['id'])
                  ->where('participant_type', $authUser['type']);
            })
            ->where('is_deleted', false)
            ->whereRaw('MATCH(message) AGAINST(? IN BOOLEAN MODE)', [$request->query])
            ->with(['conversation', 'sender']);

            if ($request->conversation_id) {
                $query->where('conversation_id', $request->conversation_id);
            }

            $messages = $query->orderBy('created_at', 'desc')
                ->paginate($request->query('per_page', 20));

            return response([
                'status' => 200,
                'message' => 'Search completed successfully',
                'data' => $messages
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error searching messages: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to search messages'], 500);
        }
    }

    // ========================================================================
    // GROUP CHAT MANAGEMENT
    // ========================================================================

    /**
     * Create group chat
     * POST /api/messaging/group-chat
     */
    public function createGroupChat(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'integer',
            'participant_types' => 'array',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            DB::beginTransaction();

            $conversation = Conversation::create([
                'type' => 'group',
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => $authUser['id'],
            ]);

            // Add creator as admin
            $conversation->addParticipant($authUser['id'], $authUser['type'], true);

            // Add other participants
            foreach ($request->participant_ids as $index => $participantId) {
                $participantType = $request->participant_types[$index] ?? 'user';
                $conversation->addParticipant($participantId, $participantType);
            }

            $conversation->load(['participants', 'lastMessage']);

            DB::commit();

            return response([
                'status' => 200,
                'message' => 'Group chat created successfully',
                'data' => $conversation
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating group chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to create group chat'], 500);
        }
    }

    /**
     * Get group chat details
     * GET /api/messaging/group-chat/{id}
     */
    public function getGroupChat(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::where('id', $id)
                ->where('type', 'group')
                ->with(['participants', 'lastMessage'])
                ->first();

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Group chat not found'], 404);
            }

            // Verify user is participant
            if (!$conversation->hasParticipant($authUser['id'], $authUser['type'])) {
                return response(['status' => 403, 'message' => 'Not a participant'], 403);
            }

            return response([
                'status' => 200,
                'message' => 'Group chat retrieved successfully',
                'data' => $conversation
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting group chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get group chat'], 500);
        }
    }

    /**
     * Send message to group chat
     * POST /api/messaging/group-chat/{id}/message
     */
    public function sendGroupMessage(Request $request, $id)
    {
        $request->merge(['conversation_id' => $id]);
        return $this->sendMessage($request);
    }

    /**
     * Join group chat
     * POST /api/messaging/group-chat/{id}/join
     */
    public function joinGroupChat(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::where('id', $id)
                ->where('type', 'group')
                ->first();

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Group chat not found'], 404);
            }

            // Check if already a participant
            if ($conversation->hasParticipant($authUser['id'], $authUser['type'])) {
                return response(['status' => 400, 'message' => 'Already a member'], 400);
            }

            $conversation->addParticipant($authUser['id'], $authUser['type']);

            return response([
                'status' => 200,
                'message' => 'Joined group chat successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error joining group chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to join group chat'], 500);
        }
    }

    /**
     * Leave group chat
     * POST /api/messaging/group-chat/{id}/leave
     */
    public function leaveGroupChat(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::where('id', $id)
                ->where('type', 'group')
                ->first();

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Group chat not found'], 404);
            }

            $participant = $conversation->participants()
                ->where('participant_id', $authUser['id'])
                ->where('participant_type', $authUser['type'])
                ->first();

            if (!$participant) {
                return response(['status' => 400, 'message' => 'Not a member'], 400);
            }

            $participant->update(['left_at' => now()]);

            return response([
                'status' => 200,
                'message' => 'Left group chat successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error leaving group chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to leave group chat'], 500);
        }
    }

    /**
     * Update group chat
     * PUT /api/messaging/group-chat/{id}
     */
    public function updateGroupChat(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $conversation = Conversation::where('id', $id)
                ->where('type', 'group')
                ->first();

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Group chat not found'], 404);
            }

            // Check if user is admin
            $participant = $conversation->participants()
                ->where('participant_id', $authUser['id'])
                ->where('participant_type', $authUser['type'])
                ->where('is_admin', true)
                ->first();

            if (!$participant) {
                return response(['status' => 403, 'message' => 'Only admins can update group chat'], 403);
            }

            $conversation->update($request->only(['name', 'description']));

            return response([
                'status' => 200,
                'message' => 'Group chat updated successfully',
                'data' => $conversation
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating group chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to update group chat'], 500);
        }
    }

    // ========================================================================
    // ORGANIZATION GROUP CHAT
    // ========================================================================

    /**
     * Create organization group chat
     * POST /api/messaging/group/organization/{organizationId}
     */
    public function createOrganizationGroupChat(Request $request, $organizationId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            // Check if organization chat already exists
            $existingConversation = Conversation::where('type', 'organization')
                ->where('organization_id', $organizationId)
                ->first();

            if ($existingConversation) {
                return response([
                    'status' => 200,
                    'message' => 'Organization chat already exists',
                    'data' => $existingConversation
                ], 200);
            }

            DB::beginTransaction();

            $conversation = Conversation::create([
                'type' => 'organization',
                'name' => $request->name ?? 'Organization Chat',
                'description' => $request->description,
                'organization_id' => $organizationId,
                'created_by' => $authUser['id'],
            ]);

            $conversation->addParticipant($authUser['id'], $authUser['type'], true);

            $conversation->load(['participants', 'lastMessage']);

            DB::commit();

            return response([
                'status' => 200,
                'message' => 'Organization chat created successfully',
                'data' => $conversation
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating organization chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to create organization chat'], 500);
        }
    }

    /**
     * Get organization group chat
     * GET /api/messaging/group/organization/{organizationId}
     */
    public function getOrganizationGroupChat(Request $request, $organizationId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::where('type', 'organization')
                ->where('organization_id', $organizationId)
                ->with(['participants', 'lastMessage'])
                ->first();

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Organization chat not found'], 404);
            }

            return response([
                'status' => 200,
                'message' => 'Organization chat retrieved successfully',
                'data' => $conversation
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting organization chat: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get organization chat'], 500);
        }
    }

    // ========================================================================
    // CHAT ROOMS
    // ========================================================================

    /**
     * Get chat rooms
     * GET /api/messaging/chat-rooms
     */
    public function getChatRooms(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $chatRooms = DB::table('chat_rooms')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response([
                'status' => 200,
                'message' => 'Chat rooms retrieved successfully',
                'data' => $chatRooms
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting chat rooms: ' . $e->getMessage());
            return response(['status' => 200, 'message' => 'Chat rooms retrieved', 'data' => []], 200);
        }
    }

    /**
     * Create chat room
     * POST /api/messaging/chat-rooms
     */
    public function createChatRoom(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'topic' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $chatRoomId = DB::table('chat_rooms')->insertGetId([
                'name' => $request->name,
                'description' => $request->description,
                'topic' => $request->topic,
                'created_by' => $authUser['id'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $chatRoom = DB::table('chat_rooms')->find($chatRoomId);

            return response([
                'status' => 200,
                'message' => 'Chat room created successfully',
                'data' => $chatRoom
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error creating chat room: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to create chat room'], 500);
        }
    }

    /**
     * Get chat room details
     * GET /api/messaging/chat-rooms/{id}
     */
    public function getChatRoom(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $chatRoom = DB::table('chat_rooms')
                ->where('id', $id)
                ->first();

            if (!$chatRoom) {
                return response(['status' => 404, 'message' => 'Chat room not found'], 404);
            }

            // Get recent messages
            $messages = DB::table('chat_room_messages')
                ->where('chat_room_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response([
                'status' => 200,
                'message' => 'Chat room retrieved successfully',
                'data' => [
                    'chat_room' => $chatRoom,
                    'messages' => $messages
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting chat room: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get chat room'], 500);
        }
    }

    /**
     * Send message to chat room
     * POST /api/messaging/chat-rooms/{id}/message
     */
    public function sendChatRoomMessage(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $chatRoom = DB::table('chat_rooms')
                ->where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$chatRoom) {
                return response(['status' => 404, 'message' => 'Chat room not found'], 404);
            }

            $messageId = DB::table('chat_room_messages')->insertGetId([
                'chat_room_id' => $id,
                'user_id' => $authUser['id'],
                'user_type' => $authUser['type'],
                'message' => $request->message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $message = DB::table('chat_room_messages')->find($messageId);

            return response([
                'status' => 200,
                'message' => 'Message sent successfully',
                'data' => $message
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error sending chat room message: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to send message'], 500);
        }
    }

    // ========================================================================
    // CONVERSATION MANAGEMENT
    // ========================================================================

    /**
     * Mark conversation as read
     * POST /api/messaging/conversations/{conversationId}/read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Conversation not found'], 404);
            }

            $participant = $conversation->participants()
                ->where('participant_id', $authUser['id'])
                ->where('participant_type', $authUser['type'])
                ->first();

            if (!$participant) {
                return response(['status' => 403, 'message' => 'Not a participant'], 403);
            }

            $participant->markAsRead();

            return response([
                'status' => 200,
                'message' => 'Conversation marked as read'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking conversation as read: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to mark as read'], 500);
        }
    }

    /**
     * Delete conversation
     * DELETE /api/messaging/conversations/{conversationId}
     */
    public function deleteConversation(Request $request, $conversationId)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return response(['status' => 404, 'message' => 'Conversation not found'], 404);
            }

            $participant = $conversation->participants()
                ->where('participant_id', $authUser['id'])
                ->where('participant_type', $authUser['type'])
                ->first();

            if (!$participant) {
                return response(['status' => 403, 'message' => 'Not a participant'], 403);
            }

            // Soft delete by marking participant as left
            $participant->update(['left_at' => now()]);

            return response([
                'status' => 200,
                'message' => 'Conversation deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting conversation: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to delete conversation'], 500);
        }
    }
}
