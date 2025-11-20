<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Mail\SendOtp;
use App\Mail\SendForgotOtp;
use App\Mail\SendUserSignupToAdmin;
use App\Mail\SendWelcomeToUser;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\BodyPoint;
use App\Models\DietaryRestriction;
use App\Models\EquipmentPreference;
use App\Models\Faq;
use App\Models\NutritionCalculation;
use App\Models\RegisterOtp;
use App\Models\ReportContent;
use App\Models\SiteInfo;
use App\Models\TrainingPreference;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email:filter',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        } else {
            $user = User::where('email', $request->email)->first();
            if (isset($user)) {
                if (!Hash::check($request->password, $user->password)) {
                    $response = [
                        'status' => 422,
                        "message" => "Invalid Password",
                    ];
                } else {
                    if ($user->is_active == 1) {
                        $token = $user->createToken("authToken")->accessToken;
                        $user['role'] = "User";
                        // $heightInCm = $user->height;
                        // $feet = floor($heightInCm / 30.48);
                        // $inches = round(($heightInCm - ($feet * 30.48)) / 2.54);

                        // $user['height'] = $feet . "." . $inches;
                        $response = [
                            'status' => 200,
                            'message' => 'Login successful',
                            'user' => $user,
                            'token' => $token,
                            'user_type' => 'client',
                        ];
                    } else {
                        $response = [
                            'status' => 422,
                            'message' => "Account Blocked!",
                        ];
                    }
                }
            } else {
                $response = [
                    'status' => 422,
                    'message' => "Invalid Email",
                ];
            }
        }
        return response($response, $response["status"]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'max:255',
                'email:filter',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'password' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required'
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);
        if ($validator->fails()) {
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create($request->toArray());
        // $user->first_login = 0;
        $user->password = $request->password;
        // SECURITY: Password reset link should be sent instead of plaintext password
        $user->otp = null;
        $user->is_mailed = 1;
        if ($request->hasFile('profile_image')) {
            $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/user_profiles/", $filename, $request->profile_image);
            $user->profile_image = $file;

            $thumbnail   = time() . rand(111, 699) .'_thumbnail' . '.' . $request->profile_image->getClientOriginalExtension();
            $thumbnail_file = Helper::generateThumbnail("upload/user_profiles/thumbnails/", $thumbnail, $request->profile_image,100,100,40);
            $user->profile_image_thumbnail = $thumbnail_file;
        }
        $user->save();
        $token = $user->createToken('app authToken')->accessToken;
        $response = [
            "status" => 200,
            "message" => "User Registered Successfully!",
            "user" => $user,
            "token" => $token,
        ];
        return response($response, $response["status"]);
    }
    public function checkAvailability(Request $request)
    {
        \Log::info('Check Email Availability', [
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'ip' => $request->ip()
        ]);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'max:255',
                'email:filter',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);

        if ($validator->fails()) {
            \Log::warning('Email availability check failed', [
                'email' => $request->email,
                'errors' => $validator->errors()->toArray()
            ]);

            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors()
            ];
            return response($response, $response["status"]);
        }

        \Log::info('Email available for registration', ['email' => $request->email]);

        $response = ["status" => 200, 'available' => true];
        return response($response, $response["status"]);
    }
    public function verifyOTPRegister(Request $request)
    {
        \Log::info('OTP Verification Request', [
            'email' => $request->email,
            'otp_provided' => substr($request->otp, 0, 2) . '****',  // Log partial OTP for security
            'ip' => $request->ip()
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email:filter|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
            'otp' => 'required',
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);
        if ($validator->fails()) {
            \Log::warning('OTP verification validation failed', [
                'email' => $request->email,
                'errors' => $validator->errors()->toArray()
            ]);

            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $temp = RegisterOtp::where("email", $request['email'])->where("otp", $request['otp'])->first();

        if ($temp) {
            \Log::info('OTP verified successfully', [
                'email' => $request->email,
                'otp_id' => $temp->id
            ]);
            $response = ['status' => 200, 'message' => "Valid Otp"];
        } else {
            \Log::warning('Invalid OTP provided', [
                'email' => $request->email,
                'otp_exists' => RegisterOtp::where("email", $request['email'])->exists()
            ]);
            $response = ['status' => 422, "message" => 'Invalid Otp!'];
        }
        return response($response, $response["status"]);
    }
    public function sendOtpForRegister(Request $request)
    {
        \Log::info('Registration OTP Request', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'max:255',
                'email',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'first_name' => 'required',
            'last_name' => 'required',
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);

        if ($validator->fails()) {
            \Log::warning('Registration OTP Validation Failed', [
                'email' => $request->email,
                'errors' => $validator->errors()->toArray()
            ]);
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = RegisterOtp::where('email', $request->email)->first();

        $otp = rand(100000, 999999);

        if ($user == null) {
            $user = RegisterOtp::create();
            $user->email = $request->email;
            $user->otp = $otp;
            $user->save();
            \Log::info('New OTP record created', ['email' => $request->email]);
        }

        if ($user) {
            if ($request->resend == 1) {
                $otp = $otp;
            }
            $user->otp = $otp;
            $user->save();

            \Log::info('OTP generated', [
                'email' => $user->email,
                'otp_length' => strlen($otp),
                'is_dev' => env('APP_ENV') === 'local'
            ]);

            $name = $request->first_name .' '.$request->last_name;

            // Development mode: Skip email sending and return OTP in response
            if (env('APP_ENV') === 'local' || env('APP_DEBUG') === true) {
                \Log::info('DEV MODE: Skipping email, returning OTP in response', [
                    'email' => $user->email,
                    'otp' => $otp
                ]);

                $response = [
                    "status" => 200,
                    "message" => "OTP Sent! (DEV MODE: Email skipped)",
                    "otp" => $otp,  // ONLY in development!
                    "code" => $otp,  // Frontend compatibility
                    "dev_mode" => true
                ];

                return response($response, $response["status"]);
            }

            // Production mode: Send email
            try{
                Mail::to($user->email)->send(new SendOtp($user,$name));
                \Log::info('OTP email sent successfully', ['email' => $user->email]);
            }
            catch(Exception $e)
            {
                \Log::error('OTP email sending failed', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $response = [
                    "status" => 422,
                    "message" => "You can't received the OTP through this email id. Kindly contact with support@bodyf1rst.com to register account",
                ];
                return response($response, $response["status"]);
            }

            $response = [
                "status" => 200,
                "message" => "OTP Sent!",
            ];

            return response($response, $response["status"]);
        } else {
            \Log::error('OTP user creation failed', ['email' => $request->email]);
            $response = ["status" => 422, "message" => 'User does not exist'];
            return response($response, $response["status"]);
        }
    }
    public function sendForgotOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);
        if ($validator->fails()) {
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $otp = rand(100000, 999999);
        $user = User::where('email',$request->email)->first();
        if(isset($user))
        {
            $name = $user->first_name .' '.$user->last_name;
            $user->otp = $otp;
            $user->save();
            try{
                Mail::to($user->email)->send(new sendForgotOtp($user,$name));
            }
            catch(Exception $e)
            {
                $response = [
                    "status" => 422,
                    "message" => "You can't received the OTP through this email id. Kindly contact with admin (Support Email ID) to register account",
                ];
                return response($response, $response["status"]);
            }
            $response = [
                "status" => 200,
                "message" => "Otp Send Successfully"
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "User Not Found"
            ];
        }

        return response($response, $response["status"]);
    }
    public function verifyForgotOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required'
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);
        if ($validator->fails()) {
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email',$request->email)->where('otp',$request->otp)->first();
        if(isset($user))
        {
            $response = [
                "status" => 200,
                "message" => "Valid OTP!"
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "Invalid OTP!"
            ];
        }

        return response($response, $response["status"]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required',
            'password' => 'required|min:6|confirmed',
        ],[
            'email.regex' => 'Please register this email ID on Google or another platform before using it for registration.'
        ]);
        if ($validator->fails()) {
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email',$request->email)->first();
        if(isset($user))
        {
            if($user->otp == $request->otp)
            {
                $user->password = $request->password;
                $user->otp = null;
                $user->save();
                $response = [
                    "status" => 200,
                    "message" => "Password Change Successfully"
                ];
            }
            else
            {
                $response = [
                    "status" => 422,
                    "message" =>  "Invalid Otp",
                ];
            }
        }
        else
        {
            $response = [
                "status" => 422,
                "message" =>  "User Not Found",
            ];
        }
        return response($response, $response["status"]);
    }
    public function changePassword(Request $request)
    {
        $rules = array(
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        );
        $validation = Validator::make($request->all(), $rules);

        if ($validation->fails()) {
            $response = [
                "status" => 422,
                "message" =>  $validation->errors()->first(),
            ];
            return response($response, $response["status"]);
        }


        $user = User::find($request->user()->id);

        if ($user) {

            if (!Hash::check($request->old_password, $user->password)) {
                $response = [
                    "status" => 422,
                    "message" =>  'Invalid Password!',
                ];
                return response($response, $response["status"]);
            }

            $user->password = $request->new_password;
            $user->save();

            $response = [
                "status" => 200,
                "message" =>  'Password Change Successfully',
            ];
        } else {
            $response = [
                "status" => 422,
                "message" =>  'User Not Found',
            ];
        }
        return response($response, $response["status"]);
    }

    public function getMyProfile(Request $request)
    {
        $currentUser = $request->user();
        $user = User::find($currentUser->id);
        if (isset($user)) {
            // $heightInCm = $user->height;
            // $feet = floor($heightInCm / 30.48);
            // $inches = round(($heightInCm - ($feet * 30.48)) / 2.54);

            // $user['height'] = $feet . "." . $inches;
            $response = [
                "status" => 200,
                "message" => "Profile Fetched Successfully",
                "user" => $user,
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "User Not Found",
            ];
        }
        return response($response, $response["status"]);
    }
    public function updateProfile(Request $request)
    {
        $currentUser = $request->user();

        \Log::info('Profile Update Request', [
            'user_id' => $currentUser->id,
            'email' => $currentUser->email,
            'first_login' => $currentUser->first_login,
            'ip' => $request->ip()
        ]);

        if($currentUser->first_login == 1)
        {
            $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
                'gender' => 'required|in:Male,Female',
                'dob' => 'required|date',
                'age' => 'required|numeric',
                'weight' => 'required|numeric',
                'height' => 'required',
                'activity_level' => 'required|in:Not Active,Slightly Active,Moderate Active,Very Active',
                'goal' => 'required',
                'daily_meal' => 'required|numeric',
                'accountability' => 'required|in:High,Medium,Low,None',
                // 'equipment_preferences' => 'array',
                // 'training_preferences' => 'array',
                // 'dietary_restrictions' => 'array',
            ];
        }
        else
        {
            $rules = [
            'gender' => 'in:Male,Female',
            'dob' => 'date',
            'age' => 'numeric',
            'weight' => 'numeric',
            'activity_level' => 'in:Not Active,Slightly Active,Moderate Active,Very Active',
            'daily_meal' => 'numeric',
            'accountability' => 'in:High,Medium,Low,None',
            // 'equipment_preferences' => 'array',
            // 'training_preferences' => 'array',
            // 'dietary_restrictions' => 'array',
        ];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            \Log::warning('Profile update validation failed', [
                'user_id' => $currentUser->id,
                'email' => $currentUser->email,
                'errors' => $validator->errors()->toArray()
            ]);

            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($currentUser->id);
        $admin = Admin::first();

        if (isset($user)) {
            $before = basename($user->profile_image);
            $beforeThumbnail = basename($user->profile_image_thumbnail);
            $user->fill($request->toArray());

            if ($request->hasFile('profile_image')) {
                \Log::info('Profile image uploaded', ['user_id' => $user->id]);
                $filename   = time() . rand(111, 699) . '.' . $request->profile_image->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/user_profiles/", $filename, $request->profile_image, $before);
                $user->profile_image = $file;

                $thumbnail   = time() . rand(111, 699) .'_thumbnail' . '.' . $request->profile_image->getClientOriginalExtension();
                $thumbnail_file = Helper::generateThumbnail("upload/user_profiles/thumbnails/", $thumbnail, $request->profile_image,100,100,40,$beforeThumbnail);
                $user->profile_image_thumbnail = $thumbnail_file;
            }

            if($user->first_login == 1)
            {
                \Log::info('First login - completing registration', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                $body_points = BodyPoint::first();
                $points = $body_points->meta_value['signup_compeletion']['profile'] ?? 0;
                $user->body_points = $points;

                Transaction::create([
                    "user_id" => $user->id,
                    "type" => "Earned",
                    "transaction_type" => Transaction::Body_Points,
                    "transaction_date" => Carbon::now()->toDateString(),
                    "name" => "Sign Up Completed",
                    "description" => "Congratulations on completing your signup process! You have successfully earned $points Body Points.",
                    "points" => $points,
                ]);

                $app_notification = AppNotification::create([
                    "user_id" => $user->id,
                    "title" => "Sign Up Completed",
                    "message" => "You have successfully earned $points Body Points.",
                ]);
                $app_notification->module = "app";
                $app_notification->save();

                $name = $admin->first_name .' '. $admin->last_name;

                try {
                    Mail::to($admin->email)->send(new SendUserSignupToAdmin($name,$user));
                    Mail::to($user->email)->send(new SendWelcomeToUser($user));
                    \Log::info('Welcome emails sent', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'admin_email' => $admin->email
                    ]);
                } catch (Exception $e) {
                    \Log::error('Failed to send welcome emails', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail registration if email fails
                }

                // SECURITY: Password reset link should be sent instead of plaintext password

                \Log::info('Registration completed successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'body_points' => $points
                ]);
            }

            $user->first_login = 0;
            $user->save();

            \Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            $response = [
                "status" => 200,
                "message" => "Profile Updated Successfully!",
                "user" => $user
            ];
        } else {
            \Log::error('User not found for profile update', [
                'requested_user_id' => $currentUser->id
            ]);

            $response = [
                "status" => 422,
                "message" => "User Not Found",
            ];
        }
        return response($response, $response["status"]);
    }
    public function logout(Request $request)
    {
        $token = $request->user()->token();
        $token->revoke();
        $response = ['status' => 200, 'message' => 'You have been successfully logged out!'];
        return response($response, 200);
    }

    public function getDietaryDropDown(Request $request)
    {
        $dietary_restrictions = DietaryRestriction::when($request->filled('limit'), function ($query) use ($request) {
            $query->limit($request->query('limit'));
        })->where('is_active',1)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Dietary Restriction Fetched Successfully",
            "dietary_restrictions" => $dietary_restrictions
        ];

        return response($response, $response["status"]);
    }
    public function getTrainingDropDown(Request $request)
    {
        $training_preferences = TrainingPreference::when($request->filled('limit'), function ($query) use ($request) {
            $query->limit($request->query('limit'));
        })->where('is_active',1)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Training Preference Fetched Successfully",
            "training_preferences" => $training_preferences
        ];

        return response($response, $response["status"]);
    }
    public function getEquipmentDropDown(Request $request)
    {
        $equipment_preferences = EquipmentPreference::when($request->filled('limit'), function ($query) use ($request) {
            $query->limit($request->query('limit'));
        })->where('is_active',1)->select('id','name')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Equipment Preference Fetched Successfully",
            "equipment_preferences" => $equipment_preferences
        ];

        return response($response, $response["status"]);
    }

    public function getNutritionCalculations(Request $request)
    {
        $nutrition_calculations = NutritionCalculation::where('is_current', 1)->select('meta_key', 'meta_value')->get();

        $nutrition_data = $nutrition_calculations->mapWithKeys(function ($calculation) {
            return [$calculation['meta_key'] => $calculation['meta_value']];
        });

        $response = [
            "status" => 200,
            "message" => "Nutrition Calculation Fetched",
            "nutrition_calculations" => $nutrition_data,
        ];

        return response($response, $response['status']);
    }

    public function getSiteInfo(Request $request)
     {
         $site_info = SiteInfo::first();
         $response = [
             "status" => 200,
             "message" => "Site Info Fetch Successfully",
             "site_info" => $site_info
         ];

         return response($response, $response["status"]);
     }

     public function getFaqs(Request $request)
    {
        $faqs = Faq::latest()->get();

        $response = [
            "status" => "200",
            "message" => "Faq Fetched Successfully",
            "faqs" => $faqs
        ];

        return response($response, $response["status"]);
    }

    public function getBodyPointsHistory(Request $request)
    {
        $user = $request->user();

        $body_points_history = Transaction::where('user_id',$user->id)
                    ->where('type','Earned')
                    ->where('transaction_type',Transaction::Body_Points)
                    ->orderBy('transaction_date','desc');

        $response = Pagination::paginate($request,$body_points_history,'body_points_history');

        $response['body_points_history'] = $response['body_points_history']->groupBy('transaction_date');

        return response($response, $response["status"]);
    }


    public function deleteMyAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deleted_reason' => 'required',
        ]);

        if ($validator->fails()) {
            $response = [
                'status' => 422,
                'message' => $validator->errors()->first(),
                "errors" => $validator->errors()
            ];
            return response($response, $response["status"]);
        }

        $user = User::find($request->user()->id);
        if(isset($user))
        {
            $user->deleted_reason = $request->deleted_reason;
            $user->save();
            $user->delete();
            $response = [
                "status" => 200,
                "message" => "User Account Deleted Successfully",
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "User Not Found",
            ];
        }

        return response($response, $response["status"]);
    }


    public function reportContent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'model_id' => 'required',
            'model_type' => 'required|in:Workout,Exercise,Video,NutritionVideo',
            'reason' => "required"
        ]);

        if ($validator->fails()) {
            $response = [
                'status' => 422,
                'message' => $validator->errors()->first(),
                "errors" => $validator->errors()
            ];
            return response($response, $response["status"]);
        }

        $user = $request->user();

        $report = ReportContent::create($request->toArray());
        $report->user_id = $user->id;
        $report->save();
        $response = [
            "status" => 200,
            "message" => "Content Reported Successfully",
        ];

        return response($response, $response["status"]);
    }
}
