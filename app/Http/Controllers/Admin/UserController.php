<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Imports\EmployeesImport;
use App\Models\DietaryRestriction;
use App\Models\EquipmentPreference;
use App\Models\Plan;
use App\Models\TrainingPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    //Employees/Users
    public function importEmployees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'organization_id' => 'required|exists:organizations,id'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        try {
            $import = new EmployeesImport($request->organization_id,'Admin',null,"Admin",1);
            Excel::import($import, $request->file('file'));

            $errors = $import->getErrors();
            if (!empty($errors)) {
                $response = [
                    "status" => 422,
                    "message" => "Some issues were found during the import.",
                    "errors" => $errors,
                ];
                return response($response, $response["status"]);
            }

            $response = [
                "status" => 200,
                "message" => "Employees imported successfully",
            ];
            return response($response, $response["status"]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                preg_match("/'(.+?)' for key/", $e->getMessage(), $matches);
                $duplicateEmail = $matches[1] ?? 'unknown email';
                $response = [
                    "status" => 422,
                    "message" => "This email {$duplicateEmail} has already been taken",
                ];
            } else {
                $response = [
                    "status" => 500,
                    "message" => "An error occurred while importing employees",
                    "error" => $e->getMessage(),
                ];
            }
            return response($response, $response["status"]);
        } catch (\Exception $e) {
            $response = [
                "status" => 500,
                "message" => "An error occurred while importing employees",
                "error" => $e->getMessage(),
            ];
            return response($response, $response["status"]);
        }
    }

    public function addEmployee(Request $request)
    {
        $role = $request->role;
        $user = Auth::guard(strtolower($role))->user();
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            // 'phone' => 'required|max:255',
            'email' => [
                'required',
                'max:255',
                'email:filter',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'organization_id' => 'nullable|exists:organizations,id',
            'coach_id' => 'nullable|exists:coaches,id',
            'weight' => 'numeric',
            'height' => 'numeric',
            'protein' => 'numeric',
            'carb' => 'numeric',
            'calorie' => 'numeric',
            'fat' => 'numeric',
            'bmr' => 'numeric',
            'tdee' => 'numeric',
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

        // Custom validation: organization_id OR coach_id must be present
        if (empty($request->organization_id) && empty($request->coach_id)) {
            $response = [
                "status" => 422,
                "message" => "Client must be assigned to either an organization or a coach",
            ];
            return response($response, $response["status"]);
        }
        // DB::beginTransaction();
        $employee = User::create($request->toArray());
        $employee->organization_id = $request->organization_id;
        $employee->coach_id = $request->coach_id;
        // $password = rand(000000,999999);
        $password = Helper::generatePassword();
        // SECURITY: Password reset link should be sent instead of plaintext password
        $employee->password = $password;
        if (isset($request->profile_image)) {
            $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/user_profiles/", $filename, $request->profile_image);
            $employee->profile_image = $file;

            $thumbnail   = time() . rand(111, 699) .'_thumbnail' . '.' . $request->profile_image->getClientOriginalExtension();
            $thumbnail_file = Helper::generateThumbnail("upload/user_profiles/thumbnails/", $thumbnail, $request->profile_image,100,100,40);
            $employee->profile_image_thumbnail = $thumbnail_file;
        }
        $employee->is_active = 1;
        $employee->first_login = 1;
        $employee->uploaded_by = $userId;
        $employee->uploader = $role;
        $employee->save();
        $employee->load('organization.coaches','coach','upload_by:id,first_name,last_name,profile_image');

        $organization = $employee->organization ? $employee->organization->name : null;
        $coach = $user ? $user->first_name . " " .$user->last_name : null;
        $name = $employee->first_name .' '.$employee->last_name;
        try {
            Helper::sendCredential("User", $name, $employee->email, $password,$organization,$coach,$role);
            if($request->filled('coach_id') && $role == "Admin")
            {
                $user = $employee;
                Helper::sendAssignedToCoach($employee->coach,$user);
            }
            $message = "Employee added successfully, and email sent to the user.";
            $mail_sent = true;
        } catch (\Exception $e) {
            $message = "Employee added successfully, but email could not be sent.";
            $mail_sent = false;
        }
        Helper::createActionLog($userId,$role,'users','add',null,$employee);
        // DB::commit();
        $response = [
            "status" => 200,
            "message" =>  $message,
            "employee" => $employee,
            "mail_sent" => $mail_sent
        ];

        return response($response, $response["status"]);
    }

    public function updateEmployee(Request $request, $id)
    {
        $role = $request->role;
        $user = Auth::guard(strtolower($role))->user();
        $userId = $user->id;
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'max:255',
                'email:filter',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('users')->ignore($id)->whereNull('deleted_at'),
            ],
            'organization_id' => 'nullable|exists:organizations,id',
            'coach_id' => 'nullable|exists:coaches,id',
            'weight' => 'numeric',
            'height' => 'numeric',
            'protein' => 'numeric',
            'carb' => 'numeric',
            'calorie' => 'numeric',
            'fat' => 'numeric',
            'bmr' => 'numeric',
            'tdee' => 'numeric',
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

        $employee = User::with('organization','coach')->find($id);
        if (isset($employee)) {
            $existingMail = $employee->email;
            $before_data = $employee->replicate();
            $before = basename($employee->profile_image);
            $beforeThumbnail = basename($employee->profile_image_thumbnail);
            $employee->fill($request->toArray());
            if($request->filled('organization_id'))
            {
                $employee->organization_id = $request->organization_id;
                $employee->coach_id = null;
            }
            if($request->filled('coach_id'))
            {
                $employee->coach_id = $request->coach_id;
                $employee->organization_id = null;
            }
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if($request->filled('is_active'))
            {
                $employee->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Employee Active Successfully' : 'Employee Blocked Successfully';
            }
            if ($request->hasFile('profile_image')) {
                $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/user_profiles/", $filename, $request->profile_image, $before);
                $employee->profile_image = $file;

                $thumbnail   = time() . rand(111, 699) .'_thumbnail' . '.' . $request->profile_image->getClientOriginalExtension();
                $thumbnail_file = Helper::generateThumbnail("upload/user_profiles/thumbnails/", $thumbnail, $request->profile_image,100,100,40,$beforeThumbnail);
                $employee->profile_image_thumbnail = $thumbnail_file;
            }
            else{
                if($request->profile_image == 'removed')
                {
                 $employee->profile_image = null;
                }
            }
            $employee->save();
            $employee->load('organization.coaches','coach','upload_by:id,first_name,last_name,profile_image');
            $mail_sent = true;
            $additional_message = "";
            if($existingMail != $employee->email)
            {
                $password = Helper::generatePassword();
                // SECURITY: Password reset link should be sent instead of plaintext password
                $employee->password = $password;
                $employee->save();
                $organization = $employee->organization ? $employee->organization->name : null;
                if($role == "Admin")
                {
                    $coach = $user ? $user->name : null;
                }
                else
                {
                    $coach = $user ? $user->first_name . " " .$user->last_name : null;
                }
                $name = $employee->first_name .' '.$employee->last_name;
                try {
                    Helper::sendCredential("User", $name, $employee->email, $password,$organization,$coach,$role);
                    if($request->filled('coach_id') && $role == "Admin")
                    {
                        $user = $employee;
                        Helper::sendAssignedToCoach($employee->coach,$user);
                    }
                    $additional_message = " and email sent to the user.";
                    $mail_sent = true;
                } catch (\Exception $e) {
                    $additional_message = " but email could not be sent.";
                    $mail_sent = false;
                }
            }

            Helper::createActionLog($userId,$role,'users','update',$before_data,$employee);
            if($onlyIsActive)
            {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "employee" => $employee
                ];
                return response($response, $response["status"]);
            }
            else
            {
                $response = [
                    "status" => 200,
                    "message" =>  'Employee Updated Successfully' . $additional_message,
                    "employee" => $employee,
                    "mail_sent" => $mail_sent
                ];
            }
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Employee Not Found',
            ];
        }

        return response($response, $response["status"]);
    }
    public function getEmployees(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $employees = User::with('organization.coaches','coach','upload_by:id,first_name,last_name,profile_image')->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('first_name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE',  '%' . $request->search . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->search . '%');
            });
        })
        ->when($role == "Coach",function($query) use ($role,$userId){
                $query->where(function($query) use ($role,$userId){
                    $query->whereHas('organization.coaches',function($subquery) use ($userId){
                        $subquery->where('coach_id',$userId);
                    })
                    ->orWhere('coach_id',$userId);
                });
        })
        ->when($request->filled('plan_id'), function($query) use ($request){
            $query->whereHas('assign_plans',function($subquery) use ($request){
                $subquery->where('plans.id',$request->query('plan_id'));
            });
        })
            ->when($request->filled('user_type'), function ($query) use ($request) {
                if($request->query('user_type') == 'Client')
                {
                    $query->whereNull('organization_id');
                }
                else if($request->query('user_type') == 'Employee')
                {
                    $query->whereNotNull('organization_id');
                }
            })
            ->when($request->filled('assignment_status'), function ($query) use ($request) {
                if($request->query('assignment_status') == 'Not Assigned')
                {
                    $query->whereNull('coach_id')->whereNull('organization_id');
                }
                else if($request->query('assignment_status') == 'Assigned')
                {
                    $query->where(function($subquery){
                        $subquery->whereNotNull('coach_id')
                        ->orWhereNotNull('organization_id');
                    });
                }
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
            ->when($request->filled('signup_status'),function($query) use ($request){
                if($request->query('signup_status') == 'Signup')
                {
                    $query->where('first_login',0);
                }
                else if($request->query('signup_status') == 'Not Signup')
                {
                    $query->where('first_login',1);
                }
            })
            ->when($request->filled('organization_id'), function ($query) use ($request) {
                $query->where('organization_id', $request->query('organization_id'));
            })
            ->when($request->filled('department'), function ($query) use ($request) {
                $query->where('department', $request->query('department'));
            })
            ->when($request->filled('uploader'),function($query) use ($request){
                $query->where('uploader',$request->query('uploader'));
            })
            ->when($request->filled('coach_id'),function($query) use ($request){
                $query->where('uploaded_by',$request->query('coach_id'))
                ->where('uploader',"Coach");
            })
            ->latest();

        $response = Pagination::paginate($request, $employees, 'employees');

        return response($response, $response["status"]);
    }
    public function getEmployee(Request $request, $id)
    {
        $employee = User::with('organization.coaches', 'coach','upload_by:id,first_name,last_name,profile_image')->find($id);

        if (isset($employee)) {
            $fitness_plans = collect();

            if ($employee->organization) {
                $employee->organization->load('assign_plans');
                $plans = $employee->organization->assign_plans;
            } else {
                $plans = Plan::whereHas('users', function ($query) use ($employee) {
                    $query->where('users.id', $employee->id);
                })->get();
            }

            if ($plans->isNotEmpty()) {
                $plans->load([
                    "upload_by:id,first_name,last_name,profile_image",
                    "workouts" => function ($query) {
                        $query->with([
                            "workout.exercise" => function ($subquery) {
                                $subquery->with('video')
                                    ->leftJoin('exercises', 'workout_exercises.exercise_id', '=', 'exercises.id')
                                    ->select(
                                        'workout_exercises.*',
                                        'workout_exercises.id as workout_exercise_id',
                                        'exercises.id as id',
                                        'exercises.title as title',
                                        'exercises.tags as tags',
                                        'exercises.description as description'
                                    );
                            }
                        ]);
                    },
                    'users:users.id,first_name,last_name,profile_image',
                    'organizations:organizations.id,name,logo'
                ]);

                foreach ($plans as $plan) {
                    $plan->total_weeks = $plan->totalWeeks();
                }

                $fitness_plans = $plans;
            }

            $response = [
                "status" => 200,
                "message" => "Employee Fetched Successfully",
                "employee" => $employee,
                "fitness_plans" => $fitness_plans
            ];
        }else
        {
            $response = [
                "status" => 422,
                "message" => "Employee Not Found!",
            ];
        }

        return response($response, $response['status']);
    }
    public function deleteEmployee(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $employee = User::find($id);

        if (isset($employee)) {
            $type = $employee->organization_id ? "Employee" : "Client";
            Helper::createActionLog($userId,$role,'users','delete',$employee,null);
            $employee->delete();
            $response = [
                "status" => 200,
                "message" => "Employee Delete Successfully",
                "type" => $type
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Employee Not Found"
            ];
        }
        return response($response, $response["status"]);
    }

    public function getUserDropDown(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $limit = $request->query('limit',10);
        $users = User::select('id','first_name','last_name','email')->withCount('coach')->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('first_name', 'LIKE', '%' . $request->search . '%')
                ->orWhere('last_name', 'LIKE', '%' . $request->search . '%')
                ->orWhere('email', 'LIKE', '%' . $request->search . '%')
                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE',  '%' . $request->search . '%');
            });
        })
        ->when($role == "Coach",function($query) use ($role,$userId){
                // $query->whereHas('coaches',function($subquery) use ($userId){
                    $query->where('coach_id',$userId);
                // });
        })
        ->whereNull('organization_id')
        ->where('is_active',1)->limit($limit)->latest()->get();

        $response = [
            "status" => 200,
            "message" => "User Fetched Successfully",
            "users" => $users
        ];

        return response($response, $response["status"]);
    }

    public function getUsersList(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        
        // Get pagination parameters from frontend (page, per_page)
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 50);
        $search = $request->query('search', '');
        $roleFilter = $request->query('role', '');
        
        // Build base query
        $query = User::with('organization:id,name', 'coach:id,first_name,last_name')
            ->select('id', 'email', 'first_name', 'last_name', 'organization_id', 'coach_id', 'is_active', 'created_at', 'last_login_at', 'subscription_type')
            ->when(!empty($search), function ($q) use ($search) {
                $q->where(function ($subquery) use ($search) {
                    $subquery->where('first_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%')
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $search . '%');
                });
            })
            ->when(!empty($roleFilter), function ($q) use ($roleFilter) {
                // Frontend sends role filter as: 'coach', 'admin', 'customer', 'employee'
                if ($roleFilter === 'customer') {
                    $q->whereNull('organization_id')->whereNull('coach_id');
                } elseif ($roleFilter === 'employee') {
                    $q->whereNotNull('organization_id');
                }
            });
        
        // Coach users should only see their own users
        if ($role === "Coach") {
            $query->where(function($q) use ($userId) {
                $q->whereHas('organization.coaches', function($subquery) use ($userId) {
                    $subquery->where('coach_id', $userId);
                })->orWhere('coach_id', $userId);
            });
        }
        
        // Get total count before pagination
        $totalUsers = $query->count();
        
        // Apply pagination
        $users = $query->latest('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $user->organization_id ? 'employee' : ($user->coach_id ? 'customer' : 'customer'),
                    'subscription_type' => $user->subscription_type ?? 'free',
                    'is_active' => (bool) $user->is_active,
                    'created_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
                    'last_login' => $user->last_login_at ? $user->last_login_at->toIso8601String() : null,
                ];
            });
        
        $response = [
            "status" => 200,
            "success" => true,
            "message" => "Users list fetched successfully",
            "users" => $users,
            "pagination" => [
                "page" => (int) $page,
                "per_page" => (int) $perPage,
                "total_users" => $totalUsers,
                "total_pages" => (int) ceil($totalUsers / $perPage),
            ]
        ];

        return response($response, $response["status"]);
    }

    public function getDietaryDropDown(Request $request)
    {
        $limit = $request->query('limit',10);
        $dietary_restrictions = DietaryRestriction::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->where('is_active',1)->limit($limit)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Dietary Restriction Fetched Successfully",
            "dietary_restrictions" => $dietary_restrictions
        ];

        return response($response, $response["status"]);
    }
    public function getTrainingDropDown(Request $request)
    {
        $limit = $request->query('limit',10);
        $training_preferences = TrainingPreference::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->where('is_active',1)->limit($limit)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Training Preference Fetched Successfully",
            "training_preferences" => $training_preferences
        ];

        return response($response, $response["status"]);
    }
    public function getEquipmentDropDown(Request $request)
    {
        $limit = $request->query('limit',10);
        $equipment_preferences = EquipmentPreference::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->where('is_active',1)->limit($limit)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Equipment Preference Fetched Successfully",
            "equipment_preferences" => $equipment_preferences
        ];

        return response($response, $response["status"]);
    }

    //Dietary Restrictions
    public function addDietaryRestriction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dietary_restrictions')->whereNull('deleted_at'),
            ]
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $dietary_restriction = DietaryRestriction::create($request->toArray());

        $response = [
            "status" => 200,
            "message" =>  'Dietary Resctriction Added Successfully',
            "dietary_restriction" => $dietary_restriction
        ];

        return response($response, $response["status"]);
    }
    public function updateDietaryRestriction(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dietary_restrictions')->ignore($id)->whereNull('deleted_at'),
            ]
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $dietary_restriction = DietaryRestriction::find($id);
        if (isset($dietary_restriction)) {
            $dietary_restriction->fill($request->toArray());
            $dietary_restriction->save();

            $response = [
                "status" => 200,
                "message" =>  'Dietary Resctriction Updated Successfully',
                "dietary_restriction" => $dietary_restriction
            ];
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Dietary Resctriction Not Found',
            ];
        }
        return response($response, $response["status"]);
    }

    public function getDietaryRestrictions(Request $request)
    {
        $dietary_restrictions = DietaryRestriction::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->latest();

        $response = Pagination::paginate($request, $dietary_restrictions, 'dietary_restrictions');

        return response($response, $response["status"]);
    }
    public function getDietaryRestriction(Request $request, $id)
    {
        $dietary_restriction = DietaryRestriction::find($id);

        if (isset($dietary_restriction)) {
            $response = [
                "status" => 200,
                "message" => "Dietary Restriction Fetched Successfully",
                "dietary_restriction" => $dietary_restriction
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Dietary Restriction Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function deleteDietaryRestriction(Request $request, $id)
    {
        $dietary_restriction = DietaryRestriction::find($id);

        if (isset($dietary_restriction)) {
            $dietary_restriction->delete();
            $response = [
                "status" => 200,
                "message" => "Dietary Restriction Delete Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Dietary Restriction Not Found"
            ];
        }
        return response($response, $response["status"]);
    }

    //Training Preferences
    public function addTrainingPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('training_preferences')->whereNull('deleted_at'),
            ]
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $training_preference = TrainingPreference::create($request->toArray());

        $response = [
            "status" => 200,
            "message" =>  'Training Preference Added Successfully',
            "training_preference" => $training_preference
        ];

        return response($response, $response["status"]);
    }
    public function updateTrainingPreference(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('training_preferences')->ignore($id)->whereNull('deleted_at'),
            ],
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $training_preference = TrainingPreference::find($id);
        if (isset($training_preference)) {
            $training_preference->fill($request->toArray());
            $training_preference->save();
            $response = [
                "status" => 200,
                "message" =>  'Training Preference Updated Successfully',
                "training_preference" => $training_preference
            ];
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Training Preference Not Found',
            ];
        }
        return response($response, $response["status"]);
    }

    public function getTrainingPreferences(Request $request)
    {
        $training_preferences = TrainingPreference::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->latest();

        $response = Pagination::paginate($request, $training_preferences, 'training_preferences');

        return response($response, $response["status"]);
    }
    public function getTrainingPreference(Request $request, $id)
    {
        $training_preference = TrainingPreference::find($id);

        if (isset($training_preference)) {
            $response = [
                "status" => 200,
                "message" => "Training Preference Fetched Successfully",
                "training_preference" => $training_preference
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Training Preference Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function deleteTrainingPreference(Request $request, $id)
    {
        $training_preference = TrainingPreference::find($id);

        if (isset($training_preference)) {
            $training_preference->delete();
            $response = [
                "status" => 200,
                "message" => "Training Preference Delete Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Training Preference Not Found"
            ];
        }
        return response($response, $response["status"]);
    }

    //Equipment Preferences
    public function addEquipmentPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('equipment_preferences')->whereNull('deleted_at'),
            ]
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $equipment_preference = EquipmentPreference::create($request->toArray());

        $response = [
            "status" => 200,
            "message" =>  'Equipment Preference Added Successfully',
            "equipment_preference" => $equipment_preference
        ];

        return response($response, $response["status"]);
    }
    public function updateEquipmentPreference(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('equipment_preferences')->ignore($id)->whereNull('deleted_at'),
            ]
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $equipment_preference = EquipmentPreference::find($id);
        if (isset($equipment_preference)) {
            $equipment_preference->fill($request->toArray());
            $equipment_preference->save();
            $response = [
                "status" => 200,
                "message" =>  'Equipment Preference Updated Successfully',
                "equipment_preference" => $equipment_preference
            ];
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Equipment Preference Not Found',
            ];
        }
        return response($response, $response["status"]);
    }

    public function getEquipmentPreferences(Request $request)
    {
        $equipment_preferences = EquipmentPreference::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->latest();

        $response = Pagination::paginate($request, $equipment_preferences, 'equipment_preferences');

        return response($response, $response["status"]);
    }
    public function getEquipmentPreference(Request $request, $id)
    {
        $equipment_preference = EquipmentPreference::find($id);

        if (isset($equipment_preference)) {
            $response = [
                "status" => 200,
                "message" => "Equipment Preference Fetched Successfully",
                "equipment_preference" => $equipment_preference
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Equipment Preference Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function deleteEquipmentPreference(Request $request, $id)
    {
        $equipment_preference = EquipmentPreference::find($id);

        if (isset($equipment_preference)) {
            $equipment_preference->delete();
            $response = [
                "status" => 200,
                "message" => "Equipment Preference Delete Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Equipment Preference Not Found"
            ];
        }
        return response($response, $response["status"]);
    }

    /**
     * Get user achievements
     */
    public function getUserAchievements(Request $request)
    {
        $userId = Auth::id();

        // Fetch user stats
        $completedWorkouts = DB::table('completed_workouts')->where('user_id', $userId)->count();
        $completedCBTLessons = DB::table('cbt_lesson_completions')->where('user_id', $userId)->count();
        $consecutiveDays = $this->getConsecutiveWorkoutDays($userId);
        $weighIns = DB::table('weight_logs')->where('user_id', $userId)->count();

        // Define achievements
        $achievements = [
            [
                'id' => 'first_workout',
                'title' => 'First Workout',
                'description' => 'Complete your first workout',
                'icon' => 'fitness',
                'unlocked' => $completedWorkouts >= 1,
                'progress' => min(100, $completedWorkouts * 100),
                'category' => 'fitness'
            ],
            [
                'id' => 'ten_workouts',
                'title' => 'Workout Warrior',
                'description' => 'Complete 10 workouts',
                'icon' => 'trophy',
                'unlocked' => $completedWorkouts >= 10,
                'progress' => min(100, ($completedWorkouts / 10) * 100),
                'category' => 'fitness'
            ],
            [
                'id' => 'fifty_workouts',
                'title' => 'Fitness Champion',
                'description' => 'Complete 50 workouts',
                'icon' => 'medal',
                'unlocked' => $completedWorkouts >= 50,
                'progress' => min(100, ($completedWorkouts / 50) * 100),
                'category' => 'fitness'
            ],
            [
                'id' => 'seven_day_streak',
                'title' => '7 Day Streak',
                'description' => 'Work out 7 days in a row',
                'icon' => 'flame',
                'unlocked' => $consecutiveDays >= 7,
                'progress' => min(100, ($consecutiveDays / 7) * 100),
                'category' => 'consistency'
            ],
            [
                'id' => 'first_cbt_lesson',
                'title' => 'Mental Wellness Beginner',
                'description' => 'Complete your first CBT lesson',
                'icon' => 'school',
                'unlocked' => $completedCBTLessons >= 1,
                'progress' => min(100, $completedCBTLessons * 100),
                'category' => 'mindset'
            ],
            [
                'id' => 'cbt_week_one',
                'title' => 'First Week Complete',
                'description' => 'Complete week 1 of CBT',
                'icon' => 'checkmark-circle',
                'unlocked' => $completedCBTLessons >= 7,
                'progress' => min(100, ($completedCBTLessons / 7) * 100),
                'category' => 'mindset'
            ],
            [
                'id' => 'weigh_in_warrior',
                'title' => 'Weigh-In Warrior',
                'description' => 'Log 10 weigh-ins',
                'icon' => 'analytics',
                'unlocked' => $weighIns >= 10,
                'progress' => min(100, ($weighIns / 10) * 100),
                'category' => 'tracking'
            ]
        ];

        return response()->json([
            'status' => 200,
            'data' => $achievements
        ]);
    }

    /**
     * Helper: Get consecutive workout days
     */
    private function getConsecutiveWorkoutDays($userId)
    {
        $workouts = DB::table('completed_workouts')
            ->where('user_id', $userId)
            ->orderBy('completed_at', 'desc')
            ->pluck('completed_at')
            ->toArray();

        if (empty($workouts)) {
            return 0;
        }

        $consecutiveDays = 1;
        $previousDate = \Carbon\Carbon::parse($workouts[0])->startOfDay();

        for ($i = 1; $i < count($workouts); $i++) {
            $currentDate = \Carbon\Carbon::parse($workouts[$i])->startOfDay();
            $daysDiff = $previousDate->diffInDays($currentDate);

            if ($daysDiff == 1) {
                $consecutiveDays++;
                $previousDate = $currentDate;
            } else {
                break;
            }
        }

        return $consecutiveDays;
    }

    /**
     * Get users dropdown (extended)
     */
    public function getUsersDropdown(Request $request)
    {
        // Call existing method if available
        if (method_exists($this, 'getUserDropDown')) {
            return $this->getUserDropDown($request);
        }

        $users = User::select('id', 'first_name', 'last_name', 'email')
            ->where('is_active', 1)
            ->orderBy('first_name')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Users Dropdown Retrieved Successfully',
            'data' => $users
        ]);
    }
}
