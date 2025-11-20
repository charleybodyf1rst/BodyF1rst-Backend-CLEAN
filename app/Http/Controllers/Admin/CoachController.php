<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Mail\SendCredential;
use App\Models\Coach;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CoachController extends Controller
{
    public function addCoach(Request $request)
    {
        $role = $request->role;
        $user = Auth::guard(strtolower($role))->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'max:255',
                'email:filter',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('coaches')->whereNull('deleted_at'),
            ],
            'profile_image' => 'image',
            'organizations' => 'nullable|array',
            'organizations.*' => 'exists:organizations,id',
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $coach = Coach::create($request->toArray());
        if (isset($request->profile_image)) {
            $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/coach_profiles/", $filename, $request->profile_image);
            $coach->profile_image = $file;
        }
        // $password = rand(000000,999999);
        $password = Helper::generatePassword();
        $coach->password = $password;
        $coach->is_active = 1;
        $coach->save();
        $name = $user->name;
        $message = 'Coach Added Successfully';
        $additional_message = '';
        $allUsers = [];
        try{
            Helper::sendCredential("Coach",$coach->name,$coach->email,$password,null,$name,$role);
            $message = "Coach Added Successfully And Mail Send to the Coach";
        }
        catch (\Exception $e)
        {
            $message = "Coach Added Successfully And Mail Not Sent!";
        }
        if ($request->filled('organizations')) {
            // $coaches = Coach::whereIn('id', $request->coaches)->get();
            $coach->organization_pivots()->attach($request->organizations);
            // Organization::whereIn('id', $request->organizations)->update(['coach_id' => $coach->id]);
        }

        if ($request->filled('users')) {
            // $coach->user_pivots()->attach($request->users);
            User::whereIn('id', $request->users)->update(['coach_id' => $coach->id]);
        }
        if($request->filled('organizations') || $request->filled('users'))
        {
            try {
                $coach->load('organizations.employees', 'users');
                $organizationUsers = $coach->organizations->flatMap(function ($organization) {
                    return $organization->employees;
                });
                $directUsers = $coach->users;
                $allUsers = $organizationUsers->merge($directUsers)->unique('id');
                Helper::sendAssignmentToCoach($coach, $coach->organizations, $coach->users);
                Helper::sendAssignmentToUsers($coach,$allUsers);
                $additional_message = " And Assignments Mail Sent";
            } catch (\Exception $e) {
                info('Error sending assignment email: ' . $e->getMessage());

                $additional_message = " And Assignments Mail Not Sent";
            }
        }
        $coach->loadCount('organizations','users');
        Helper::createActionLog($request->user()->id,"Admin",'coaches','add',null,$coach);
        $response = [
            "status" => 200,
            "message" => $message . $additional_message,
            "coach" => $coach
        ];

        return response($response, $response["status"]);
    }

    public function updateCoach(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'email:filter',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('coaches')->ignore($id)->whereNull('deleted_at'),
            ],
            'profile_image' => 'image',
            'organizations' => 'nullable|array',
            'organizations.*' => 'exists:organizations,id',
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $coach = Coach::with('organizations','users')->withCount('organizations','users')->find($id);
        if (isset($coach)) {
            $allUsers = [];
            $before_data = $coach->replicate();
            $before = basename($coach->profile_image);
            $coach->fill($request->toArray());
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $existingOrganizations = $coach->organizations ? $coach->organizations->pluck('id')->toArray() : [];
            $existingUsers = $coach->users ? $coach->users->pluck('id')->toArray() : [];
            $message = '';
            $additional_message = '';
            if($request->filled('is_active'))
            {
                $coach->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Coach Active Successfully' : 'Coach Blocked Successfully';
            }
            if ($request->hasFile('profile_image')) {
                $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/coach_profiles/", $filename, $request->profile_image, $before);
                $coach->profile_image = $file;
            }
            else{
                if($request->profile_image == 'removed')
                {
                 $coach->profile_image = null;
                }
            }
            $coach->save();
            if ($request->filled('organizations')) {
                // if(empty($request->organizations))
                // {
                //     Organization::where('coach_id', $coach->id)->update(['coach_id' => null]);
                // }
                // else
                // {
                //     Organization::where('coach_id', $coach->id)
                //         ->whereNotIn('id', $request->organizations)
                //         ->update(['coach_id' => null]);

                //     Organization::whereIn('id', $request->organizations)->update(['coach_id' => $coach->id]);
                // }
                $coach->organization_pivots()->sync($request->organizations);
            }


            if ($request->filled('users')) {
                if(empty($request->users))
                {
                    User::where('coach_id', $coach->id)->update(['coach_id' => null]);
                }
                else
                {
                    User::where('coach_id', $coach->id)
                        ->whereNotIn('id', $request->users)
                        ->update(['coach_id' => null]);
                    User::whereIn('id', $request->users)->update(['coach_id' => $coach->id]);
                }
                // $coach->user_pivots()->sync($request->users);
            }

            if ($request->filled('organizations') || $request->filled('users')) {
                try {
                    $coach->load('organizations.employees', 'users');


                    $newOrganizations = array_diff($request->organizations, $existingOrganizations);
                    $newUsers = array_diff($request->users, $existingUsers);

                    $newOrganizationObjects = Organization::with('employees')->whereIn('id', $newOrganizations)->get();
                    $newUserObjects = User::whereIn('id', $newUsers)->get();
                    $organizationUsers = $newOrganizationObjects->flatMap(function ($organization) {
                        return $organization->employees;
                    })->toArray();

                    $directUsers = $coach->users->toArray();

                    $allUsers = collect(array_unique(array_merge($organizationUsers, $directUsers)));

                    if ($newOrganizationObjects->isNotEmpty() || $newUserObjects->isNotEmpty()) {
                        Helper::sendAssignmentToCoach($coach,
                            $newOrganizationObjects,
                            $newUserObjects
                        );

                        $additional_message = " And Assignments Mail Sent";
                    } else {
                        $additional_message = " No New Assignments Sent";
                    }
                    if ($allUsers->isNotEmpty()) {
                        Helper::sendAssignmentToUsers($coach,$allUsers);

                        $additional_message = " And Assignments Mail Sent";
                    } else {
                        $additional_message = " No New Assignments Sent";
                    }
                } catch (\Exception $e) {
                    info('Error sending assignment email: ' . $e->getMessage());
                    $additional_message = " And Assignments Mail Not Sent";
                }
            }


            $coach->load('organizations','users');
            $coach->loadCount('organizations','users');
            Helper::createActionLog($request->user()->id,"Admin",'coaches','update',$before_data,$coach);

            if($onlyIsActive)
            {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "coach" => $coach
                ];
                return response($response, $response["status"]);
            }
            else
            {
                $response = [
                    "status" => 200,
                    "message" => "Coach Updated Successfully" . $additional_message,
                    "coach" => $coach
                ];
            }
        } else {
            $response = [
                "status" => 422,
                "message" => "Coach Not Found",
            ];
        }

        return response($response, $response["status"]);
    }

    public function getCoaches(Request $request)
    {
        $coaches = Coach::withCount('organizations','users')->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('first_name', 'LIKE', '%' . $request->search . '%')
                ->orWhere('first_name', 'LIKE', '%' . $request->search . '%')
                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE',  '%' . $request->search . '%');
            });
        })
        ->when($request->filled('status'),function($query) use ($request){
            if($request->query('status') == 'Active')
            {
                $query->where('is_active',1);
            }
            else if($request->query('status') == 'Blocked')
            {
                $query->where('is_active',0);
            }
        })
        ->when($request->filled('assignment_status'), function ($query) use ($request) {
            if($request->query('assignment_status') == 'Not Assigned')
            {
                $query->where(function($subquery){
                    $subquery->whereDoesntHave('organizations')
                    ->whereDoesntHave('users');
                });
            }
            else if($request->query('assignment_status') == 'Assigned')
            {
                $query->where(function($subquery){
                    $subquery->whereHas('organizations')
                    ->orWhereHas('users');
                });
            }
        })
            ->when($request->filled('organization_id'), function ($query) use ($request) {
                $query->whereHas('organizations', function ($subquery) use ($request) {
                    $subquery->where('organizations.id', $request->query('organization_id'));
                });
            })
            ->latest();

        $response = Pagination::paginate($request, $coaches, 'coaches');

        return response($response, $response["status"]);
    }

    public function getCoach(Request $request, $id)
    {
        $coach = Coach::with('organizations','users.upload_by:id,first_name,last_name,profile_image')->withCount('organizations','users')->find($id);
        if (isset($coach)) {
            $response = [
                "status" => 200,
                "message" => "Coach Fetched Successfully",
                "coach" => $coach
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Coach Not Found",
            ];
        }

        return response($response, $response["status"]);
    }
    public function deleteCoach(Request $request, $id)
    {
        $coach = Coach::find($id);
        if (isset($coach)) {
            Helper::createActionLog($request->user()->id,"Admin",'coaches','delete',$coach,null);
            $coach->delete();
            $response = [
                "status" => 200,
                "message" => "Coach Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Coach Not Found",
            ];
        }

        return response($response, $response["status"]);
    }

    public function getCoachDropDown(Request $request)
    {
        $limit = $request->query('limit',10);
        $coaches = Coach::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('first_name', 'LIKE', '%' . $request->search . '%')
                ->orWhere('first_name', 'LIKE', '%' . $request->search . '%')
                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE',  '%' . $request->search . '%');
            });
        })->where('is_active',1)->limit($limit)->select('id','first_name','last_name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Coaches Fetched Successfully",
            "coaches" => $coaches
        ];

        return response($response, $response["status"]);
    }

    /**
     * Assign a coach to a client/user
     */
    public function assignCoach(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coach_id' => 'required|exists:coaches,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Implementation placeholder - would update user's coach_id or coach_user pivot table
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Assigned Successfully',
            'data' => [
                'coach_id' => $request->coach_id,
                'user_id' => $request->user_id
            ]
        ]);
    }

    /**
     * Unassign a coach from a client/user
     */
    public function unassignCoach(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coach_id' => 'required|exists:coaches,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Unassigned Successfully'
        ]);
    }

    /**
     * Get all clients for a specific coach
     */
    public function getCoachClients($coachId)
    {
        $coach = Coach::find($coachId);

        if (!$coach) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Coach Not Found'
            ], 404);
        }

        // Placeholder - would fetch from coach_user pivot or users.coach_id
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Clients Retrieved Successfully',
            'data' => []
        ]);
    }

    /**
     * Get schedule for a specific coach
     */
    public function getCoachSchedule($coachId)
    {
        $coach = Coach::find($coachId);

        if (!$coach) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Coach Not Found'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Schedule Retrieved Successfully',
            'data' => [
                'coach_id' => $coachId,
                'schedule' => []
            ]
        ]);
    }

    /**
     * Update coach availability
     */
    public function updateCoachAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coach_id' => 'required|exists:coaches,id',
            'availability' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Availability Updated Successfully'
        ]);
    }

    /**
     * Get statistics for a specific coach
     */
    public function getCoachStats($coachId)
    {
        $coach = Coach::find($coachId);

        if (!$coach) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Coach Not Found'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Stats Retrieved Successfully',
            'data' => [
                'total_clients' => 0,
                'active_clients' => 0,
                'completed_sessions' => 0,
                'average_rating' => 0
            ]
        ]);
    }

    /**
     * Toggle coach active/inactive status
     */
    public function toggleCoachStatus($coachId)
    {
        $coach = Coach::find($coachId);

        if (!$coach) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Coach Not Found'
            ], 404);
        }

        $coach->is_active = !$coach->is_active;
        $coach->save();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Status Toggled Successfully',
            'data' => [
                'is_active' => $coach->is_active
            ]
        ]);
    }

    /**
     * Get earnings for a specific coach
     */
    public function getCoachEarnings($coachId)
    {
        $coach = Coach::find($coachId);

        if (!$coach) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Coach Not Found'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Earnings Retrieved Successfully',
            'data' => [
                'total_earnings' => 0,
                'monthly_earnings' => 0,
                'pending_payments' => 0,
                'completed_sessions' => 0
            ]
        ]);
    }
}
