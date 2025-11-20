<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function getMessages(Request $request)
    {
        try {
            $coachId = auth()->id();

            $messages = DB::table('coach_messages')
                ->where('coach_id', $coachId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json(['success' => true, 'data' => $messages]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function sendMessage(Request $request, $clientId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $coachId = auth()->id();

            $messageId = DB::table('coach_messages')->insertGetId([
                'coach_id' => $coachId,
                'client_id' => $clientId,
                'message' => $request->message,
                'sent_by' => 'coach',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Message sent successfully', 'data' => ['id' => $messageId]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error sending message'], 500);
        }
    }

    /**
     * Get all conversations for the authenticated user
     * GET /api/conversations
     *
     * Legacy compatibility endpoint for frontend calls to /conversations.php
     */
    public function getConversations(Request $request)
    {
        try {
            $userId = auth()->id();
            $userType = $request->get('user_type', 'client'); // client, coach, or admin

            // Get conversations based on user type
            if ($userType === 'coach') {
                $conversations = DB::table('conversations')
                    ->where('coach_id', $userId)
                    ->orderBy('last_message_at', 'desc')
                    ->get()
                    ->map(function ($conversation) {
                        return [
                            'id' => $conversation->id,
                            'client_id' => $conversation->client_id,
                            'client_name' => DB::table('users')->where('id', $conversation->client_id)->value('name'),
                            'last_message' => $conversation->last_message,
                            'last_message_at' => $conversation->last_message_at,
                            'unread_count' => $conversation->unread_count_coach ?? 0
                        ];
                    });
            } else {
                // Client view
                $conversations = DB::table('conversations')
                    ->where('client_id', $userId)
                    ->orderBy('last_message_at', 'desc')
                    ->get()
                    ->map(function ($conversation) {
                        return [
                            'id' => $conversation->id,
                            'coach_id' => $conversation->coach_id,
                            'coach_name' => DB::table('users')->where('id', $conversation->coach_id)->value('name'),
                            'last_message' => $conversation->last_message,
                            'last_message_at' => $conversation->last_message_at,
                            'unread_count' => $conversation->unread_count_client ?? 0
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'data' => $conversations,
                'count' => $conversations->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching conversations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update message status (read, archived, etc.)
     * PUT /api/messages/update
     *
     * Legacy compatibility endpoint for frontend calls to /index.php (PUT)
     */
    public function updateMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|integer|exists:coach_messages,id',
            'status' => 'nullable|in:read,unread,archived',
            'is_read' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();
            $messageId = $request->message_id;

            // Verify user has access to this message
            $message = DB::table('coach_messages')
                ->where('id', $messageId)
                ->where(function ($query) use ($userId) {
                    $query->where('coach_id', $userId)
                          ->orWhere('client_id', $userId);
                })
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or access denied'
                ], 404);
            }

            // Build update data
            $updateData = ['updated_at' => now()];

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            if ($request->has('is_read')) {
                $updateData['is_read'] = $request->is_read;
                $updateData['read_at'] = $request->is_read ? now() : null;
            }

            // Update the message
            DB::table('coach_messages')
                ->where('id', $messageId)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully',
                'data' => ['id' => $messageId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating message: ' . $e->getMessage()
            ], 500);
        }
    }
}
