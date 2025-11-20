<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|in:All Users,Individual Users,Organizations',
            'users' => 'required_if:user_type,Individual Users|array',
            'organizations' => 'required_if:user_type,Organizations|array',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'redirect_url' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $notification = AppNotification::create($request->toArray());
        $api_response = null;
        if ($request->user_type == "All Users") {
            $notification->user_id = null;
            $notification->module = "admin";
            $api_response = Helper::sendPush($request->title,$request->message,null,$notification->id,"Annoucement",null,[]);
            $notification->api_response = $api_response;
            $notification->save();
        } else if ($request->user_type == 'Individual Users') {
            if (isset($request->users)) {
                $notification->user_pivots()->attach($request->users);
                $notification->module = "admin";
                $api_response = Helper::sendPush($request->title,$request->message,null,$notification->id,"Annoucement",null,$request->users);
                $notification->api_response = $api_response;
                $notification->save();
                $notification->load('users:users.id,first_name,last_name,profile_image,email,phone');
                $notification->loadCount('users');
            }
        } else {
            if (!empty($request->organizations)) {
                $notification->organization_pivots()->attach($request->organizations);
                $notification->module = "admin";

                $users = User::whereHas('organization', function ($query) use ($request) {
                    $query->whereIn('id', $request->organizations);
                })->pluck('id')->toArray();

                $chunks = array_chunk($users, 1500);
                foreach ($chunks as $chunk) {
                    $api_response = Helper::sendPush($request->title, $request->message, null, $notification->id, "Announcement", null, $chunk);
                }

                $notification->api_response = $api_response;
                $notification->save();
                $notification->load('organizations:organizations.id,name,logo,poc_email,poc_name');
                $notification->loadCount('organizations');


            }
        }

        $response = [
            "status" => 200,
            "message" => "Notification Send Successfully",
            "notification" => $notification
        ];
        return response($response, $response['status']);
    }

    public function getNotifications(Request $request)
    {
        $notifications = AppNotification::withCount('users','organizations')->with('users:users.id,first_name,last_name,profile_image,email,phone','organizations:organizations.id,name,logo,poc_email,poc_name')->where('module', 'admin')
            ->when($request->filled('user_type'), function ($query) use ($request) {
                $query->where('user_type', $request->query('user_type'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->where('title', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->latest();

        $response = Pagination::paginate($request, $notifications, 'notifications');
        return response($response, $response['status']);
    }
    public function deleteNotification(Request $request, $id)
    {
        $notification = AppNotification::where('module', 'admin')->find($id);

        if (isset($notification)) {
            $notification->delete();
            $response = [
                "status" => 200,
                "message" => "Notification Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Notification Not Found",
            ];
        }

        return response($response, $response['status']);
    }

    public function getUsersDropDown(Request $request)
    {
        $limit = $request->query('limit', 20);

        $users = User::where('is_active', 1)->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('first_name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE',  '%' . $request->search . '%');
            });
        })->limit($limit)->select('id', 'first_name', 'last_name', 'email', 'phone')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Users Fetched Successfully",
            "users" => $users
        ];

        return response($response, $response["status"]);
    }
}
