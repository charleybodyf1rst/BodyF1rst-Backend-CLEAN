<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageFlag;
use App\Models\Message;
use App\Models\BlockedUser;
use App\Models\User;
use App\Models\Coach;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MessageModerationController extends Controller
{
    /**
     * Get flagged messages
     */
    public function getFlaggedMessages(Request $request)
    {
        try {
            $query = MessageFlag::with(['message', 'message.sender', 'message.conversation', 'flagger'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by flag type
            if ($request->filled('flag_type')) {
                $query->where('flag_type', $request->flag_type);
            }

            // Filter by date range
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $flags = $query->paginate($request->query('per_page', 20));

            return response([
                'status' => 200,
                'message' => 'Flagged messages retrieved successfully',
                'data' => $flags
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting flagged messages: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get flagged messages'], 500);
        }
    }

    /**
     * Get single flagged message details
     */
    public function getFlaggedMessageDetails(Request $request, $flagId)
    {
        try {
            $flag = MessageFlag::with([
                'message',
                'message.sender',
                'message.conversation',
                'message.editHistory',
                'message.flags',
                'flagger',
                'reviewer'
            ])->find($flagId);

            if (!$flag) {
                return response(['status' => 404, 'message' => 'Flagged message not found'], 404);
            }

            return response([
                'status' => 200,
                'message' => 'Flagged message details retrieved successfully',
                'data' => $flag
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting flagged message details: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get flagged message details'], 500);
        }
    }

    /**
     * Review flagged message
     */
    public function reviewFlaggedMessage(Request $request, $flagId)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:dismiss,warn,delete_message,ban_user',
            'review_notes' => 'nullable|string',
            'ban_duration_days' => 'required_if:action,ban_user|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            DB::beginTransaction();

            $flag = MessageFlag::with('message')->find($flagId);

            if (!$flag) {
                return response(['status' => 404, 'message' => 'Flagged message not found'], 404);
            }

            // Mark flag as reviewed
            $flag->markAsReviewed($admin->id, $request->review_notes);

            // Take action based on admin decision
            switch ($request->action) {
                case 'dismiss':
                    $flag->update(['status' => 'dismissed']);
                    break;

                case 'warn':
                    $flag->update(['status' => 'actioned']);
                    // Send warning notification to user
                    // Implement warning notification logic here
                    break;

                case 'delete_message':
                    $flag->update(['status' => 'actioned']);
                    $flag->message->update(['is_deleted' => true]);
                    break;

                case 'ban_user':
                    $flag->update(['status' => 'actioned']);
                    $message = $flag->message;

                    // Ban user logic
                    if ($message->sender_type === 'user') {
                        User::where('id', $message->sender_id)->update([
                            'is_banned' => true,
                            'banned_until' => now()->addDays($request->ban_duration_days),
                            'ban_reason' => $request->review_notes
                        ]);
                    } elseif ($message->sender_type === 'coach') {
                        Coach::where('id', $message->sender_id)->update([
                            'is_banned' => true,
                            'banned_until' => now()->addDays($request->ban_duration_days),
                            'ban_reason' => $request->review_notes
                        ]);
                    }

                    // Also delete the message
                    $flag->message->update(['is_deleted' => true]);
                    break;
            }

            DB::commit();

            return response([
                'status' => 200,
                'message' => 'Flagged message reviewed successfully',
                'data' => $flag
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error reviewing flagged message: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to review flagged message'], 500);
        }
    }

    /**
     * Get moderation statistics
     */
    public function getModerationStats(Request $request)
    {
        try {
            $fromDate = $request->query('from_date', now()->subDays(30));
            $toDate = $request->query('to_date', now());

            $stats = [
                'total_flags' => MessageFlag::whereBetween('created_at', [$fromDate, $toDate])->count(),
                'pending_flags' => MessageFlag::pending()->whereBetween('created_at', [$fromDate, $toDate])->count(),
                'reviewed_flags' => MessageFlag::reviewed()->whereBetween('created_at', [$fromDate, $toDate])->count(),

                'flags_by_type' => MessageFlag::whereBetween('created_at', [$fromDate, $toDate])
                    ->select('flag_type', DB::raw('count(*) as count'))
                    ->groupBy('flag_type')
                    ->get(),

                'flags_by_status' => MessageFlag::whereBetween('created_at', [$fromDate, $toDate])
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get(),

                'total_messages' => Message::whereBetween('created_at', [$fromDate, $toDate])->count(),

                'flagged_message_percentage' => 0,

                'top_flagged_users' => MessageFlag::with('message.sender')
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->select('message_id')
                    ->get()
                    ->groupBy(function($flag) {
                        return $flag->message->sender_id . '-' . $flag->message->sender_type;
                    })
                    ->map(function($group) {
                        return count($group);
                    })
                    ->sortDesc()
                    ->take(10),

                'average_review_time' => MessageFlag::reviewed()
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->whereNotNull('reviewed_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, reviewed_at)) as avg_minutes')
                    ->value('avg_minutes'),
            ];

            // Calculate flagged message percentage
            if ($stats['total_messages'] > 0) {
                $stats['flagged_message_percentage'] = round(
                    ($stats['total_flags'] / $stats['total_messages']) * 100,
                    2
                );
            }

            return response([
                'status' => 200,
                'message' => 'Moderation statistics retrieved successfully',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting moderation stats: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get moderation statistics'], 500);
        }
    }

    /**
     * Get reported users
     */
    public function getReportedUsers(Request $request)
    {
        try {
            $reportedUsers = MessageFlag::with('message.sender')
                ->select('message_id')
                ->get()
                ->groupBy(function($flag) {
                    return $flag->message->sender_id . '-' . $flag->message->sender_type;
                })
                ->map(function($group) {
                    $firstFlag = $group->first();
                    $sender = $firstFlag->message->sender;

                    return [
                        'user_id' => $firstFlag->message->sender_id,
                        'user_type' => $firstFlag->message->sender_type,
                        'user_name' => $sender->first_name ?? $sender->name ?? 'Unknown',
                        'total_flags' => count($group),
                        'latest_flag_date' => $group->max('created_at'),
                    ];
                })
                ->sortByDesc('total_flags')
                ->values();

            return response([
                'status' => 200,
                'message' => 'Reported users retrieved successfully',
                'data' => $reportedUsers
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting reported users: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get reported users'], 500);
        }
    }

    /**
     * Ban user
     */
    public function banUser(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_type' => 'required|in:user,coach',
            'duration_days' => 'required|integer|min:1',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            if ($request->user_type === 'user') {
                User::where('id', $request->user_id)->update([
                    'is_banned' => true,
                    'banned_until' => now()->addDays($request->duration_days),
                    'ban_reason' => $request->reason,
                    'banned_by' => $admin->id,
                ]);
            } elseif ($request->user_type === 'coach') {
                Coach::where('id', $request->user_id)->update([
                    'is_banned' => true,
                    'banned_until' => now()->addDays($request->duration_days),
                    'ban_reason' => $request->reason,
                    'banned_by' => $admin->id,
                ]);
            }

            return response([
                'status' => 200,
                'message' => 'User banned successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error banning user: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to ban user'], 500);
        }
    }

    /**
     * Unban user
     */
    public function unbanUser(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_type' => 'required|in:user,coach',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            if ($request->user_type === 'user') {
                User::where('id', $request->user_id)->update([
                    'is_banned' => false,
                    'banned_until' => null,
                    'ban_reason' => null,
                ]);
            } elseif ($request->user_type === 'coach') {
                Coach::where('id', $request->user_id)->update([
                    'is_banned' => false,
                    'banned_until' => null,
                    'ban_reason' => null,
                ]);
            }

            return response([
                'status' => 200,
                'message' => 'User unbanned successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error unbanning user: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to unban user'], 500);
        }
    }

    /**
     * Get moderation logs
     */
    public function getModerationLogs(Request $request)
    {
        try {
            $logs = MessageFlag::with(['message', 'message.sender', 'reviewer'])
                ->whereNotNull('reviewed_at')
                ->orderBy('reviewed_at', 'desc')
                ->paginate($request->query('per_page', 20));

            return response([
                'status' => 200,
                'message' => 'Moderation logs retrieved successfully',
                'data' => $logs
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting moderation logs: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to get moderation logs'], 500);
        }
    }

    /**
     * Bulk review flags
     */
    public function bulkReviewFlags(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'flag_ids' => 'required|array',
            'flag_ids.*' => 'integer|exists:message_flags,id',
            'action' => 'required|in:dismiss,delete_messages',
            'review_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        try {
            DB::beginTransaction();

            $flags = MessageFlag::with('message')->whereIn('id', $request->flag_ids)->get();

            foreach ($flags as $flag) {
                $flag->markAsReviewed($admin->id, $request->review_notes);

                if ($request->action === 'dismiss') {
                    $flag->update(['status' => 'dismissed']);
                } elseif ($request->action === 'delete_messages') {
                    $flag->update(['status' => 'actioned']);
                    $flag->message->update(['is_deleted' => true]);
                }
            }

            DB::commit();

            return response([
                'status' => 200,
                'message' => 'Flags reviewed successfully',
                'processed_count' => count($flags)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk review: ' . $e->getMessage());
            return response(['status' => 500, 'message' => 'Failed to review flags'], 500);
        }
    }
}
