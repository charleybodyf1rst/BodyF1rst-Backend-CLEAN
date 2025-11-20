<?php

namespace App\Http\Controllers;

use App\Models\Inbox;
use App\Models\InboxChat;
use App\Events\MessageSent;
use App\Helpers\Pagination;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        // SECURITY FIX: Ensure user is authenticated
        $role = $request->role;
        if (!$role || !in_array(strtolower($role), ['user', 'coach', 'admin'])) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: Invalid role"
            ], 401);
        }

        $authUser = Auth::guard(strtolower($role))->user();
        if (!$authUser) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: User not authenticated"
            ], 401);
        }

        $userId = $authUser->id;

        $validator = Validator::make($request->all(), [
            'inbox_id' => 'required',
            'message' => 'required_without:attachment',
            'attachment' => 'required_without:message'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $inbox = Inbox::find($request->inbox_id);

        if (!isset($inbox)) {
            $response = [
                "status" => 422,
                "message" => "Inbox not found",
            ];
            return response($response, $response["status"]);
        }

        // SECURITY FIX: Verify user has access to this inbox
        if ($role == "User" && $inbox->user_id != $userId) {
            return response([
                "status" => 403,
                "message" => "Forbidden: You don't have access to this inbox"
            ], 403);
        }

        if ($role == "Coach" && $inbox->coach_id != $userId) {
            return response([
                "status" => 403,
                "message" => "Forbidden: You don't have access to this inbox"
            ], 403);
        }

        // SECURITY FIX: Use actual authenticated user data instead of hardcoded values
        $new_message = new InboxChat();
        $new_message->inbox_id = $request->inbox_id; // Use actual inbox_id from request
        $new_message->message = $request->message;
        $new_message->sender_id = $userId; // Use authenticated user's ID
        $new_message->sender_role = $role; // Use authenticated user's role
        if(isset($request->attachment))
        {
            $new_message->attachment = $request->attachment;
        }
        $new_message->save();

        broadcast(new MessageSent($new_message));

        $response = [
            "status" => 200,
            "message" => "Message sent successfully",
            "new_message" => $new_message
        ];

        return response($response, $response["status"]);
    }

    public function getInboxChat(Request $request)
    {
        // SECURITY FIX: Ensure user is authenticated
        $role = $request->role;
        if (!$role || !in_array(strtolower($role), ['user', 'coach', 'admin'])) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: Invalid role"
            ], 401);
        }

        $authUser = Auth::guard(strtolower($role))->user();
        if (!$authUser) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: User not authenticated"
            ], 401);
        }

        $userId = $authUser->id;

        if ($role == "Coach" && !$request->filled("user_id")) {
            return response([
                "status" => 422,
                "message" => "User id is required"
            ], 422);
        }
        if ($role == "User" && !$request->filled("coach_id")) {
            return response([
                "status" => 422,
                "message" => "Coach id is required"
            ], 422);
        }

        $otherUserId = $role == "Coach" ? $request->query('user_id') : $request->query('coach_id');

        if ($userId == $otherUserId) {
            return response([
                "status" => 422,
                "message" => "You cannot send a message to yourself"
            ], 422);
        }


        $inbox = Inbox::with("user:id,first_name,last_name,profile_image", "coach:id,name,profile_image")
            ->with(["messages" => function ($query) use ($request) {
                if ($request->filled('timestamp')) {
                    $query->where("created_at", ">", $request->query('timestamp'));
                }}])
            ->where(function ($query) use ($userId, $otherUserId) {
                $query->where(function($que) use ($userId, $otherUserId) {
                    $que->where("user_id", $userId)
                        ->where("coach_id", $otherUserId);
                })->orWhere(function($que) use ($userId, $otherUserId) {
                    $que->where("user_id", $otherUserId)
                        ->where("coach_id", $userId);
                });
            })->first();

        if (!isset($inbox)) {
            if ($role == "Coach") {
                $userId = $request->query('user_id');
                $otherUserId = $userId;
            } else {
                $userId = $userId;
                $otherUserId = $request->query('coach_id');
            }

            $new_inbox = new Inbox();
            $new_inbox->user_id =  $userId;
            $new_inbox->coach_id = $otherUserId;
            $new_inbox->save();

            $new_inbox->load(["user:id,first_name,last_name,profile_image", "coach:id,name,profile_image", "messages"]);

            $response = [
                "status" => 200,
                "message" => "Messages Fetched",
                "inbox_chat" => $new_inbox,
            ];
        } else {

            $inbox->load(["user:id,first_name,last_name,profile_image", "coach:id,name,profile_image", "messages"]);

            InboxChat::where("inbox_id", $inbox->id)
                ->where("sender_id", "<>", $userId)
                ->where("sender_role",$role)
                ->where("has_read", 0)
                ->update(["has_read" => 1]);

            $response = [
                "status" => 200,
                "message" => "Messages Fetched",
                "inbox_chat" => $inbox,
            ];
        }

        return response($response, $response["status"]);
    }



    public function getMyInboxChats(Request $request)
    {
        // SECURITY FIX: Ensure user is authenticated
        $role = $request->role;
        if (!$role || !in_array(strtolower($role), ['user', 'coach', 'admin'])) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: Invalid role"
            ], 401);
        }

        $authUser = Auth::guard(strtolower($role))->user();
        if (!$authUser) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: User not authenticated"
            ], 401);
        }

        $userId = $authUser->id;

        $inbox_chats = Inbox::select('inboxes.*')
            ->leftJoin(DB::raw('(SELECT inbox_id, MAX(created_at) AS last_message_created_at, MAX(id) AS last_message_id FROM inbox_chats GROUP BY inbox_id) as subquery'), function ($join) {
                $join->on('inboxes.id', '=', 'subquery.inbox_id');
            })
            ->whereHas('last_message')
            ->with([
                'last_message' => function ($query) {
                    $query->with(["vehicle" => function($query){
                        $query->with("make:id,title", "model:id,title", "trim:id,title", "location:id,title")
                            ->select("id", "make_id", "model_id", "trim_id", "location_id", "year", "price", "cover_image");
                    }])->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');
                },
                'user:id,first_name,last_name,profile_image',
                'coach:id,name,profile_image',
            ])
            ->withCount(['messages' => function ($query) use ($userId) {
                $query->select(DB::raw('
                    COUNT(CASE WHEN inbox_chats.attachment IS NULL THEN 1 ELSE NULL END) +
                    COALESCE(SUM(CASE WHEN inbox_chats.attachment IS NOT NULL THEN JSON_LENGTH(inbox_chats.attachment) ELSE 0 END), 0)
                '))
                ->where('inbox_chats.sender_id', '<>', $userId)
                ->where('inbox_chats.has_read', 0);
            }])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('coach_id', $userId);
            })
            ->when($request->filled('search'), function ($query) use ($request, $userId) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search, $userId) {
                    $q->where(function ($q1) use ($search, $userId) {
                        $q1->where('user_id', $userId)
                        ->whereHas('coach', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->orWhere(function ($q1) use ($search, $userId) {
                        $q1->where('coach_id', $userId)
                        ->whereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });
                    });
                });
            })
            ->orderBy('subquery.last_message_created_at', 'DESC')
            ->orderBy('subquery.last_message_id', 'DESC');

        $response = Pagination::paginate($request, $inbox_chats, 'inbox_chats');

        return response($response, $response["status"]);
    }


    public function getInboxChatbyID(Request $request, $id)
    {
        // SECURITY FIX: Ensure user is authenticated
        $role = $request->role;
        if (!$role || !in_array(strtolower($role), ['user', 'coach', 'admin'])) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: Invalid role"
            ], 401);
        }

        $authUser = Auth::guard(strtolower($role))->user();
        if (!$authUser) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: User not authenticated"
            ], 401);
        }

        $userId = $authUser->id;
        $limit = $request->query('limit', 50);
        $inbox_chat = Inbox::with(["user:id,first_name,last_name,profile_image", "coach:id,name,profile_image", "messages" => function ($query) use ($request, $limit) {
            if ($request->filled('timestamp')) {
                $query->where("created_at", ">", $request->query('timestamp'));
            }
            if ($request->filled('beforeTimestamp')) {
                $query->where("created_at", "<", $request->query('beforeTimestamp'));
            }
            if ($request->filled('message_id')) {
                $query->where("id", "<", $request->query('message_id'));
            }
            $query->latest()->limit($limit);
        }])->find($id);

        if (isset($inbox_chat)) {
            // SECURITY FIX: Verify user has access to this inbox
            if ($role == "User" && $inbox_chat->user_id != $userId) {
                return response([
                    "status" => 403,
                    "message" => "Forbidden: You don't have access to this inbox"
                ], 403);
            }

            if ($role == "Coach" && $inbox_chat->coach_id != $userId) {
                return response([
                    "status" => 403,
                    "message" => "Forbidden: You don't have access to this inbox"
                ], 403);
            }
            InboxChat::where("inbox_id", $id)
                ->where("sender_id", "<>", $userId)
                ->where('sender_role',$role)
                ->where("has_read", 0)
                ->update(["has_read" => 1]);

            $response = [
                "status" => 200,
                "message" => "Chat Fetched Successfully",
                "inbox_chat" => $inbox_chat
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Inbox Not found"
            ];
        }

        return response($response, $response["status"]);
    }

    /**
     * Get Messages (Enhanced)
     * GET /api/chat/get-messages
     */
    public function getMessages(Request $request)
    {
        // SECURITY FIX: Ensure user is authenticated
        $role = $request->role;
        if (!$role || !in_array(strtolower($role), ['user', 'coach', 'admin'])) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: Invalid role"
            ], 401);
        }

        $authUser = Auth::guard(strtolower($role))->user();
        if (!$authUser) {
            return response([
                "status" => 401,
                "message" => "Unauthorized: User not authenticated"
            ], 401);
        }

        $userId = $authUser->id;

        $validator = Validator::make($request->all(), [
            'inbox_id' => 'required|integer|exists:inboxes,id',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'before_id' => 'integer',
            'after_id' => 'integer',
            'search' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        try {
            $inboxId = $request->inbox_id;
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 50);

            // Verify user has access to this inbox
            $inbox = Inbox::find($inboxId);
            if (!$inbox) {
                return response([
                    "status" => 404,
                    "message" => "Inbox not found"
                ], 404);
            }

            if ($role == "User" && $inbox->user_id != $userId) {
                return response([
                    "status" => 403,
                    "message" => "Forbidden: You don't have access to this inbox"
                ], 403);
            }

            if ($role == "Coach" && $inbox->coach_id != $userId) {
                return response([
                    "status" => 403,
                    "message" => "Forbidden: You don't have access to this inbox"
                ], 403);
            }

            // Build query
            $query = InboxChat::where('inbox_id', $inboxId)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');

            // Pagination filters
            if ($request->has('before_id')) {
                $query->where('id', '<', $request->before_id);
            }

            if ($request->has('after_id')) {
                $query->where('id', '>', $request->after_id);
            }

            // Search filter
            if ($request->has('search')) {
                $query->where('message', 'LIKE', "%{$request->search}%");
            }

            // Get messages with pagination
            $messages = $query->paginate($perPage, ['*'], 'page', $page);

            // Mark unread messages as read
            InboxChat::where('inbox_id', $inboxId)
                ->where('sender_id', '<>', $userId)
                ->where('has_read', 0)
                ->update(['has_read' => 1]);

            // Get sender details
            $inbox->load(['user:id,first_name,last_name,profile_image', 'coach:id,name,profile_image']);

            // Format response
            $formattedMessages = $messages->map(function($msg) use ($inbox) {
                $sender = null;
                if ($msg->sender_role == 'User') {
                    $sender = [
                        'id' => $inbox->user->id ?? null,
                        'name' => ($inbox->user->first_name ?? '') . ' ' . ($inbox->user->last_name ?? ''),
                        'avatar' => $inbox->user->profile_image ?? null,
                        'role' => 'User',
                    ];
                } else if ($msg->sender_role == 'Coach') {
                    $sender = [
                        'id' => $inbox->coach->id ?? null,
                        'name' => $inbox->coach->name ?? 'Coach',
                        'avatar' => $inbox->coach->profile_image ?? null,
                        'role' => 'Coach',
                    ];
                }

                return [
                    'id' => $msg->id,
                    'inboxId' => $msg->inbox_id,
                    'message' => $msg->message,
                    'attachment' => $msg->attachment,
                    'senderId' => $msg->sender_id,
                    'senderRole' => $msg->sender_role,
                    'sender' => $sender,
                    'hasRead' => (bool) $msg->has_read,
                    'createdAt' => $msg->created_at,
                    'updatedAt' => $msg->updated_at,
                ];
            });

            return response([
                "status" => 200,
                "message" => "Messages retrieved successfully",
                "messages" => $formattedMessages,
                "pagination" => [
                    'current_page' => $messages->currentPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'total_pages' => $messages->lastPage(),
                    'has_more' => $messages->hasMorePages(),
                ],
                "inbox" => [
                    'id' => $inbox->id,
                    'user' => [
                        'id' => $inbox->user->id ?? null,
                        'name' => ($inbox->user->first_name ?? '') . ' ' . ($inbox->user->last_name ?? ''),
                        'avatar' => $inbox->user->profile_image ?? null,
                    ],
                    'coach' => [
                        'id' => $inbox->coach->id ?? null,
                        'name' => $inbox->coach->name ?? 'Coach',
                        'avatar' => $inbox->coach->profile_image ?? null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response([
                "status" => 500,
                "message" => "Failed to retrieve messages",
                "error" => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

}
