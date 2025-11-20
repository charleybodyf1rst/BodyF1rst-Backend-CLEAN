<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Imports\EmployeesImport;
use App\Models\Admin;
use App\Models\Coach;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationSubmission;
use App\Models\RewardProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class OrganizationController extends Controller
{
    //Departments
    public function addDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->whereNull('deleted_at'),
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

        $department = Department::create($request->toArray());

        $response = [
            "status" => 200,
            "message" =>  'Department Added Successfully',
            "department" => $department
        ];

        return response($response, $response["status"]);
    }
    public function updateDepartment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('departments')->ignore($id)->whereNull('deleted_at'),
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

        $department = Department::find($id);
        if (isset($department)) {
            $department->fill($request->toArray());

            $response = [
                "status" => 200,
                "message" =>  'Department Updated Successfully',
                "department" => $department
            ];
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Department Not Found',
            ];
        }
        return response($response, $response["status"]);
    }

    public function getDepartments(Request $request)
    {
        $departments = Department::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->latest();

        $response = Pagination::paginate($request, $departments, 'departments');

        return response($response, $response["status"]);
    }
    public function getDepartment(Request $request, $id)
    {
        $department = Department::find($id);

        if (isset($department)) {
            $response = [
                "status" => 200,
                "message" => "Department Fetched Successfully",
                "department" => $department
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Department Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function deleteDepartment(Request $request, $id)
    {
        $department = Department::find($id);

        if (isset($department)) {
            $department->delete();
            $response = [
                "status" => 200,
                "message" => "Department Delete Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Department Not Found"
            ];
        }
        return response($response, $response["status"]);
    }

    public function getDepartmentDropDown(Request $request)
    {
        $limit = $request->query('limit',10);
        $departments = Department::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->where('is_active',1)->limit($limit)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Department Fetched Successfully",
            "departments" => $departments
        ];

        return response($response, $response["status"]);
    }

    //Rewards
    public function addReward(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('reward_programs')->whereNull('deleted_at'),
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

        $reward = RewardProgram::create($request->toArray());

        $response = [
            "status" => 200,
            "message" =>  'Reward Added Successfully',
            "reward" => $reward
        ];

        return response($response, $response["status"]);
    }
    public function updateReward(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('reward_programs')->ignore($id)->whereNull('deleted_at'),
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

        $reward = RewardProgram::find($id);
        if (isset($reward)) {
            $reward->fill($request->toArray());

            $response = [
                "status" => 200,
                "message" =>  'Reward Updated Successfully',
                "reward" => $reward
            ];
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Reward Not Found',
            ];
        }
        return response($response, $response["status"]);
    }

    public function getRewards(Request $request)
    {
        $rewards = RewardProgram::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->latest();

        $response = Pagination::paginate($request, $rewards, 'rewards');

        return response($response, $response["status"]);
    }
    public function getReward(Request $request, $id)
    {
        $reward = RewardProgram::find($id);

        if (isset($reward)) {
            $response = [
                "status" => 200,
                "message" => "Reward Fetched Successfully",
                "reward" => $reward
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Reward Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function deleteReward(Request $request, $id)
    {
        $reward = RewardProgram::find($id);

        if (isset($reward)) {
            $reward->delete();
            $response = [
                "status" => 200,
                "message" => "Reward Delete Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Reward Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function getRewardDropDown(Request $request)
    {
        $limit = $request->query('limit',10);
        $rewards = RewardProgram::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })->where('is_active',1)->limit($limit)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Rewards Fetched Successfully",
            "rewards" => $rewards
        ];

        return response($response, $response["status"]);
    }

    //Organizations
    public function addOrganization(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organizations')->whereNull('deleted_at'),
            ],
            // 'address' => 'required',
            // 'website' => 'required',
            'contract_start_date' => 'required|date|before:contract_end_date',
            'contract_end_date' => 'required|date|after:contact_start_date',
            'logo' => 'image',
            'poc_name' => 'required|string|max:255',
            'poc_email' => 'required',
            'rewards' => 'array',
            'departments' => 'array|min:1',
            'coaches' => 'array',
            'coaches.*'=>'nullable|exists:coaches,id',
            'file' => 'file'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }
        // try{
        // DB::beginTransaction();
        $organization = Organization::create($request->toArray());
        // $organization->contract_start_date = Carbon::now()->toDateString();
        if(isset($request->coaches))
        {
            $coaches = Coach::whereIn('id', $request->coaches)->get();
            Helper::sendOrganizationAssignedToCoach($coaches,$organization);
            $organization->coach_pivots()->attach($request->coaches);
        }
        $existingDepartments = [];
        $departments = [];
        if (is_array($request->departments) && !empty($request->departments)) {
            $existingDepartments = Department::whereIn('name', $request->departments)->pluck('name')->toArray();
            $departments = array_values(array_diff($request->departments, $existingDepartments));
        }
        if ($request->hasFile('logo')) {
            $filename   = time() . rand(111, 699) . '.' . $request->logo->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/organization_profiles/", $filename, $request->logo);
            $organization->logo = $file;
        }
        $organization->token = Helper::generateToken();
        $organization->is_active = 1;
        $organization->save();
        $message = "";
        if($request->hasFile('file'))
        {
            $type = "Admin";
            $import = new EmployeesImport($organization->id,$type,$organization->name,$role,$userId);
            Excel::import($import, $request->file('file'));

            $errors = $import->getErrors();
            $imported_array = $import->getDepartments();
            if(!empty($imported_array))
            {
                $departments = array_merge($imported_array,$departments);
            }
            if (!empty($errors)) {
                $file = $import->getFile();
                $message = $import->getMessage();
                $is_mailed = $import->checkMailedCount();
                $afterCount = $organization->employees()->count();
                $organization['is_user_added'] = ($afterCount > 0) ? 1 : 0;
                $response = [
                    "status" => 422,
                    "message" => "Organisation have created successfully. An email has been sent to the Point of Contact (POC) to complete the organization submission form.But Some issues were found during the import.".$message,
                    // "errors" => $errors,
                    "file" => $file,
                    "is_mailed" => $is_mailed > 0 ? false : true,
                    "organization" => $organization,
                    "departments" => $departments
                ];
                Helper::sendOrganizationSubmissionForm($organization);
                return response($response, $response["status"]);
            }
        }
        $afterCount = $organization->employees()->count();
        $organization['is_user_added'] = ($afterCount > 0) ? 1 : 0;
        $organization->load('coaches');
        $employees = User::where('organization_id',$organization->id)->latest();
        $organization['employees'] = Pagination::paginate($request, $employees, 'employees');
        Helper::sendOrganizationSubmissionForm($organization);
        Helper::createActionLog($userId,$role,'organizations','add',null,$organization);
        // DB::commit();
        $response = [
            "status" => 200,
            "message" =>  'Organisation have created successfully. An email has been sent to the Point of Contact (POC) to complete the organization submission form '.$message,
            "organization" => $organization,
            "departments" => $departments
        ];

        return response($response, $response["status"]);
    // }catch (\Exception $e) {
    //     $response = [
    //         "status" => 500,
    //         "message" => "An error occurred while creating organization",
    //         "error" => $e->getMessage(),
    //     ];
    //     return response($response, $response["status"]);
    // }
    }
    public function updateOrganization(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'name' => [
                'string',
                'max:255',
                Rule::unique('organizations')->ignore($id)->whereNull('deleted_at'),
            ],
            'logo' => 'image',
            'contract_start_date' => 'date|before:contract_end_date',
            'contract_end_date' => 'date|after:contact_start_date',
            'rewards' => 'array',
            'departments' => 'array|min:1',
            'coaches' => 'array',
            'coaches.*'=>'nullable|exists:coaches,id',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $organization = Organization::with('coaches')->find($id);
        if (isset($organization)) {
            $beforeCount = $organization->employees()->count();
            $before_data = $organization->replicate();
            $before = basename($organization->logo);
            $organization->fill($request->toArray());
            $existingCoaches = $organization->coaches->pluck('id')->toArray();
            if (isset($request->coaches)) {
                $newCoaches = array_diff($request->coaches, $existingCoaches);
                if (!empty($newCoaches)) {
                    $newCoachesData = Coach::whereIn('id', $newCoaches)->get();
                    Helper::sendOrganizationAssignedToCoach($newCoachesData, $organization);
                }
                $organization->coach_pivots()->sync($request->coaches);
            }
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if($request->filled('is_active'))
            {
                $organization->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Organization Active Successfully' : 'Organization Blocked Successfully';
            }
            if ($request->hasFile('logo')) {
                $filename   = time() . rand(111, 699) . '.' . $request->logo->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/organization_profiles/", $filename, $request->logo, $before);
                $organization->logo = $file;
            }
            else
            {
                if($request->logo == 'removed')
                {
                 $organization->logo = null;
                }
            }
            $organization->save();
            $importMessage = "";
            if(isset($request->departments))
            {
                $existingDepartments = Department::whereIn('name', $request->departments)->pluck('name')->toArray();
                $departments = array_values(array_diff($request->departments, $existingDepartments));
            }
            else
            {
                $departments = null;
            }

            if($request->hasFile('file'))
            {
                if($request->filled('token'))
                {
                    $type = "POC";
                    $import = new EmployeesImport($organization->id,$type,$organization->name,$role,$userId);
                    Excel::import($import, $request->file('file'));

                    $errors = $import->getErrors();
                    $users_count = $import->getUsersCount();
                    if (!empty($errors)) {
                        $file = $import->getFile();
                        $importMessage = $import->getMessage();
                        $is_mailed = $import->checkMailedCount();

                        $response = [
                            "status" => 422,
                            "message" => $importMessage,
                            "file" => $file,
                            "is_mailed" => $is_mailed > 0 ? false : true,
                        ];
                        return response($response, $response["status"]);
                    }
                    else
                    {

                        $organization_submission = new OrganizationSubmission();
                        $organization_submission->organization_id = $id;
                        if($request->hasFile('file'))
                        {
                            $filename   = time() . rand(111, 699) . '.' . $request->file->getClientOriginalExtension();
                            $file = Helper::uploadedImage("upload/organization_submissions/", $filename, $request->file, $before);
                            $organization_submission->file = $file;
                        }
                        $organization_submission->total_users = $users_count;
                        $organization_submission->save();

                        $user = Admin::first();
                        if(isset($user))
                        {
                            Helper::sendOrganizationSubmitted($user,$organization);
                        }
                        $organization->token = null;
                        $organization->save();
                    }
                }
                else
                {
                    $organization_submission = OrganizationSubmission::where('organization_id',$id)->latest()->first();
                    if(isset($organization_submission))
                    {
                        $organization_submission->status = "Uploaded";
                        $organization_submission->save();
                        if (!empty($existingCoaches)) {
                            $coaches = Coach::whereIn('id', $existingCoaches)->get();

                            if ($coaches->isNotEmpty()) {
                                Helper::sendNewOnBoarding($coaches,$organization);
                            }
                        }
                    }
                    $type = "Admin";
                    $import = new EmployeesImport($organization->id,$type,$organization->name,$role,$userId);
                    Excel::import($import, $request->file('file'));

                    $errors = $import->getErrors();
                    $imported_array = $import->getDepartments();
                    if(!empty($imported_array))
                    {
                        $departments = array_merge($imported_array,$departments);
                    }
                    if (!empty($errors)) {
                        $file = $import->getFile();
                        $importMessage = $import->getMessage();
                        $is_mailed = $import->checkMailedCount();
                        $afterCount = $organization->employees()->count();
                        $organization['is_user_added'] = ($afterCount > $beforeCount) ? 1 : 0;
                        $organization->load('coaches');
                        $employees = User::where('organization_id',$organization->id)->latest();
                        $organization['employees'] = Pagination::paginate($request, $employees, 'employees');
                        $response = [
                            "status" => 422,
                            "message" => "Organization Updated Successfully but Some issues were found during the import. ".$importMessage,
                            // "errors" => $errors,
                            "file" => $file,
                            "is_mailed" => $is_mailed > 0 ? false : true,
                            "organization" => $organization,
                            "departments" => $departments

                        ];
                        return response($response, $response["status"]);
                    }
                }
            }
            if($request->filled('file'))
            {
                if(!$request->hasFile('file'))
                {
                    $fileUrl = $request->input('file');
                    $fileName = basename($fileUrl);
                    $file = public_path('upload/organization_submissions/' . $fileName);
                    $organization_submission = OrganizationSubmission::where('organization_id',$id)->latest()->first();
                    if(isset($organization_submission))
                    {
                        $organization_submission->status = "Uploaded";
                        $organization_submission->save();

                        if (!empty($existingCoaches)) {
                            $coaches = Coach::whereIn('id', $existingCoaches)->get();

                            if ($coaches->isNotEmpty()) {
                                Helper::sendNewOnBoarding($coaches, $organization);
                            }
                        }
                    }
                    $type = "Admin";
                    $import = new EmployeesImport($organization->id,$type,$organization->name,$role,$userId);
                    Excel::import($import, $file);

                    $errors = $import->getErrors();
                    $imported_array = $import->getDepartments();
                    if(!empty($imported_array))
                    {
                        $departments = array_merge($imported_array,$departments);
                    }
                    if (!empty($errors)) {
                        $file = $import->getFile();
                        $importMessage = $import->getMessage();
                        $is_mailed = $import->checkMailedCount();
                        $afterCount = $organization->employees()->count();
                        $organization['is_user_added'] = ($afterCount > $beforeCount) ? 1 : 0;
                        $organization->load('coaches');
                        $employees = User::where('organization_id',$organization->id)->latest();
                        $organization['employees'] = Pagination::paginate($request, $employees, 'employees');
                        $response = [
                            "status" => 422,
                            "message" => "Organization Updated Successfully but Some issues were found during the import. ".$importMessage,
                            // "errors" => $errors,
                            "file" => $file,
                            "is_mailed" => $is_mailed > 0 ? false : true,
                            "organization" => $organization,
                            "departments" => $departments

                        ];
                        return response($response, $response["status"]);
                    }
                    else
                    {
                        $afterCount = $organization->employees()->count();
                        $organization['is_user_added'] = ($afterCount > $beforeCount) ? 1 : 0;
                        $organization->load('coaches');
                        $employees = User::where('organization_id',$organization->id)->latest();
                        $organization['employees'] = Pagination::paginate($request, $employees, 'employees');
                        $response = [
                            "status" => 200,
                            "message" =>  'Employees Imported Successfully.',
                            "organization" => $organization,
                        ];

                        return response($response, $response["status"]);
                    }
                }
            }

            if(!$request->token){
                Helper::createActionLog($userId,$role,'organizations','update',$before_data,$organization);
            }
            $afterCount = $organization->employees()->count();
            $organization['is_user_added'] = ($afterCount > $beforeCount) ? 1 : 0;
            $organization->load('coaches','submission');

            $employees = User::where('organization_id',$id)->latest();

            $organization['employees'] = Pagination::paginate($request, $employees, 'employees');


            if($onlyIsActive)
            {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "organization" => $organization
                ];
                return response($response, $response["status"]);
            }
            else
            {
                $response = [
                    "status" => 200,
                    "message" =>  'Organization Updated Successfully. '.$importMessage,
                    "organization" => $organization,
                    "departments" => $departments ?? []
                ];
            }
        } else {
            $response = [
                "status" => 422,
                "message" =>  'Organization Not Found',
            ];
        }

        return response($response, $response["status"]);
    }
    public function getOrganizations(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $organizations = Organization::with('coaches')->withCount('employees')->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->where('name', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->when($role == "Coach",function($query) use ($role,$userId){
                    $query->whereHas('coaches',function($subquery) use ($userId){
                        $subquery->where('coach_id',$userId);
                    });
            })
            ->when($request->filled('department'),function($query) use ($request){
                $query->whereJsonContains('departments',$request->query('department'));
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
                    $query->whereDoesntHave('coaches');
                }
                else if($request->query('assignment_status') == 'Assigned')
                {
                    $query->whereHas('coaches',function($subquery){
                        $subquery->whereNotNull('coach_id');
                    });
                }
            })
            ->when($request->filled('employee_assignment'), function ($query) use ($request) {
                if($request->query('employee_assignment') == 'Unavailable')
                {
                    $query->whereDoesntHave('employees');
                }
                else if($request->query('employee_assignment') == 'Available')
                {
                    $query->whereHas('employees');
                }
            })
            ->latest();

        $response = Pagination::paginate($request, $organizations, 'organizations');

        return response($response, $response["status"]);
    }
    public function getOrganization(Request $request, $id)
    {
        $organization = Organization::with('coaches','submission','assign_plans.upload_by:id,first_name,last_name,profile_image')->withCount('employees')->find($id);

        $employees = User::where('organization_id',$id)->latest();

        $organization['employees'] = Pagination::paginate($request, $employees, 'employees');

        if (isset($organization)) {
            $response = [
                "status" => 200,
                "message" => "Organization Fetched Successfully",
                "organization" => $organization
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Organization Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function getOrganizationByToken(Request $request, $token)
    {
        $organization = Organization::with('coaches','employees','submission')->where("token",$token)->first();

        if (isset($organization)) {
            $response = [
                "status" => 200,
                "message" => "Organization Fetched Successfully",
                "organization" => $organization
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Token Expired"
            ];
        }
        return response($response, $response["status"]);
    }
    public function getOrganizationSubmissions(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $organization_submissions = OrganizationSubmission::with('organization:id,name,logo')
        ->when($request->filled('status'),function($query) use ($request){
            $query->where('status',$request->query('status'));
        })
        ->whereHas('organization')->latest();

        $response = Pagination::paginate($request, $organization_submissions, 'organization_submissions');

        $response["organization_submissions"] = $response["organization_submissions"]->map(function ($submission) {
            $submissionArray = $submission->toArray();
            $submissionArray['fileUrl'] = $submission->file;
            $submissionArray['file'] = $submission->file ? basename($submission->getRawOriginal('file')) : null;
            return $submissionArray;
        });

        return response($response, $response["status"]);
    }
    public function getOrganizationSubmission(Request $request, $id)
    {
        $organization_submission = OrganizationSubmission::with('organization')->find($id);

        if (isset($organization_submission)) {
            $response = [
                "status" => 200,
                "message" => "Organization submission Fetched Successfully",
                "organization_submission" => $organization_submission
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Organization submission Not Found"
            ];
        }
        return response($response, $response["status"]);
    }
    public function deleteOrganization(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $organization = Organization::find($id);

        if (isset($organization)) {
            Helper::createActionLog($userId,$role,'organizations','delete',$organization,null);
            $organization->delete();
            $response = [
                "status" => 200,
                "message" => "Organization Delete Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Organization Not Found"
            ];
        }
        return response($response, $response["status"]);
    }

    public function getOrganizationDropDown(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $limit = $request->query('limit',10);
        $organizations = Organization::select('id','name','departments','coach_id')->withCount('coaches')->with('coaches:coaches.id,first_name,last_name')
        ->when($role == "Coach",function($query) use ($userId){
            $query->whereHas('coaches',function($subquery) use ($userId){
                $subquery->where('coach_id',$userId);
            });
        })
        ->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('name', 'LIKE', '%' . $request->search . '%');
            });
        })
        ->where('is_active',1)->limit($limit)->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Organization Fetched Successfully",
            "organizations" => $organizations
        ];

        return response($response, $response["status"]);
    }

    public function addBulkDepartments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'departments' => 'required|array',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $departments = [];


        foreach($request->departments as $department)
        {
            $departments[] = [
                "name" => $department,
                "created_at"=>Carbon::now(),
                "updated_at"=>Carbon::now()
            ];

        }
        Department::insert($departments);

        $addedDepartments = Department::whereIn('name', $request->departments)->select('id','name')->get();


        $response = [
            "status" => 200,
            "message" => "Departments Added Successfully",
            "departments" => $addedDepartments
        ];

        return response($response, $response["status"]);
    }

    public function bulkDeleteEmployees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'deleted_ids' => 'required|array',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        User::whereIn('id', $request->deleted_ids)
        ->where('organization_id', $request->organization_id)
        ->update(['organization_id' => null]);

        $response = [
            "status" => 200,
            "message" => "Employees Deleted Successfully",
        ];

        return response($response, $response["status"]);
    }

    /**
     * Get assigned workout plans for an organization
     */
    public function getOrganizationWorkoutPlans(Request $request, $id)
    {
        $organization = Organization::with(['assign_plans' => function($query) {
            $query->with('upload_by:id,first_name,last_name,profile_image');
        }])->find($id);

        if (!isset($organization)) {
            return response([
                "status" => 404,
                "message" => "Organization Not Found"
            ], 404);
        }

        $response = [
            "status" => 200,
            "message" => "Workout Plans Fetched Successfully",
            "success" => true,
            "data" => $organization->assign_plans
        ];

        return response($response, 200);
    }

    /**
     * Assign workout plans to an organization (all members will receive it)
     */
    public function assignWorkoutPlans(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'plan_ids' => 'required|array',
            'plan_ids.*' => 'required|exists:plans,id',
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        $organization = Organization::with('employees')->find($id);

        if (!isset($organization)) {
            return response([
                "status" => 404,
                "message" => "Organization Not Found"
            ], 404);
        }

        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $assignedData = [];

        foreach ($request->plan_ids as $planId) {
            // Check if already assigned to organization
            $existingAssignment = DB::table('assign_plans')
                ->where('plan_id', $planId)
                ->where('organization_id', $id)
                ->whereNull('user_id')
                ->first();

            if (!$existingAssignment) {
                $assignedData[] = [
                    'plan_id' => $planId,
                    'organization_id' => $id,
                    'user_id' => null,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now()->addMonths(3),
                    'uploaded_by' => $userId,
                    'uploader' => $role,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
            }
        }

        if (!empty($assignedData)) {
            DB::table('assign_plans')->insert($assignedData);
        }

        $response = [
            "status" => 200,
            "message" => "Workout Plans Assigned Successfully to Organization",
            "success" => true
        ];

        return response($response, 200);
    }

    /**
     * Unassign a workout plan from an organization
     */
    public function unassignWorkoutPlan(Request $request, $orgId, $planId)
    {
        $assignment = DB::table('assign_plans')
            ->where('plan_id', $planId)
            ->where('organization_id', $orgId)
            ->first();

        if (!$assignment) {
            return response([
                "status" => 404,
                "message" => "Assignment Not Found"
            ], 404);
        }

        DB::table('assign_plans')
            ->where('plan_id', $planId)
            ->where('organization_id', $orgId)
            ->delete();

        return response([
            "status" => 200,
            "message" => "Workout Plan Unassigned Successfully",
            "success" => true
        ], 200);
    }

    /**
     * Get organization clients/employees
     */
    public function getOrganizationClients(Request $request, $id)
    {
        $organization = Organization::find($id);

        if (!isset($organization)) {
            return response([
                "status" => 404,
                "message" => "Organization Not Found"
            ], 404);
        }

        $clients = User::where('organization_id', $id)
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'profile_image', 'is_active')
            ->latest()
            ->get();

        return response([
            "status" => 200,
            "message" => "Clients Fetched Successfully",
            "success" => true,
            "data" => $clients
        ], 200);
    }

    /**
     * Get organization coaches
     */
    public function getOrganizationCoaches(Request $request, $id)
    {
        $organization = Organization::with('coaches')->find($id);

        if (!isset($organization)) {
            return response([
                "status" => 404,
                "message" => "Organization Not Found"
            ], 404);
        }

        return response([
            "status" => 200,
            "message" => "Coaches Fetched Successfully",
            "success" => true,
            "data" => $organization->coaches
        ], 200);
    }

    /**
     * Get analytics dashboard for organization
     * GET /api/admin/organizations/{id}/analytics
     */
    public function getAnalyticsDashboard($id)
    {
        try {
            $organization = Organization::findOrFail($id);

            // Get member statistics
            $totalMembers = DB::table('organization_members')
                ->where('organization_id', $id)
                ->count();

            $activeMembers = DB::table('organization_members')
                ->where('organization_id', $id)
                ->where('last_active_at', '>=', now()->subDays(30))
                ->count();

            // Get workout statistics
            $totalWorkouts = DB::table('workouts')
                ->whereIn('user_id', function($query) use ($id) {
                    $query->select('user_id')
                        ->from('organization_members')
                        ->where('organization_id', $id);
                })
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            // Get nutrition compliance
            $nutritionCompliance = DB::table('daily_nutrition')
                ->whereIn('user_id', function($query) use ($id) {
                    $query->select('user_id')
                        ->from('organization_members')
                        ->where('organization_id', $id);
                })
                ->where('date', '>=', now()->subDays(30))
                ->avg('compliance_percentage');

            // Get challenge participation
            $challengeParticipation = DB::table('challenge_participants')
                ->whereIn('user_id', function($query) use ($id) {
                    $query->select('user_id')
                        ->from('organization_members')
                        ->where('organization_id', $id);
                })
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'organization' => [
                        'id' => $organization->id,
                        'name' => $organization->name
                    ],
                    'members' => [
                        'total' => $totalMembers,
                        'active_30_days' => $activeMembers,
                        'activity_rate' => $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100, 1) : 0
                    ],
                    'workouts' => [
                        'total_30_days' => $totalWorkouts,
                        'avg_per_member' => $totalMembers > 0 ? round($totalWorkouts / $totalMembers, 1) : 0
                    ],
                    'nutrition' => [
                        'avg_compliance' => round($nutritionCompliance ?? 0, 1)
                    ],
                    'challenges' => [
                        'total_participants_30_days' => $challengeParticipation
                    ],
                    'period' => 'Last 30 days'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk invite users to organization
     * POST /api/admin/organizations/{id}/bulk-invite
     */
    public function bulkInvite($id, Request $request)
    {
        $validated = $request->validate([
            'emails' => 'required|array|min:1|max:100',
            'emails.*' => 'email',
            'role' => 'nullable|in:member,coach,admin'
        ]);

        try {
            $organization = Organization::findOrFail($id);
            $invitedEmails = [];
            $failedEmails = [];

            foreach ($validated['emails'] as $email) {
                try {
                    // Check if user exists
                    $user = DB::table('users')->where('email', $email)->first();

                    if (!$user) {
                        $failedEmails[] = ['email' => $email, 'reason' => 'User not found'];
                        continue;
                    }

                    // Check if already a member
                    $isMember = DB::table('organization_members')
                        ->where('organization_id', $id)
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($isMember) {
                        $failedEmails[] = ['email' => $email, 'reason' => 'Already a member'];
                        continue;
                    }

                    // Check if already invited
                    $alreadyInvited = DB::table('organization_invites')
                        ->where('organization_id', $id)
                        ->where('email', $email)
                        ->where('status', 'pending')
                        ->exists();

                    if ($alreadyInvited) {
                        $failedEmails[] = ['email' => $email, 'reason' => 'Already invited'];
                        continue;
                    }

                    // Create invitation
                    DB::table('organization_invites')->insert([
                        'organization_id' => $id,
                        'email' => $email,
                        'user_id' => $user->id,
                        'role' => $validated['role'] ?? 'member',
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    $invitedEmails[] = $email;
                } catch (\Exception $e) {
                    $failedEmails[] = ['email' => $email, 'reason' => $e->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'invited' => $invitedEmails,
                    'invited_count' => count($invitedEmails),
                    'failed' => $failedEmails,
                    'failed_count' => count($failedEmails)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance reports for organization
     * GET /api/admin/organizations/{id}/compliance-reports
     */
    public function getComplianceReports($id)
    {
        $period = request()->query('period', '30days'); // 7days, 30days, 90days

        $days = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30
        };

        try {
            $organization = Organization::findOrFail($id);

            // Get member IDs
            $memberIds = DB::table('organization_members')
                ->where('organization_id', $id)
                ->pluck('user_id')
                ->toArray();

            if (empty($memberIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'organization' => $organization->name,
                        'period' => $period,
                        'message' => 'No members in organization'
                    ]
                ]);
            }

            // Workout compliance
            $workoutCompliance = DB::table('user_workout_goals as uwg')
                ->select(DB::raw('
                    COUNT(DISTINCT CASE WHEN w.id IS NOT NULL THEN uwg.user_id END) as compliant_members,
                    COUNT(DISTINCT uwg.user_id) as total_members
                '))
                ->leftJoin('workouts as w', function($join) use ($days) {
                    $join->on('w.user_id', '=', 'uwg.user_id')
                        ->where('w.created_at', '>=', now()->subDays($days));
                })
                ->whereIn('uwg.user_id', $memberIds)
                ->first();

            // Nutrition compliance
            $nutritionCompliance = DB::table('daily_nutrition')
                ->whereIn('user_id', $memberIds)
                ->where('date', '>=', now()->subDays($days))
                ->selectRaw('AVG(compliance_percentage) as avg_compliance')
                ->first();

            // Challenge participation
            $challengeParticipation = DB::table('challenge_participants')
                ->whereIn('user_id', $memberIds)
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('COUNT(DISTINCT user_id) as participating_members')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'organization' => $organization->name,
                    'period' => $period . ' (' . $days . ' days)',
                    'compliance' => [
                        'workout' => [
                            'compliant_members' => $workoutCompliance->compliant_members ?? 0,
                            'total_members' => count($memberIds),
                            'compliance_rate' => count($memberIds) > 0
                                ? round((($workoutCompliance->compliant_members ?? 0) / count($memberIds)) * 100, 1)
                                : 0
                        ],
                        'nutrition' => [
                            'avg_compliance_percentage' => round($nutritionCompliance->avg_compliance ?? 0, 1)
                        ],
                        'challenges' => [
                            'participating_members' => $challengeParticipation->participating_members ?? 0,
                            'participation_rate' => count($memberIds) > 0
                                ? round((($challengeParticipation->participating_members ?? 0) / count($memberIds)) * 100, 1)
                                : 0
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate compliance report: ' . $e->getMessage()
            ], 500);
        }
    }
}
