<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeCoach;
use App\Models\ChallengeOrganization;
use App\Models\ChallengeType;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChallengeController extends Controller
{
    public function addChallenge(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'type' => 'required|in:Threshold,Leaderboard',
            'cover_image' => 'required|image',
            'organizations' => 'required|array',
            'organizations.*' => 'exists:organizations,id',
            'coaches' => 'array',
            // 'coaches.*' => 'nullable|exists:coaches,id',
            'start_date' => 'required|date|date_format:Y-m-d'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }
        $challenges = [];
        $challenge = Challenge::create($request->toArray());
        $challenge->uploaded_by = $userId;
        $challenge->uploader = $role;
        if ($request->hasFile('cover_image')) {
            $filename   = time() . rand(111, 699) . '.' . $request->file('cover_image')->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/challenge_profiles/", $filename, $request->file('cover_image'));
            $challenge->cover_image = $file;
        }
        // $coaches = $request->filled('coaches') ? $request->coaches : [];
        if ($request->filled('organizations')) {
            foreach ($request->organizations as $key => $organization) {
                $copy = $key == 0 ? $challenge : $challenge->replicate();
                $copy->organization_id = $organization;
                $existOrganization = Organization::with('coaches')->find($organization);
                if (isset($existOrganization) && $existOrganization->coaches->isNotEmpty()) {
                    $coachIds = $existOrganization->coaches->pluck('id')->toArray();
                    $copy->coach_pivots()->attach($coachIds);
                }
                $copy->save();
                $copy->load('organization', 'coach','coaches','upload_by:id,first_name,last_name,profile_image');
                $challenges[] = $copy;
                Helper::createActionLog($userId, $role, 'challenges', 'add', null, $copy);
            }
        }
        $response = [
            "status" => 200,
            "message" => "Challenges Added Successfully",
            "challenges" => $challenges
        ];

        return response($response, $response["status"]);
    }

    public function updateChallenge(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'type' => 'in:Threshold,Leaderboard',
            'cover_image' => 'image',
            'organizations' => 'array|max:1',
            'organizations.*' => 'nullable|exists:organizations,id',
            // 'coaches' => 'array|max:1',
            // 'coaches.*' => 'nullable|exists:coaches,id',
            'start_date' => 'date|date_format:Y-m-d'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $challenge = Challenge::with('organization','coach')->find($id);
        if (isset($challenge)) {
            $before_data = $challenge->replicate();
            $before = basename($challenge->profile_image);
            $challenge->fill($request->toArray());
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if($request->filled('is_active'))
            {
                $challenge->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Challenge Active Successfully' : 'Challenge Blocked Successfully';
            }
            if ($request->hasFile('profile_image')) {
                $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/challenge_profiles/", $filename, $request->profile_image, $before);
                $challenge->profile_image = $file;
            }
            else{
                if($request->profile_image == 'removed')
                {
                 $challenge->profile_image = null;
                }
            }
            $challenge->organization_id = $request->organizations[0] ?? null;
            $existOrganization = Organization::with('coaches')->find($request->organizations[0]);
            if (isset($existOrganization) && $existOrganization->coaches->isNotEmpty()) {
                $coachIds = $existOrganization->coaches->pluck('id')->toArray();
                $challenge->coach_pivots()->sync($coachIds);
            }
            $challenge->save();
            $challenge->load('organization','coach','coaches','upload_by:id,first_name,last_name,profile_image');
            Helper::createActionLog($userId, $role, 'challenges','update',$before_data,$challenge);

            if($onlyIsActive)
            {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "challenge" => $challenge
                ];
                return response($response, $response["status"]);
            }
            else
            {
                $response = [
                    "status" => 200,
                    "message" => "Challenge Updated Successfully",
                    "challenge" => $challenge
                ];
            }
        } else {
            $response = [
                "status" => 422,
                "message" => "Challenge Not Found",
            ];
        }

        return response($response, $response["status"]);
    }

    public function getChallenges(Request $request)
    {
        $now = Carbon::now()->toDateString();
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $challenges = Challenge::with('organization','coach','coaches','upload_by:id,first_name,last_name,profile_image')->select('*')
            ->selectRaw('DATE_ADD(start_date, INTERVAL duration DAY) AS end_date')
            ->when($request->filled('status'), function ($query) use ($request, $now) {
                if ($request->query('status') == 'Current') {
                    $query->where('start_date', '<=', $now)
                        ->whereRaw('DATE_ADD(start_date, INTERVAL duration DAY) >= ?', [$now]);
                } else if ($request->query('status') == 'Upcoming') {
                    $query->where('start_date', '>', $now);
                } else if ($request->query('status') == 'Completed') {
                    $query->whereRaw('DATE_ADD(start_date, INTERVAL duration DAY) < ?', [$now]);
                }
            })
            ->when($role == "Coach",function($query) use ($userId){
                $query->whereHas('coaches',function($subquery) use($userId){
                    $subquery->where('coach_id', $userId);
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->where('title', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('organization_id'), function ($query) use ($request) {
                $query->where('organization_id', $request->query('organization_id'));
            })
            ->when($request->filled('coach_id'), function ($query) use ($request) {
                $query->where('coach_id', $request->query('coach_id'));
            })
            ->latest();

        $response = Pagination::paginate($request, $challenges, 'challenges');

        return response($response, $response["status"]);
    }


    public function getChallenge(Request $request, $id)
    {
        $challenge = Challenge::with('coach:id,first_name,last_name,profile_image','coaches:coaches.id,first_name,last_name,profile_image','organization:id,name,logo','users:users.id,first_name,last_name,profile_image','upload_by:id,first_name,last_name,profile_image')
        ->find($id);
        if (isset($challenge)) {
            $response = [
                "status" => 200,
                "message" => "Challenge Fetched Successfully",
                "challenge" => $challenge
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Challenge Not Found",
            ];
        }

        return response($response, $response["status"]);
    }
    public function deleteChallenge(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $challenge = Challenge::find($id);
        if (isset($challenge)) {
            Helper::createActionLog($userId,$role,'challenges','delete',$challenge,null);
            $challenge->delete();
            $response = [
                "status" => 200,
                "message" => "Challenge Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Challenge Not Found",
            ];
        }

        return response($response, $response["status"]);
    }

    public function getChallengeTypes(Request $request)
    {
        $challenge_types = ChallengeType::where('is_active',1)->get();

        $response = [
            "status" => 200,
            "message" => "Challenge Types Fetched Successfully",
            "challenge_types" => $challenge_types,
        ];

        return response($response, $response["status"]);

    }

    /**
     * Get challenges dropdown
     */
    public function getChallengesDropdown(Request $request)
    {
        $challenges = Challenge::select('id', 'name', 'challenge_type')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Challenges Dropdown Retrieved Successfully',
            'data' => $challenges
        ]);
    }
}
