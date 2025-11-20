<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Notification Controller
 * Handles bulk notification sending and user management
 */
class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:admin', 'role']);
    }

    /**
     * Send Notification (Bulk)
     * POST /api/admin/send-notification
     */
    public function sendNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|string|in:info,success,warning,error,announcement',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'targetType' => 'required|string|in:all,specific,organization,department,role,active,inactive',
            'targetIds' => 'required_if:targetType,specific,organization,department|array',
            'targetIds.*' => 'integer',
            'roleFilter' => 'nullable|string|in:admin,user,trainer,nutritionist',
            'scheduledFor' => 'nullable|date',
            'expiresAt' => 'nullable|date|after:scheduledFor',
            'actionUrl' => 'nullable|url',
            'actionLabel' => 'nullable|string|max:50',
            'sendEmail' => 'boolean',
            'sendPush' => 'boolean',
            'sendSms' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Determine target users based on targetType
            $targetUsers = $this->getTargetUsers(
                $request->targetType,
                $request->targetIds,
                $request->roleFilter
            );

            if ($targetUsers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found matching the target criteria',
                ], 400);
            }

            $notificationData = [
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'priority' => $request->priority,
                'action_url' => $request->actionUrl,
                'action_label' => $request->actionLabel,
                'scheduled_for' => $request->scheduledFor ?? now(),
                'expires_at' => $request->expiresAt,
                'sent_by' => Auth::id(),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $notifications = [];
            $emailQueue = [];
            $pushQueue = [];
            $smsQueue = [];

            // Create notification records for each target user
            foreach ($targetUsers as $user) {
                $userNotification = array_merge($notificationData, ['user_id' => $user->id]);
                $notifications[] = $userNotification;

                // Queue email notifications
                if ($request->sendEmail && $user->email) {
                    $emailQueue[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                    ];
                }

                // Queue push notifications
                if ($request->sendPush && $user->push_token) {
                    $pushQueue[] = [
                        'user_id' => $user->id,
                        'push_token' => $user->push_token,
                    ];
                }

                // Queue SMS notifications
                if ($request->sendSms && $user->phone) {
                    $smsQueue[] = [
                        'user_id' => $user->id,
                        'phone' => $user->phone,
                    ];
                }
            }

            // Bulk insert notifications
            DB::table('notifications')->insert($notifications);

            // Send email notifications (using Laravel queues in production)
            if (!empty($emailQueue)) {
                $this->sendEmailNotifications($emailQueue, $request->title, $request->message);
            }

            // Send push notifications
            if (!empty($pushQueue)) {
                $this->sendPushNotifications($pushQueue, $request->title, $request->message);
            }

            // Send SMS notifications
            if (!empty($smsQueue)) {
                $this->sendSmsNotifications($smsQueue, $request->message);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifications sent successfully',
                'summary' => [
                    'totalRecipients' => count($notifications),
                    'emailsSent' => count($emailQueue),
                    'pushNotificationsSent' => count($pushQueue),
                    'smsSent' => count($smsQueue),
                    'scheduledFor' => $notificationData['scheduled_for'],
                    'expiresAt' => $notificationData['expires_at'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Users Dropdown
     * GET /api/admin/get-users-drop-down
     */
    public function getUsersDropDown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'organizationId' => 'nullable|integer|exists:organizations,id',
            'departmentId' => 'nullable|integer|exists:departments,id',
            'role' => 'nullable|string|in:admin,user,trainer,nutritionist',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $limit = $request->input('limit', 50);

            $query = User::select('id', 'name', 'email', 'role', 'avatar', 'organization_id', 'department_id', 'status')
                ->with(['organization:id,name', 'department:id,name']);

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            // Organization filter
            if ($request->has('organizationId')) {
                $query->where('organization_id', $request->organizationId);
            }

            // Department filter
            if ($request->has('departmentId')) {
                $query->where('department_id', $request->departmentId);
            }

            // Role filter
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Default to active users only
                $query->where('status', 'active');
            }

            $users = $query->orderBy('name', 'asc')
                ->limit($limit)
                ->get();

            $formattedUsers = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                    'status' => $user->status,
                    'organization' => [
                        'id' => $user->organization_id,
                        'name' => $user->organization->name ?? null,
                    ],
                    'department' => [
                        'id' => $user->department_id,
                        'name' => $user->department->name ?? null,
                    ],
                    'displayLabel' => $user->name . ' (' . $user->email . ')',
                ];
            });

            // Get summary statistics
            $stats = [
                'totalUsers' => User::count(),
                'activeUsers' => User::where('status', 'active')->count(),
                'organizations' => Organization::count(),
                'departments' => Department::count(),
            ];

            return response()->json([
                'success' => true,
                'users' => $formattedUsers,
                'stats' => $stats,
                'total' => $formattedUsers->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load users',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Helper: Get Target Users
     */
    protected function getTargetUsers($targetType, $targetIds, $roleFilter)
    {
        $query = User::where('status', 'active');

        switch ($targetType) {
            case 'all':
                // All active users
                break;

            case 'specific':
                // Specific user IDs
                $query->whereIn('id', $targetIds ?? []);
                break;

            case 'organization':
                // All users in specified organizations
                $query->whereIn('organization_id', $targetIds ?? []);
                break;

            case 'department':
                // All users in specified departments
                $query->whereIn('department_id', $targetIds ?? []);
                break;

            case 'role':
                // Users with specific role
                if ($roleFilter) {
                    $query->where('role', $roleFilter);
                }
                break;

            case 'active':
                // Users active in last 7 days
                $query->where('last_login', '>=', now()->subDays(7));
                break;

            case 'inactive':
                // Users inactive for 30+ days
                $query->where(function($q) {
                    $q->where('last_login', '<', now()->subDays(30))
                      ->orWhereNull('last_login');
                });
                break;
        }

        // Apply role filter if specified
        if ($roleFilter && $targetType !== 'role') {
            $query->where('role', $roleFilter);
        }

        return $query->select('id', 'name', 'email', 'phone', 'push_token')->get();
    }

    /**
     * Helper: Send Email Notifications
     */
    protected function sendEmailNotifications($emailQueue, $title, $message)
    {
        // In production, this should use Laravel queues
        // For now, we'll just log it
        foreach ($emailQueue as $recipient) {
            // Queue email job
            // Mail::to($recipient['email'])->queue(new NotificationEmail($title, $message));

            // For now, just log
            \Log::info("Email notification queued for {$recipient['email']}: {$title}");
        }
    }

    /**
     * Helper: Send Push Notifications
     */
    protected function sendPushNotifications($pushQueue, $title, $message)
    {
        // In production, integrate with Firebase Cloud Messaging or similar
        foreach ($pushQueue as $recipient) {
            // Send push notification
            // PushNotificationService::send($recipient['push_token'], $title, $message);

            // For now, just log
            \Log::info("Push notification queued for user {$recipient['user_id']}: {$title}");
        }
    }

    /**
     * Helper: Send SMS Notifications
     */
    protected function sendSmsNotifications($smsQueue, $message)
    {
        // In production, integrate with Twilio or similar
        foreach ($smsQueue as $recipient) {
            // Send SMS
            // SmsService::send($recipient['phone'], $message);

            // For now, just log
            \Log::info("SMS notification queued for {$recipient['phone']}: {$message}");
        }
    }
}
