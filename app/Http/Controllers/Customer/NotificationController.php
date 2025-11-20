<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AppNotificationRead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $notifications = AppNotification::withCount(['readAt as is_read' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('users', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->orWhereHas('organizations', function ($query) use ($user) {
                        $query->whereHas('employees', function ($subquery) use ($user) {
                            $subquery->where('id', $user->id);
                        });
                    })
                    ->orWhere(function ($query) use ($user) {
                        $query->where('created_at', '>=', $user->created_at)
                            ->whereNotIn('user_type', ['Individual Users', 'Organizations'])
                            ->whereNull('user_id');
                    });
            })->when($request->filled('timestamp'), function ($query) use ($request) {
                $query->where("created_at", ">", $request->query('timestamp'));
            })
            ->when($request->filled("limit"), function ($query) use ($request) {
                $query->limit($request->query("limit"));
            })
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->latest()
            ->addSelect([
                "app_notifications.*",
                DB::raw("(CASE WHEN module = 'admin' THEN 'Announcement' ELSE 'Notification' END) as type")
            ]);

        $response = Pagination::paginate($request, $notifications, 'notifications');

        $response['notifications'] = $response['notifications']->groupBy(function ($notification) {
            return $notification->created_at->toDateString();
        });



        return response($response, $response["status"]);
    }

    public function readNotificaiton(Request $request, $id)
    {
        $notification = AppNotificationRead::where(['notification_id' => $id, 'user_id' => $request->user()->id])->first();
        if ($notification) {
            $response = [
                "status" => 200,
                "message" => "Already Read",
            ];
        } else {

            $readAt = new AppNotificationRead();
            $readAt->user_id = $request->user()->id;
            $readAt->notification_id = $id;
            $readAt->save();
            $response = [
                "status" => 200,
                "message" => "Notification Read Successfully",
                "notification" => $readAt,
            ];
        }

        return response($response, $response["status"]);
    }

    public function readAllNotifications(Request $request)
    {
        $user = $request->user();
        $notifications = AppNotification::whereDoesntHave('readAt', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->orWhereHas('organizations', function ($query) use ($user) {
                    $query->whereHas('employees', function ($subquery) use ($user) {
                        $subquery->where('id', $user->id);
                    });
                })
                ->orWhere(function ($query) use ($user) {
                    $query->where('created_at', '>=', $user->created_at)
                        ->whereNotIn('user_type', ['Individual Users', 'Organizations'])
                        ->whereNull('user_id');
                });
        })->get();

        foreach ($notifications as $notification) {
            $readAt = new AppNotificationRead();
            $readAt->user_id = $request->user()->id;
            $readAt->notification_id = $notification->id;
            $readAt->save();
        }

        $response = [
            "status" => 200,
            "message" => "Notifications Read Successfully",
        ];

        return response($response, $response["status"]);
    }
}
