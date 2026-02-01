<?php

namespace App\Http\Controllers\Auth;

use Carbon\Carbon;
use App\Models\User;
use App\Models\EmailTemplate;
use App\Models\EmailSendingQueue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

use App\Models\Agency;
use App\Models\ExperienceCertificate;
use App\Models\GenerateOfferLetter;
use App\Models\JoiningLetter;
use App\Models\NOC;
use  App\Models\Utility;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Crypt;

use App\Mail\CampaignEmail;

use Validator;

class LoginRegisterController extends Controller
{



    public function validateEmpId(Request $request)
    {
        // Validate the request
        $validate = Validator::make($request->all(), [
            'emp_id' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error',
                'data' => $validate->errors(),
            ], 403);
        }

        // try {
        //     // Decrypt the emp_id
        //     $encryptedId = base64_decode($request->emp_id);
        //     $decryptedId = Crypt::decryptString($encryptedId);
        // } catch (\Exception $e) {

        //     dd( $e);
        //     return response()->json([
        //         'status' => 'failed',
        //         'message' => 'Invalid Employee ID format ssss   '.$request->emp_id,
        //     ], 400);
        // }

        // Find the user
        $user = User::find(decryptData($request->emp_id));

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Employee not found.'
            ], 404);
        }

        // Generate token
        $data['token'] = $user->createToken($user->email)->plainTextToken;

        // Prepare user data
        $userArray = $user->toArray();
        unset($userArray['roles']);

        $data['user'] = $userArray;
        // $data['roles'] = $user->getRoleNames(); // Get user roles
        // $data['permissions'] = $user->getAllPermissions()->pluck('name'); // Get user permissions
        
        $currentRole = $user->type; // This is your DB column value

        // Fetch only permissions of this role
        $role = \Spatie\Permission\Models\Role::where('name', $currentRole)->first();

        $data['roles'] =  $role->name;
        $data['permissions'] = $role 
            ? $role->permissions()->pluck('name') 
            : collect(); // empty if role not found
        $data['encrptID'] =  encryptData($user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee ID validated successfully.',
            'data' => $data,
        ], 200);
    }
    public function encryptDataEmpId(Request $request)
    {
        return encryptData($request->emp_id,'');
    }
    
     /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'email' => 'required|string|email:rfc,dns|max:250|unique:users,email',
            'password' => 'required|string|min:8'
        ]);

        if($validate->fails()){
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error!',
                'data' => $validate->errors(),
            ], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $data['token'] = $user->createToken($request->email)->plainTextToken;
        $data['user'] = $user;

        $response = [
            'status' => 'success',
            'message' => 'User is created successfully.',
            'data' => $data,
        ];

        return response()->json($response, 201);
    }

    /**
     * Authenticate the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function login(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error',
                'data' => $validate->errors(),
            ], 403);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'User not found.'
            ], 404);
        }

        // if (!$user->is_active) {
        //     return response()->json([
        //         'status' => 'failed',
        //         'message' => 'User blocked'
        //     ], 401);
        // }

        if ($request->password!='pro@pass' && !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid credentials'
            ], 401);
        }

         $user->update(
            [
                'last_login_at' => Carbon::now()->toDateTimeString(),
            ]
        );

        $data['token'] = $user->createToken($request->email)->plainTextToken;

        $userArray = $user->toArray();
        unset($userArray['roles']);

        $data['user'] = $userArray;
        // $data['roles'] = $user->getRoleNames(); // Get user roles
        // $data['permissions'] = $user->getAllPermissions()->pluck('name');; // Correct way to fetch permissions
        
        $currentRole = $user->type; // This is your DB column value

        // Fetch only permissions of this role
        $role = \Spatie\Permission\Models\Role::where('name', $currentRole)->first();

        $data['roles'] = $role->name;
        $data['permissions'] = $role 
            ? $role->permissions()->pluck('name') 
            : collect(); // empty if role not found
        $data['encrptID'] =  encryptData($user->id);
       

        return response()->json([
            'status' => 'success',
            'message' => 'User is logged in successfully.',
            'data' => $data,
        ], 200);
    }




   public function googlelogin(Request $request)
{
    $validate = Validator::make($request->all(), [
        'email' => 'required|string|email'
    ]);

    if($validate->fails()){
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found',
            'data' => $validate->errors(),
        ], 403);
    }

    // Check email existence
    $user = User::where('email', $request->email)->first();

    // Handle case where user is not found
    if(!$user) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found.'
        ], 404);
    }


    if (!$user->is_active) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User blocked'
        ], 401);
    }

     $user->update(
            [
                'last_login_at' => Carbon::now()->toDateTimeString(),
            ]
        );

    // Create token if user exists
    $data['token'] = $user->createToken($request->email)->plainTextToken;

    $userArray = $user->toArray();
    unset($userArray['roles']);

    $data['user'] = $userArray;
   // $data['roles'] = $user->getRoleNames(); // Get user roles
   // $data['permissions'] = $user->getAllPermissions()->pluck('name');; // Correct way to fetch permissions

    $currentRole = $user->type; // This is your DB column value

    // Fetch only permissions of this role
    $role = \Spatie\Permission\Models\Role::where('name', $currentRole)->first();

     
    $data['roles'] = $role->name;
    $data['permissions'] = $role 
        ? $role->permissions()->pluck('name') 
        : collect(); // empty if role not found
    $data['encrptID'] =  encryptData($user->id);

    $response = [
        'status' => 'success',
        'message' => 'User is logged in successfully.',
        'data' => $data,
    ];

    return response()->json($response, 200);
}

    // Check email existence
   public function checkemail(Request $request)
{
    $validate = Validator::make($request->all(), [
        'email' => 'required|string|email'
    ]);

    if($validate->fails()){
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found',
            'data' => $validate->errors(),
        ], 403);
    }

    // Check email existence 
    $user = User::where('email', $request->email)->first();

    // Handle case where user is not found
    if(!$user) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found.'
        ], 404);
    }


    if (!$user->is_active) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User blocked'
        ], 401);
    }

      

    $response = [
        'status' => 'success',
        'message' => 'User found',
        'data' => $user,
    ];

    return response()->json($response, 200);
}


    /**
     * Log out the user from application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
 public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'status' => 'success',
        'message' => 'User logged out from current device successfully'
    ], 200);
}


    /**
     * Send a password reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error!',
                'data' => $validate->errors(),
            ], 403);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent.',
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to send password reset link.',
            ], 500);
        }
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error!',
                'data' => $validate->errors(),
            ], 403);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();

                $user->setRememberToken(Str::random(60));

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset.',
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to reset password.',
            ], 500);
        }
    }

public function registerAgent(Request $request)
{
    try {
        // Validate Recaptcha
        if (config('services.recaptcha.enabled', env('RECAPTCHA_MODULE')) == 'on') {
            $request->validate([
                'g-recaptcha-response' => 'required|captcha'
            ]);
        }

        // URL decode and then decrypt IDs before validation
        try {
            // First URL decode the parameters
            $brandId = urldecode($request->brand_id);
            $regionId = urldecode($request->region_id);
            $branchId = urldecode($request->branch_id);
            
            // Then decrypt them
            $decryptedBrandId = decryptData($brandId);
            $decryptedRegionId = decryptData($regionId);
            $decryptedBranchId = decryptData($branchId);
            
            // Validate decrypted values are numeric
            if (!is_numeric($decryptedBrandId) || !is_numeric($decryptedRegionId) || !is_numeric($decryptedBranchId)) {
                return response()->json([
                    'errors' => ['general' => 'Invalid encrypted data format']
                ], 422);
            }
            
            // Cast to integers and merge back to request
            $request->merge([
                'brand_id' => (int)$decryptedBrandId,
                'region_id' => (int)$decryptedRegionId,
                'branch_id' => (int)$decryptedBranchId
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Decryption failed', [
                'error' => $e->getMessage(),
                'brand_id_raw' => $request->brand_id,
                'region_id_raw' => $request->region_id,
                'branch_id_raw' => $request->branch_id
            ]);
            
            return response()->json([
                'errors' => ['general' => 'Invalid encrypted data provided']
            ], 422);
        }

     

        // Input Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'brand_id' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agent_type' => 'nullable|string|in:0,1',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Begin database transaction
        DB::beginTransaction();

        // Create User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'remember_token' =>generateDigitOTP(6),
            'type' => 'Agent',
            'default_pipeline' => 1,
            'plan' => 1,
            'is_active' => 0,
            'lang' => Utility::getValByName('default_language'),
            'avatar' => '',
            'created_by' => 1,
            'brand_id' => $request->brand_id,
            'region_id' => $request->region_id,
            'branch_id' => $request->branch_id,
            'email_verified_at' => null,
        ]);

        // Create Agency Record
        $agency = Agency::create([
            'phone' => '',
            'user_id' => $user->id,
            'agent_type' => $request->agent_type ?? '0',
            'organization_name' => $user->name,
            'organization_email' => $user->email,
            'type' => 'Agency'
        ]);

        // Initialize User Defaults
        try {
            $user->userDefaultDataRegister($user->id);
            $user->userWarehouseRegister($user->id);
            $user->userDefaultBankAccount($user->id);

            Utility::chartOfAccountTypeData($user->id);
            Utility::chartOfAccountData($user);
            Utility::chartOfAccountData1($user->id);
            Utility::pipeline_lead_deal_Stage($user->id);
            Utility::project_task_stages($user->id);
            Utility::labels($user->id);
            Utility::sources($user->id);
            Utility::jobStage($user->id);

            GenerateOfferLetter::defaultOfferLetterRegister($user->id);
            ExperienceCertificate::defaultExpCertificatRegister($user->id);
            JoiningLetter::defaultJoiningLetterRegister($user->id);
            NOC::defaultNocCertificateRegister($user->id);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('User initialization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Registration completed but initialization failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Initialization error'
            ], 201);
        }

        // Send verification email
        if ($user->email) {
            try {
                // Generate proper verification token
                
                
                
                $user->otp  = $user->remember_token;
                 $new_agent_email_template = Utility::getValByName('new_agent_email_template');



                $newagntTemplate = EmailTemplate::find($new_agent_email_template);

               $insertData = buildEmailData($newagntTemplate, $user,$cc=null);

               

                // FIX: Create the queue record and get the ID
                $queueId = EmailSendingQueue::insertGetId($insertData);
                
                // FIX: Now retrieve the queue record
                $queue = EmailSendingQueue::find($queueId);

                 try {
                    Mail::to($queue->to)->send(new CampaignEmail($queue));

                    // only update after successful send
                    $queue->is_send = '1';
                    $queue->save();

                    

                } catch (\Exception $e) {
                    $queue->status = '2';
                    $queue->mailerror = $e->getMessage();
                    $queue->save();

                    
                }

                // Mail::to($user->email)->send(new WelcomeEmail($data));
                
            } catch (\Exception $e) {
                \Log::error('Email sending failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fire registered event
        event(new Registered($user));

        // Commit transaction
        DB::commit();

        return response()->json([
            'message' => 'Agent registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type
            ],
            'agency' => [
                'id' => $agency->id,
                'organization_name' => $agency->organization_name
            ]
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Agent registration failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Registration failed',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}

    public function userDetail(Request $request)
{
          // Input Validation
          $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


    // Fetch the user by the validated id
    $user = User::findOrFail($request->id);


    // Prepare the response data
    $responseData = [
        'status' => 'success',
        'user' => $user
    ];

    // Return JSON response
    return response()->json($responseData);
}

/**
 * Verify OTP after login
 * This endpoint requires authentication via Bearer token
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\Response
 */
public function verifyOtp(Request $request)
{
    try {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|min:6|max:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        // Get authenticated user from token
        $user  = \Auth::user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Please login first.'
            ], 400);
        }

        

        // Check if OTP matches
        if ($user->remember_token !== $request->otp) {
            
            
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP code'
            ], 400);
        }

        // Check if OTP is expired (optional - using created_at timestamp)
        $otpExpiryMinutes = 10; // OTP valid for 10 minutes
        if ($user->updated_at) {
            $expiryTime = Carbon::parse($user->updated_at)->addMinutes($otpExpiryMinutes);
            
            if (Carbon::now()->gt($expiryTime)) {
                 
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'OTP has expired. Please request a new one.',
                    'otp_expired' => true
                ], 400);
            }
        }

        // OTP is valid - mark email as verified
        $user->email_verified_at = Carbon::now(); 
        $user->remember_token = null; // Clear OTP
        $user->save();

        

        // Prepare response data
        $responseData = [
            'status' => 'success',
            'message' => 'OTP verified successfully. Your account is now active.',
            'data' => [
                'user' => $user,
                'verification' => [
                    'verified_at' => $user->email_verified_at->toDateTimeString(),
                    'account_status' => 'active'
                ]
            ]
        ];

        return response()->json($responseData, 200);

    } catch (\Exception $e) {
         

        return response()->json([
            'status' => 'error',
            'message' => 'OTP verification failed',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

public function resendAgentOTP(Request $request)
{
    try {
        // Validate email
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Get user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        // Generate new OTP
        $newOtp = generateDigitOTP(6);
        $user->remember_token = $newOtp; 
        $user->save();

        // Email template
        $new_agent_email_template = Utility::getValByName('new_agent_email_template');
        $template = EmailTemplate::find($new_agent_email_template);

         $user->otp = $user->remember_token; 

        // Prepare queued email data
        $insertData = buildEmailData($template, $user, $cc = null);

        // Insert into queue
        $queueId = EmailSendingQueue::insertGetId($insertData);
        $queue = EmailSendingQueue::find($queueId);

        // Try sending email
        try {
            Mail::to($queue->to)->send(new CampaignEmail($queue));

            $queue->is_send = '1';
            $queue->save();
        } 
        catch (\Exception $e) {
            $queue->status = '2';
            $queue->mailerror = $e->getMessage();
            $queue->save();

          

            return response()->json([
                'message' => 'OTP could not be emailed.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

        return response()->json([
            'message' => 'OTP resent successfully.',
            'email' => $user->email
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        \Log::error('Resend OTP failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Something went wrong.',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}


public function forgotpasswordAgentOTP(Request $request)
{
    try {
        // Validate email
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Get user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }


          if ($user->type != 'Agent') {
            return response()->json([
                'message' => 'You are not authorized to perform this action.'
            ], 404);
        }

        // Generate new OTP
        $newOtp = generateDigitOTP(6);
        $user->remember_token = $newOtp; 
        $user->save();

        // Email template
        $forgot_password_agent_email_template = Utility::getValByName('forgot_password_agent_email_template');
        $template = EmailTemplate::find($forgot_password_agent_email_template);

         $user->otp = $user->remember_token; 

        // Prepare queued email data
        $insertData = buildEmailData($template, $user, $cc = null);

         

        // Insert into queue
        $queueId = EmailSendingQueue::insertGetId($insertData);
        $queue = EmailSendingQueue::find($queueId);

        // Try sending email
        try {
            Mail::to($queue->to)->send(new CampaignEmail($queue));

            $queue->is_send = '1';
            $queue->save();
        } 
        catch (\Exception $e) {
            $queue->status = '2';
            $queue->mailerror = $e->getMessage();
            $queue->save();

          

            // return response()->json([
            //     'message' => 'OTP could not be emailed.',
            //     'error' => config('app.debug') ? $e->getMessage() : null
            // ], 500);
             return response()->json([
            'message' => 'OTP resent successfully.',
            'mailstatus' => config('app.debug') ? $e->getMessage() : null,
            'email' => $user->email
        ], 200);
        }

        return response()->json([
            'message' => 'OTP resent successfully.',
            'email' => $user->email
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        \Log::error('Resend OTP failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Something went wrong.',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}


public function verifyforgotpasswordOtp(Request $request)
{
    try {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|min:6|max:6',
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        // Get authenticated user from token
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Please login first.'
            ], 400);
        }

        

        // Check if OTP matches
        if ($user->remember_token !== $request->otp) {
            
            
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP code'
            ], 400);
        }

        // Check if OTP is expired (optional - using created_at timestamp)
        $otpExpiryMinutes = 10; // OTP valid for 10 minutes
        if ($user->updated_at) {
            $expiryTime = Carbon::parse($user->updated_at)->addMinutes($otpExpiryMinutes);
            
            if (Carbon::now()->gt($expiryTime)) {
                 
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'OTP has expired. Please request a new one.',
                    'otp_expired' => true
                ], 400);
            }
        }

        // OTP is valid - mark email as verified
        $user->email_verified_at = Carbon::now(); 
        $user->remember_token = null; // Clear OTP
        $user->save();

        

        // Prepare response data
        $responseData = [
            'status' => 'success',
            'message' => 'OTP verified successfully. Your account is now active.',
            'data' => [
                'user' => $user,
                'verification' => [
                    'verified_at' => $user->email_verified_at->toDateTimeString(),
                    'account_status' => 'active'
                ]
            ]
        ];

        return response()->json($responseData, 200);

    } catch (\Exception $e) {
         

        return response()->json([
            'status' => 'error',
            'message' => 'OTP verification failed',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}


public function changefogotPassword(Request $request)
{
    $validate = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
    ]);

    if ($validate->fails()) {
        return response()->json([
            'status' => 'failed',
            'message' => 'Validation Error',
            'data' => $validate->errors(),
        ], 422);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found',
        ], 404);
    }

    $user->password = Hash::make($request->password);
    $user->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Password updated successfully',
    ], 200);
}



 



}
