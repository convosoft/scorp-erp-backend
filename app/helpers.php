<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Region;
use App\Models\University;
use App\Models\ActivityLog;
use App\Models\LogActivity;
use App\Models\Notification;
use App\Models\StageHistory;
use App\Events\NewNotification;
use App\Models\CompanyPermission;
use App\Models\HistoryRequest;
use App\Models\LeadTag;
use App\Models\EmailTemplate;
use App\Models\Utility;
use App\Models\EmailSendingQueue;
use App\Models\EmailTag;

if (!function_exists('countries')) {
    function countries()
    {
        $all_countries = [];
        $contries = \App\Models\Country::get();


        foreach ($contries as $country) {
            $all_countries[$country->name] = $country->name;
        }

        return $all_countries;
    }
}

  function checkUserAssociationsBeforeDelete($userId)
    {
        if (empty($userId)) {
            return "Error: User ID is required.";
        }

        $tableLabels = [
            'deal_tasks' => 'Tasks',
            'leads' => 'Leads',
            'deals' => 'Admissions and Applications',
        ];

        $tables = [
            'deal_tasks' => ['created_by', 'assigned_to'],
            'leads' => ['user_id', 'created_by'],
            'deals' => ['created_by', 'assigned_to'],
        ];

        $user = \Auth::user(); // Fetch authenticated user
        $filtersBrands = array_keys(FiltersBrands()); // Get filters for brands

        // Fetch associated tables with record counts
        $associatedTables = collect($tables)->mapWithKeys(function ($columns, $table) use ($userId, $user, $filtersBrands) {
            $count = \DB::table($table)
                ->where(function ($query) use ($columns, $userId, $user, $filtersBrands, $table) {
                    // Check for user association in the specified columns
                    foreach ($columns as $column) {
                        $query->orWhere($column, $userId);
                    }

                    // Apply filtering logic based on user roles
                    if ($user->type !== 'HR') {
                        if ($user->type === 'super admin' || $user->can('level 1')) {
                            $filtersBrands[] = '3751';
                            $query->whereIn("$table.brand_id", $filtersBrands);
                        } elseif ($user->type === 'company') {
                            $query->where("$table.brand_id", $user->id);
                        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
                            $query->whereIn("$table.brand_id", $filtersBrands);
                        } elseif ($user->type === 'Region Manager' || ($user->can('level 3') && !empty($user->region_id))) {
                            $query->where("$table.region_id", $user->region_id);
                        } elseif (
                            in_array($user->type, [
                                'Branch Manager',
                                'Admissions Officer',
                                'Careers Consultant',
                                'Admissions Manager',
                                'Marketing Officer',
                            ]) || ($user->can('level 4') && !empty($user->branch_id))
                        ) {
                            $query->where("$table.branch_id", $user->branch_id);
                        } elseif ($user->type === 'Agent') {
                            $query->where("$table.assigned_to", $user->id)
                                ->orWhere("$table.created_by", $user->id);
                        } else {
                            $query->where("$table.branch_id", $user->branch_id);
                        }
                    }
                })->count();

            return $count > 0 ? [$table => $count] : [];
        });

        if ($associatedTables->isNotEmpty()) {
            // Map to user-friendly labels with counts
            $associatedMessages = $associatedTables->map(function ($count, $table) use ($tableLabels) {
                $label = $tableLabels[$table] ?? $table; // Default to table name if no label exists
                return "$label ($count record(s))";
            });

            return "User is associated with the following: " . $associatedMessages->join(', ') . ". Please remove these associations before deleting the user.";
        }

        return null;
    }

function encryptData($data, $key = "mysecretkey1234567890123456") {
    $cipher = "AES-256-CBC";
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return urlencode(base64_encode($iv . $encrypted));
}

function decryptData($data, $key = "mysecretkey1234567890123456") {
    $cipher = "AES-256-CBC";
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
}

function getStatus($status)
{
    $segment = request()->segment(1); // Get the first URL segment

    switch ($status) {
        case 0:
            return '<span class="badge bg-danger">' . ($segment === 'profile-update-agent' ? '' : 'Agent') . ' Registered</span>';
        case 1:
            return '<span class="badge bg-warning text-dark">Document Submitted</span>';
        case 2:
            return '<span class="badge bg-success">' . ($segment === 'profile-update-agent' ? '' : 'Agent') . ' Approved</span>';
        default:
            return '<span class="badge bg-success">' . ($segment === 'profile-update-agent' ? '' : 'Agent') . ' Approved</span>';
    }
}

if (!function_exists('months')) {
    function months()
    {
        $months = [
            'JAN' => 'January',
            'FEB' => 'February',
            'MAR' => 'March',
            'APR' => 'April',
            'MAY' => 'May',
            'JUN' => 'June',
            'JUL' => 'July',
            'AUG' => 'August',
            'SEP' => 'September',
            'OCT' => 'October',
            'NOV' => 'November',
            'DEC' => 'December',
        ];
        return $months;
    }
}


if (!function_exists('companies')) {
    function companies()
    {
        return User::where('type', 'company')->pluck('name', 'id')->toArray();
    }
}


if (!function_exists('allUsers')) {
    function allUsers()
    {
        return User::pluck('name', 'id')->toArray();
    }
}

if (!function_exists('allRegions')) {
    function allRegions()
    {
        return Region::pluck('name', 'id')->toArray();
    }
}

if (!function_exists('companiesEmployees')) {
    function companiesEmployees($company_id)
    {
        return User::where('created_by', $company_id)->pluck('name', 'id')->toArray();
    }
}


if (!function_exists('getAllEmployees')) {
    function getAllEmployees()
    {
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $usersQuery = User::select('users.*');
        $userType = \Auth::user()->type;
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // Permissions for level 1
        } elseif ($userType === 'company') {
            $usersQuery->where('brand_id', \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $usersQuery->whereIn('brand_id', $brand_ids);
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $usersQuery->where('region_id', \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
            $usersQuery->where('branch_id', \Auth::user()->branch_id);
        } else {
            $usersQuery->where('id', \Auth::user()->id);
        }
        // Apply exclusion of user types
        return $usersQuery->whereNotIn('type', $excludedTypes)->pluck('name','id');
    }
}

if (!function_exists('allUniversities')) {
    function allUniversities()
    {
        return University::pluck('name', 'id')->toArray();
    }
}


if (!function_exists('allPermittedCompanies')) {
    function allPermittedCompanies()
    {
        return CompanyPermission::where('user_id', \Auth::user()->id)->where('active', 'true')->pluck('permitted_company_id')->toArray();
    }
}
if (!function_exists('addToEmailQueue')) {
    function addToEmailQueue(
        $user_id,
        $settingtemplate,
        $templateId = null,
        $ccchecklist = [
            'is_branch_manager'   => 'yes',
            'is_region_manager'   => 'yes',
            'is_project_manager'  => 'yes',
            'is_scrop_attendance' => 'yes'
        ],
        $ccadditional = [],
        $additionalTags = []
    ) {
        $user = User::with(['branch.manager', 'region.manager'])
            ->findOrFail($user_id);

        // Resolve manager details
        $branch_manager_detail  = optional($user->branch)->manager;
        $region_manager_detail  = optional($user->region)->manager;
        $project_manager_detail = User::where('id', $user->brand->project_manager_id)->first();

        // Build CC list
        $ccList = [];

        if (($ccchecklist['is_branch_manager'] ?? 'no') === 'yes' && !empty($branch_manager_detail?->email)) {
            $ccList[] = $branch_manager_detail->email;
        }

        if (($ccchecklist['is_region_manager'] ?? 'no') === 'yes' && !empty($region_manager_detail?->email)) {
            $ccList[] = $region_manager_detail->email;
        }

        if (($ccchecklist['is_project_manager'] ?? 'no') === 'yes' && !empty($project_manager_detail?->email)) {
            $ccList[] = $project_manager_detail->email;
        }

        if (($ccchecklist['is_scrop_attendance'] ?? 'no') === 'yes') {
            $ccList[] = 'scorp-erp_attendance@convosoft.com';
        }

        // Merge additional CCs
        $ccList = array_unique(array_merge($ccList, $ccadditional));

        // Inject managers for email template usage
        $user->branch_manager  = $branch_manager_detail;
        $user->region_manager  = $region_manager_detail;
        $user->project_manager = $project_manager_detail;

        // Add employee status
        $statusMap = [
            0 => 'Inactive',
            1 => 'Active',
            2 => 'Terminated',
            3 => 'Suspended',
        ];
        $user->employee_status = $statusMap[$user->is_active] ?? 'Unknown';

        // Add custom tags
        foreach ($additionalTags as $key => $value) {
            $user->$key = $value;
        }

        // Get template
        $resolvedTemplateId = $templateId ?? Utility::getValByName($settingtemplate);
        $emailTemplate = EmailTemplate::find($resolvedTemplateId);

        if ($emailTemplate) {
            $insertData = [
                buildEmailData($emailTemplate, $user, implode(',', $ccList))
            ];

            EmailSendingQueue::insert($insertData);
        }
    }
}
/**
 * Generate a secure numeric OTP (One-Time Password)
 *
 * @param int $len Length of OTP (default: 6)
 * @return string Numeric OTP
 */
function generateDigitOTP($len = 6)
{
    // Validate length parameter
    if (!is_int($len) || $len < 4 || $len > 10) {
        $len = 6; // Default to 6 if invalid
    }

    // Method 1: Using random_int (Most secure - PHP 7+)
    $otp = '';
    for ($i = 0; $i < $len; $i++) {
        $otp .= random_int(0, 9);
    }

    //$otp = 123456;

    return $otp;
}

 function buildEmailData($template, $user,$cc=null)
    {
        if (!$template) {
            return [];
        }

        $subject = replaceTags($template->subject, $user);
        $content = replaceTags($template->template, $user);

        return [
            'to'           => $user->email,
            'cc'           => $cc,
            'subject'      => $subject,
            'brand_id'     => $user->brand_id,
            'from_email'   => $template->from ?? 'hr@scorp.co',
            'branch_id'    => $user->branch_id,
            'region_id'    => $user->region_id,
            'is_send'      => '0',
            'sender_id'    => 0,
            'created_by'   => 0,
            'priority'     => 1,
            'content'      => $content,
            'stage_id'     => null,
            'pipeline_id'  => null,
            'template_id'  => $template->id,
            'related_type' => 'employee',
            'related_id'   => $user->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

     function replaceTags($content, $user)
    {
        $tags = EmailTag::all();
        $replacePairs = [];

        foreach ($tags as $tag) {
            $key = '{' . $tag->tag . '}';
               // If tag exists as dynamic property on $user (from $additionalTags)
                if (isset($user->{$tag->tag})) {
                    $value = $user->{$tag->tag};
                } else {
                    $value = ''; // default
                }

            switch ($tag->tag) {
                case 'employee_name':
                    $value = $user->name;
                    break;
                case 'employee_email':
                    $value = $user->email;
                    break;
                case 'DOB':
                    $value = $user->date_of_birth;
                    break;

                case 'branch_manager_name':
                    $value = optional(optional($user->branch)->manager)->name ?? '';
                    break;
                case 'branch_manager_email':
                    $value = optional(optional($user->branch)->manager)->email ?? '';
                    break;

                case 'date_today':
                    $value = now()->toDateString();
                    break;
                case 'employee_designation':
                    $value = optional($user->designation)->name ?? '';
                    break;
                case 'commencedDate':
                    $value = optional(
                        $user->employeeMetas->where('meta_key', 'commencedDate')->first()
                    )->meta_value ?? '';
                    break;
                        // 🔹 Document info
                    case 'document_name':
                        $value = $user->document_name ?? '';
                        break;
                    case 'document_expiry':
                        $value = $user->document_expiry ?? '';
                        break;

                    // 🔹 Managers
                    case 'project_manager_name':
                        $value = $user->project_manager?->name ?? '';
                        break;
                    case 'project_manager_email':
                        $value = $user->project_manager?->email ?? '';
                        break;
                    }

            $replacePairs[$key] = $value;
        }

        return strtr($content, $replacePairs);
    }


if (!function_exists('addLogActivity')) {
    function addLogActivity($data = [],$is_cron= 0)
    {
        $new_log = new LogActivity();
        $new_log->type = $data['type'];
        $new_log->start_date = date('Y-m-d');
        $new_log->time = date('H:i:s');
        $new_log->note = $data['note'];
        $new_log->module_type = isset($data['module_type']) ? $data['module_type'] : '';
        $new_log->module_id = isset($data['module_id']) ? $data['module_id'] : 0;
        if($is_cron==1){
            $new_log->created_by = 0;
        }else{
            $new_log->created_by = \Auth::user()?->id ?? 0;
            }

        $new_log->save();



        ///////////////////Creating Notification
        $msg = '';
        if(strtolower($data['notification_type']) == 'application stage update'){
            $msg = 'Application stage updated.';
        }else if(strtolower($data['notification_type']) == 'lead updated'){
            $msg = 'Lead updated.';
        }else if(strtolower($data['module_type']) == 'application'){
            $msg = 'New application created.';
        }else if(strtolower($data['notification_type']) == 'University Created'){
            $msg = 'New University Created.';
        }else if(strtolower($data['notification_type']) == 'University Updated'){
            $msg = 'University Updated.';
        }else if(strtolower($data['notification_type']) == 'University Deleted'){
            $msg = 'University Deleted.';
        }else if(strtolower($data['notification_type']) == 'Deal Created'){
            $msg = 'Deal Created.';
        }else if(strtolower($data['notification_type']) == 'Deal Updated'){
            $msg = 'Deal Updated.';
        }else if(strtolower($data['notification_type']) == 'Lead Updated'){
            $msg = 'Lead Updated.';
        }else if(strtolower($data['notification_type']) == 'Deal Notes Created'){
            $msg = 'Deal Notes Created.';
        }else if(strtolower($data['notification_type']) == 'Task Created'){
            $msg = 'Task Created.';
        }else if(strtolower($data['notification_type']) == 'Task Updated'){
            $msg = 'Task Updated.';
        }else if(strtolower($data['notification_type']) == 'Stage Updated'){
            $msg = 'Stage Updated.';
        }else if(strtolower($data['notification_type']) == 'Deal Stage Updated'){
            $msg = 'Deal Stage Updated.';
        }else if(strtolower($data['notification_type']) == 'Organization Created'){
            $msg = 'Organization Created.';
        }else if(strtolower($data['notification_type']) == 'Organization Updated'){
            $msg = 'Organization Updated.';
        }else if(strtolower($data['notification_type']) == 'Lead Notes Updated'){
            $msg = 'Lead Notes Updated.';
        }else if(strtolower($data['notification_type']) == 'Notes created'){
            $msg = 'Notes created.';
        }else if(strtolower($data['notification_type']) == 'Task Deleted'){
            $msg = 'Task Deleted.';
        }else if(strtolower($data['notification_type']) == 'Lead Created'){
            $msg = 'Lead Created.';
        }else if(strtolower($data['notification_type']) == 'Lead Updated'){
            $msg = 'Lead Updated.';
        }else if(strtolower($data['notification_type']) == 'Lead Deleted'){
            $msg = 'Lead Deleted.';
        }else if(strtolower($data['notification_type']) == 'Discussion created'){
            $msg = 'Discussion created.';
        }else if(strtolower($data['notification_type']) == 'Drive link added'){
            $msg = 'Drive link added.';
        }else if(strtolower($data['notification_type']) == 'Lead Notes Updated'){
            $msg = 'Lead Notes Updated.';
        }else if(strtolower($data['notification_type']) == 'Lead Notes Deleted'){
            $msg = 'Lead Notes Deleted.';
        }else if(strtolower($data['notification_type']) == 'Lead Converted'){
            $msg = 'Lead Converted.';
        }else if(strtolower($data['notification_type']) == 'Application Notes Created'){
            $msg = 'Application Notes Created.';
        }else if(strtolower($data['notification_type']) == 'Application Notes Updated'){
            $msg = 'Application Notes Updated.';
        }else if(strtolower($data['notification_type']) == 'Applicaiton Notes Deleted'){
            $msg = 'Applicaiton Notes Deleted.';
        }else{
            $msg = 'New record created';
        }



        // $notification = new Notification;
        // $notification->user_id = \Auth::user()->id;
        // $notification->type = 'push notificationn';
        // $notification->data = $msg;
        // $notification->is_read = 0;

        // $notification->save();
       // event(new NewNotification($notification));
    }
}

if (!function_exists('addLeadHistory')) {
    function addLeadHistory($data = [])
    {
        if (isset($data['stage_id'])) {
            StageHistory::where('type_id', $data['type_id'])
                ->where('type', $data['type'])
                ->where('stage_id', '>=', $data['stage_id'])
                ->delete();
        }


        $new_log = new StageHistory();
        $new_log->type = $data['type'];
        $new_log->type_id = $data['type_id'];
        $new_log->stage_id = $data['stage_id'];
        $new_log->created_by = \Auth::user()->id;
        $new_log->save();
    }
}

if (!function_exists('getLogActivity')) {
    function getLogActivity($id, $type)
    {
        return LogActivity::where('module_id', $id)->where('module_type', $type)->orderBy('created_at', 'desc')->get();
    }
}


if (!function_exists('getBlackListLog')) {
    function getBlackListLog($id)
    {
        return HistoryRequest::where('student_id', $id)->orderBy('created_at', 'desc')->get();
    }
}

if (!function_exists('formatPhoneNumber')) {
    function formatPhoneNumber($phoneNumber)
    {
        // Remove non-numeric characters from the phone number
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Check if the phone number starts with '92' (country code for Pakistan)
        if (strpos($phoneNumber, '92') === 0) {
            // Remove the leading '92' if present
            $phoneNumber = substr($phoneNumber, 2);
        }

        // Add the country code '92' to the phone number
        $formattedPhoneNumber = '92' . $phoneNumber;

        return $formattedPhoneNumber;
    }
}


if (!function_exists('FiltersBrands')) {
    function FiltersBrands()
    {
        $brands = User::where('type', 'company');
        if (\Auth::user()->type == 'company') {
            $user_brand = !empty(\Auth::user()->id) ? \Auth::user()->id : 0;
        } else {
            $user_brand = !empty(\Auth::user()->brand_id) ? \Auth::user()->brand_id : 0;
        }

        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->type == 'HR' || \Auth::user()->can('level 1')) {

        } else if (\Auth::user()->type == 'Project Director' || \Auth::user()->type == 'Project Manager' || \Auth::user()->can('level 2')) {
            $permittedCompanies = allPermittedCompanies();
            $brands->whereIn('id', $permittedCompanies);
            $brands->orWhere('id', $user_brand);
        } else if (\Auth::user()->type == 'Region Manager' || \Auth::user()->can('level 3')) {
            $regions = Region::where('region_manager_id', \Auth::user()->id)->pluck('id')->toArray();
            $branches = Branch::whereIn('region_id', $regions)->pluck('id')->toArray();
            $brands->whereIn('branch_id', $branches);
            $brands->orWhere('id', $user_brand);
        } else if (\Auth::user()->type == 'Branch Manager' || \Auth::user()->can('level 4')) {
            $branches = Branch::where('branch_manager_id', \Auth::user()->id)->pluck('id')->toArray();
            $brands->whereIn('branch_id', $branches);
            $brands->orWhere('id', $user_brand);
        } else if(\Auth::user()->can('level 5')){
            $brands->where('id', $user_brand);
        } else {
            $brands->where('id', $user_brand);
        }

        return $brands->pluck('name', 'id')->toArray();
    }
}

if (!function_exists('FiltersRegions')) {
    function FiltersRegions($id)
    {
        $regions = Region::whereRaw('FIND_IN_SET(?, brands)', [$id])->pluck('name', 'id')->toArray();
        $html = ' <select class="form form-control region_id select2" id="region_id" name="region_id"> <option value="">Select Region</option> ';
        foreach ($regions as $key => $region) {
            $html .= '<option value="' . $key . '">' . $region . '</option> ';
        }
        $html .= '</select>';

        return $html;
    }
}

if (!function_exists('FiltersBranches')) {
    function FiltersBranches($id)
    {
        $branches = Branch::where('region_id', $id)->pluck('name', 'id')->toArray();
        $html = ' <select class="form form-control branch_id select2" id="branch_id" name="lead_branch"> <option value="">Select Branch</option> ';
        foreach ($branches as $key => $branch) {
            $html .= '<option value="' . $key . '">' . $branch . '</option> ';
        }
        $html .= '</select>';

        return $html;
    }
}

if (!function_exists('FiltersBranchUsers')) {
    function FiltersBranchUsers($id)
    {
        $branch = Branch::find($id);

        if (!empty($branch)) {
            // Get region of the branch
            $regions = Region::select(['regions.id'])
                ->join('branches', 'branches.region_id', '=', 'regions.id')
                ->where('branches.id', $id)
                ->pluck('id')
                ->toArray();

            // Get brand IDs
            $brand_ids = Region::select(['regions.brands'])
                ->join('branches', 'branches.region_id', '=', 'regions.id')
                ->where('branches.id', $id)
                ->pluck('brands')
                ->toArray();

            // Super Admins
            $admins = [];
            if ($branch->name == 'Admin') {
                $admins = User::whereIn('type', ['super admin'])
                    ->pluck('name', 'id')
                    ->toArray();
            }

            // Product Coordinators
            $Product_Coordinator = [];
            if ($branch->name == 'Product') {
                $Product_Coordinator = User::whereIn('type', ['Product Coordinator'])
                    ->pluck('name', 'id')
                    ->toArray();
            }

            // Marketing Team
            $Marketing_team = [];
            if ($branch->name == 'Marketing') {
                $Marketing_team = User::whereIn('type', ['Marketing Officer'])
                    ->pluck('name', 'id')
                    ->toArray();
            }

            // Project Directors and Managers
            $project_directors = User::whereIn('type', ['Project Director', 'Project Manager'])
                ->whereIn('brand_id', $brand_ids)
                ->pluck('name', 'id')
                ->toArray();

            // Regional Managers
            $regional_managers = User::where('type', 'Region Manager')
                ->whereIn('region_id', $regions)
                ->pluck('name', 'id')
                ->toArray();

            // Other Users
            $users = User::whereNotIn('type', ['super admin', 'company', 'client', 'team'])
                ->where('branch_id', $id)
                ->pluck('name', 'id')
                ->toArray();

            // Current Logged-in User
            $login_user = [\Auth::id() => \Auth::user()->name];

            // Combine all users and ensure uniqueness
            $unique_users = [];
            $users_lists = [
                $login_user,
                $admins,
                $project_directors,
                $regional_managers,
                $users,
                $Product_Coordinator,
                $Marketing_team
            ];

            foreach ($users_lists as $users) {
                foreach ($users as $key => $user) {
                    if (!isset($unique_users[$key]) && $key > 0) {
                        $unique_users[$key] = $user;
                    }
                }
            }

            // Return as an array
            return $unique_users;
        }

        return  'Branch not found' ;
    }
}
if (!function_exists('FilterUserByBranchId')) {
    function FilterUserByBranchId($id)
    {
        $branch = Branch::find($id);
        if (!empty($branch)) {
            // Get region of the branch
            $regions = Region::select(['regions.id'])
                ->join('branches', 'branches.region_id', '=', 'regions.id')
                ->where('branches.id', $id)
                ->pluck('id')
                ->toArray();

            $brand_ids = Region::select(['regions.brands'])
                ->join('branches', 'branches.region_id', '=', 'regions.id')
                ->where('branches.id', $id)
                ->pluck('brands')
                ->toArray();

            // Super admins
            $admins = [];
            if ($branch->name == 'Admin') {
                $admins = User::whereIn('type', ['super admin'])
                    ->select('id', 'name')
                    ->get()
                    ->toArray();
            }

            // Product Coordinators
            $Product_Coordinator = [];
            if ($branch->name == 'Product') {
                $Product_Coordinator = User::whereIn('type', ['Product Coordinator'])
                    ->select('id', 'name')
                    ->get()
                    ->toArray();
            }

            // Marketing team
            $Marketing_team = [];
            if ($branch->name == 'Marketing') {
                $Marketing_team = User::whereIn('type', ['Marketing Officer'])
                    ->select('id', 'name')
                    ->get()
                    ->toArray();
            }

            // Project Directors
            $project_directors = User::whereIn('type', ['Project Director', 'Project Manager'])
                ->whereIn('brand_id', $brand_ids)
                ->select('id', 'name')
                ->get()
                ->toArray();

            // Regional Managers
            $regional_managers = User::where('type', 'Region Manager')
                ->whereIn('region_id', $regions)
                ->select('id', 'name')
                ->get()
                ->toArray();

            // General users
            $users = User::whereNotIn('type', ['super admin', 'company', 'client', 'team'])
                ->where('branch_id', $id)
                ->select('id', 'name')
                ->get()
                ->toArray();

            // Logged-in user
            $login_user = [
                'id' => \Auth::id(),
                'name' => \Auth::user()->name,
            ];

            // Combine all users and remove duplicates
            $users_lists = array_merge([$login_user], $admins, $project_directors, $regional_managers, $users, $Product_Coordinator, $Marketing_team);

            // Ensure unique users by 'id'
            $unique_users = collect($users_lists)->unique('id')->mapWithKeys(function ($user) {
                return [$user['id'] => $user['name']];
            })->toArray();

            // Add default option
            $unique_users = ['0' => 'Select Employee'] + $unique_users;

            return $unique_users;
        }

        return ['0' => 'Select Employee'];
    }
}

if (!function_exists('FiltersBranchUsersFORTASK')) {
    function FiltersBranchUsersFORTASK($id)
    {
        $branch = Branch::find($id);
        if (!empty($branch)) {
            // Fetch regions
            $regions = Region::select(['regions.id'])
                ->join('branches', 'branches.region_id', '=', 'regions.id')
                ->where('branches.id', $id)
                ->pluck('id')
                ->toArray();

            // Fetch brand IDs
            $brand_ids = Region::select(['regions.brands'])
                ->join('branches', 'branches.region_id', '=', 'regions.id')
                ->where('branches.id', $id)
                ->pluck('brands')
                ->toArray();

            // Super admins
            $admins = [];
            if ($branch->name == 'Admin') {
                $admins = User::where('type', 'super admin')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            }

            // Product Coordinators
            $Product_Coordinator = [];
            if ($branch->name == 'Product') {
                $Product_Coordinator = User::where('type', 'Product Coordinator')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            }

            // Marketing team
            $Marketing_team = [];
            if ($branch->name == 'Marketing') {
                $Marketing_team = User::where('type', 'Marketing Officer')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            }

            // Project directors
            $project_directors = User::whereIn('type', ['Project Director', 'Project Manager'])
                ->whereIn('brand_id', $brand_ids)
                ->orderBy('name', 'ASC')
                ->pluck('name', 'id')
                ->toArray();

            // Regional managers
            $regional_managers = User::where('type', 'Region Manager')
                ->whereIn('region_id', $regions)
                ->orderBy('name', 'ASC')
                ->pluck('name', 'id')
                ->toArray();

            // Branch-specific users
            $users = User::whereNotIn('type', ['super admin', 'company', 'client', 'team'])
                ->where('branch_id', $id)
                ->orderBy('name', 'ASC')
                ->pluck('name', 'id')
                ->toArray();

            // Currently logged-in user
            $login_user = [\Auth::id() => \Auth::user()->name];

            // Merge all user lists
            $unique_users = [];
            $users_lists = [$admins, $project_directors, $regional_managers, $users, $Product_Coordinator, $Marketing_team, $login_user];
            foreach ($users_lists as $user_group) {
                foreach ($user_group as $key => $user) {
                    if (!isset($unique_users[$key]) && $key > 0) {
                        $unique_users[$key] = $user;
                    }
                }
            }

            // Sort unique users alphabetically by name
            asort($unique_users);

            // Return JSON response with sorted users
            return response()->json([
                'status' => 'success',
                'employees' => $unique_users,
            ]);
        }

        // Return error if branch not found
        return response()->json([
            'status' => 'error',
            'message' => 'Branch not found',
        ]);
    }
}


if (!function_exists('FiltersBranchUsersFORTASK_FORM_EDIT')) {
    function FiltersBranchUsersFORTASK_FORM_EDIT($id)
    {
        $branch=Branch::find($id);
        if(!empty($branch)){

         //get region of the branch
         $regions = Region::select(['regions.id'])->join('branches', 'branches.region_id', '=', 'regions.id')->where('branches.id', $id)->pluck('id')->toArray();

         $brand_ids = Region::select(['regions.brands'])->join('branches', 'branches.region_id', '=', 'regions.id')->where('branches.id', $id)->pluck('brands')->toArray();

         //super admins
         $admins=[];
         if($branch->name == 'Admin'){
            $admins = User::whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();
        }
        $Product_Coordinator=[];
        if($branch->name == 'Product'){
           $Product_Coordinator = User::whereIn('type', ['Product Coordinator'])->pluck('name', 'id')->toArray();
       }

       $Marketing_team=[];
       if($branch->name == 'Marketing'){
          $Marketing_team = User::whereIn('type', ['Marketing Officer'])->pluck('name', 'id')->toArray();
       }

         //project directors
         $project_directors = User::whereIn('type', ['Project Director', 'Project Manager'])->where('brand_id', $brand_ids)->pluck('name', 'id')->toArray();

         $regional_managers = User::where('type', 'Region Manager')->whereIn('region_id', $regions)->pluck('name', 'id')->toArray();


        // $users = User::whereNotIn('type', ['super admin', 'company', 'accountant', 'client', 'team'])->where('branch_id', $id)->pluck('name', 'id')->toArray();
        $users = User::whereNotIn('type', ['super admin', 'company', 'client', 'team'])->where('branch_id', $id)->pluck('name', 'id')->toArray();

        $login_user=[\Auth::id() => \Auth::user()->name];
        $html = '';

        if(isset($_GET['page']) && $_GET['page'] == 'lead_list'){
            $html .= '<option value="null">Not Assign</option> ';
        }


        $unique_users = [];
        $users_lists = [$admins, $project_directors, $regional_managers, $users,$Product_Coordinator,$Marketing_team,$login_user];
        foreach ($users_lists as $users) {
            foreach ($users as $key => $user) {
                if (!isset($unique_users[$key]) && $key > 0) {
                    $unique_users[$key] = $user;
                }
            }
        }

         return $unique_users;
     }
    }
}


//returning stages ranges like visa fall in 1,2,3 and deposit fall in 4,5,6
if (!function_exists('stagesRange')) {
    function stagesRange($type)
    {
        if ($type == 'visas') {
            return [4, 5, 6];
        } else if ($type == 'deposit') {
            return [7, 8, 9];
        } else {
            return [1, 2, 3];
        }
    }
}

if (!function_exists('GetTrainers')) {
        function GetTrainers()
        {
            $Trainer_query = App\Models\Trainer::query();
            // Apply user type-based filtering
            $userType = \Auth::user()->type;
            if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
                // No additional filtering needed
            } elseif ($userType === 'company') {
                $Trainer_query->where('trainers.brand_id', \Auth::user()->id);
            } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
                $Trainer_query->whereIn('trainers.brand_id', array_keys(FiltersBrands()));
            } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
                $Trainer_query->where('trainers.region_id', \Auth::user()->region_id);
            } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
                $Trainer_query->where('trainers.branch_id', \Auth::user()->branch_id);
            } else {
                $Trainer_query->where('trainers.created_by', \Auth::user()->id);
            }

            return $Trainer_query->get()->pluck('firstname', 'id');
        }
}

if (!function_exists('GetDepartments')) {
    function GetDepartments()
    {
        $Trainer_query = App\Models\Department::query();

        // Apply user type-based filtering
        $userType = \Auth::user()->type;
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // No additional filtering needed
        } elseif ($userType === 'company') {
            $Trainer_query->where('trainings.brand_id', \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $Trainer_query->whereIn('trainings.brand_id', array_keys(FiltersBrands()));
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $Trainer_query->where('trainings.region_id', \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
            $Trainer_query->where('trainings.branch_id', \Auth::user()->branch_id);
        } else {
            $Trainer_query->where('trainings.created_by', \Auth::user()->id);
        }

        return $Trainer_query->get()->pluck('name', 'id');
    }
}

if (!function_exists('getAbsentCounts')) {
    function getAbsentCounts($userId)
    {
        $attendanceQuery = App\Models\AttendanceEmployee::where('employee_id', $userId);
        $last30dayCount = $attendanceQuery->whereBetween('date', [now()->subDays(30), now()])->count();
        $last12dayCount = $attendanceQuery->whereBetween('date', [now()->subDays(12), now()])->count();
        $absenceCount = $attendanceQuery->whereBetween('date', [now()->subDays(30), now()])->count();
        $attendance = $attendanceQuery->whereBetween('date', [now()->subDays(30), now()])->get();
        $absenceCount = $attendance->count();
        $absenceDays = 30 - $absenceCount;
        $bradfordFactor = pow($absenceCount, 30) * $absenceDays;

        return compact('last30dayCount', 'last12dayCount', 'bradfordFactor');
    }
}

if (!function_exists('CountJob')) {
    function CountJob($status)
    {
        $count_query = App\Models\Job::select('jobs.*');

        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);
        $userType = \Auth::user()->type;
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // No additional filtering needed
        } elseif ($userType === 'company') {
            $count_query->where('jobs.brand_id', \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $count_query->whereIn('jobs.brand_id', $brand_ids);
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $count_query->where('jobs.region_id', \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user())) {
            $count_query->where('jobs.branch', \Auth::user()->branch_id);
        } else {
            $count_query->where('jobs.created_by', \Auth::user()->id);
        }


        return $count_query->whereIn('status',$status)->count();
    }
}


if (!function_exists('RoleBaseTableGet')) {
    function RoleBaseTableGet($count_query,$brand,$region,$branch,$byMe)
    {
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);
        $userType = \Auth::user()->type;
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // No additional filtering needed
        } elseif ($userType === 'company') {
            $count_query->where($brand, \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $count_query->whereIn($brand, $brand_ids);
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $count_query->where($region, \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user())) {
            $count_query->where($branch, \Auth::user()->branch_id);
        } else {
            $count_query->where($byMe, \Auth::user()->id);
        }
        return $count_query;
    }
}



if (!function_exists('GetAllbrachesByPermission')) {
    function GetAllbrachesByPermission()
    {
        $branch_query = Branch::select(['branches.*']);
            if(\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->type == 'HR'){
            }else if(\Auth::user()->type == 'company'){
            $branch_query->where('brands', \Auth::user()->id);
            }else{
                $companies = FiltersBrands();
                $brand_ids = array_keys($companies);
                $branch_query->whereIn('brands', $brand_ids);
            }
            if(\Auth::user()->type == 'Region Manager'){
                $branch_query->where('region_id', \Auth::user()->region_id);
            }


            return $branch_query->get();


    }
}

if (! function_exists('limit_words')) {
    function limit_words($string, $word_limit) {
        $words = explode(' ', $string);
        return implode(' ', array_slice($words, 0, $word_limit));
    }
}



if (!function_exists('BrandsRegionsBranches')) {
    function BrandsRegionsBranches()
    {
        $brands = [];
        $regions = [];
        $branches = [];
        $employees = [];

        $user = \Auth::user();
        $type = $user->type;
        //$super_admins = [];
        //$project_dm = [];

        //$super_admins = User::whereIn('type', ['super admin'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        if(!empty(request()->segment(1)) && !empty(request()->segment(2)))
        {
            if(request()->segment(1).'/'.request()->segment(2) == 'tages/edit')
            {
                $leadTag=LeadTag::find(request()->segment(3));
                if(!empty($leadTag))
                {
                    $regions = Region::where('brands', $leadTag->brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    $branches = Branch::where('region_id', $leadTag->region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    $employees = User::where('branch_id', $leadTag->branch_id)->whereNotIn('type', ['client', 'company', 'super admin','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                }
            }
        }
        if(isset($_GET['brand']) && !empty($_GET['brand'])){
            $regions = Region::where('brands', $_GET['brand'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        }
        if(isset($_GET['brand_id']) && !empty($_GET['brand_id'])){
            $regions = Region::where('brands', $_GET['brand_id'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        }

        if(isset($_GET['region_id']) && !empty($_GET['region_id'])){
            $branches = Branch::where('region_id', $_GET['region_id'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            // $regions = Region::where('id', $_GET['region_id'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        }

        if(isset($_GET['branch_id']) && !empty($_GET['branch_id'])){
           // $branches = Branch::where('id', $_GET['branch_id'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
           $employees = User::where('branch_id', $_GET['branch_id'])->whereNotIn('type', ['client', 'company', 'super admin','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        }

        if ($type == 'super admin' || $type == 'Admin Team' || $type == 'HR' || \Auth::user()->can('level 1')) {
            $brands = User::where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
           $employees = User::whereNotIn('type', ['company', 'client', 'team','organization', 'super admin'])->where('branch_id', $user->branch_id)->pluck('name', 'id')->toArray();
        } else if ($type == 'company') {
            $brands = User::where('type', 'company')->where('id', $user->id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $regions = Region::where('brands', $user->id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        } else if ($type == 'Project Director' || $type == 'Project Manager' || \Auth::user()->can('level 2') || $type == 'Agent') {
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);
            $brands = User::where('type', 'company')->whereIn('id', $brand_ids)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        } else if ($type == 'Region Manager' || \Auth::user()->can('level 3')) {
            $brands = User::where('type', 'company')->where('id', $user->brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $regions = Region::where('id', $user->region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $branches = Branch::where('region_id', $user->region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        } else if ($type == 'Branch Manager' || $type == 'Admissions Officer' || $type == 'Admissions Manager' || $type == 'Marketing Officer' || \Auth::user()->can('level 4')) {
            $brands = User::where('type', 'company')->where('id', $user->brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $regions = Region::where('id', $user->region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $branches = Branch::where('id', $user->branch_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $employees = User::where('branch_id', $user->branch_id)->whereNotIn('type', ['client', 'company','organization', 'super admin'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        }


        return [
            'brands' =>   $brands,
            'regions' =>    $regions,
            'branches' =>   $branches,
            'employees' =>   $employees
        ];
    }




    if (!function_exists('BrandsRegionsBranchesForEdit')) {
        function BrandsRegionsBranchesForEdit($brand_id = 0, $region_id = 0, $branch_id = 0)
        {
            $brands = [];
            $regions = [];
            $branches = [];
            $employees = [];

            $user = \Auth::user();
            $type = $user->type;

            //dd($brand_id.' '.$region_id.' '.$branch_id);


            //get region of the branch
            // $regions = Region::select(['regions.id'])->join('branches', 'branches.region_id', '=', 'regions.id')->where('branches.id', $branch_id)->pluck('id')->toArray();

            // $brand_ids = Region::select(['regions.brands'])->join('branches', 'branches.region_id', '=', 'regions.id')->where('branches.id', $branch_id)->pluck('brands')->toArray();

            // //super admins
            // $admins = User::whereNull('users.branch_id')->whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();

            // //project directors
            // $project_directors = User::whereNull('users.branch_id')->whereIn('type', ['Project Director', 'Project Manager'])->where('brand_id', $brand_ids)->pluck('name', 'id')->toArray();

            // $regional_managers = User::whereNull('users.branch_id')->where('type', 'Region Manager')->whereIn('region_id', $regions)->pluck('name', 'id')->toArray();

            $branch=Branch::find($branch_id);

            if ($type == 'super admin' || $type == 'HR' || $type == 'Admin Team' || \Auth::user()->can('level 1')) {
                $brands = User::where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $regions = Region::where('brands', $brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $branches = Branch::where('region_id', $region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                if(!empty($branch)){
                    if($branch->name == 'Admin'){
                        $employees = User::whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();
                    }else{
                        $employees = User::whereIn('branch_id', [$user->branch_id,$branch_id])->whereNotIn('type', ['super admin', 'company', 'team', 'client','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    }
                }
            } else if ($type == 'company') {
                $brands = User::where('type', 'company')->where('id', $user->id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $regions = Region::where('brands', $brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $branches = Branch::where('region_id', $region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                if(!empty($branch)){
                    if($branch->name == 'Admin'){
                        $employees = User::whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();
                    }else{
                        $employees = User::whereIn('branch_id', [$user->branch_id,$branch_id])->whereNotIn('type', ['super admin', 'company', 'team', 'client','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    }
                }
            } else if ($type == 'Project Director' || $type == 'Project Manager' || \Auth::user()->can('level 2') || $type == 'Agent') {
                $companies = FiltersBrands();
                $brand_ids = array_keys($companies);
                $brands = User::where('type', 'company')->whereIn('id', $brand_ids)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $regions = Region::where('brands', $brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $branches = Branch::where('region_id', $region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                if(!empty($branch)){
                    if($branch->name == 'Admin'){
                        $employees = User::whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();
                    }else{
                        $employees = User::whereIn('branch_id', [$user->branch_id,$branch_id])->whereNotIn('type', ['super admin', 'company', 'team', 'client','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    }
                }
            } else if ($type == 'Region Manager' || \Auth::user()->can('level 3')) {
                $brands = User::where('type', 'company')->where('id', $brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $regions = Region::where('brands', $brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $branches = Branch::where('region_id', $region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                if(!empty($branch)){
                    if($branch->name == 'Admin'){
                        $employees = User::whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();
                    }else{
                        $employees = User::whereIn('branch_id', [$user->branch_id,$branch_id])->whereNotIn('type', ['super admin', 'company', 'team', 'client','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    }
                }
            } else if ($type == 'Branch Manager' || $type == 'Admissions Officer' || $type == 'Admissions Manager' || $type == 'Marketing Officer' || \Auth::user()->can('level 4')) {
                $brands = User::where('type', 'company')->where('id', $brand_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $regions = Region::where('id', $region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                $branches = Branch::where('region_id', $region_id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                if(!empty($branch)){
                    if($branch->name == 'Admin'){
                        $employees = User::whereIn('type', ['super admin'])->pluck('name', 'id')->toArray();
                    }else{
                        $employees = User::whereIn('branch_id', [$user->branch_id,$branch_id])->whereNotIn('type', ['super admin', 'company', 'team', 'client','organization'])->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
                    }
                }
            }

            return [
                'brands' =>   $brands,
                'regions' =>   $regions,
                'branches' =>   $branches,
                'employees' =>  $employees
            ];
        }
    }
}


function downloadCSV($headers, $data, $filename = 'data.csv')
{
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write headers to CSV
    fputcsv($output, $headers);

    // Write data to CSV
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    // Close output stream
    fclose($output);

    // Stop further execution
    exit;
}

function accessLevel()
{
    return [
        'first' => [
            'super admin',
            'Admin Team',
            'Project Director',
            'Project Manager'
        ],
        'second' => [
            'Region Manager'
        ],
        'third' => [
            'Branch Manager',
            'Admissions Manager',
            'Admissions Officer',
            'Marketing Officer'
        ]
    ];
}

/**
 * Calculates pagination details based on the current page and number of results per page.
 * If 'page' and 'num_results_on_page' parameters are provided in the GET request,
 * calculates the start index for fetching results accordingly.
 *
 * @return array An array containing pagination details:
 *               - 'start': The start index for fetching results.
 *               - 'num_results_on_page': The number of results to display on each page.
 *               - 'page': The current page number.
 */
function getPaginationDetail(){
    // Pagination calculation
    $start = 0; // Default start index
    $num_results_on_page = env("RESULTS_ON_PAGE"); // Default number of results per page

    if (isset($_GET['page'])) {
        $page = $_GET['page']; // Current page number

        // If 'num_results_on_page' parameter is provided, update $num_results_on_page
        $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;

        // Calculate the start index based on the current page and number of results per page
        $start = ($page - 1) * $num_results_on_page;
    } else {
        $page = 1;
        // If 'page' parameter is not provided, only update $num_results_on_page if 'num_results_on_page' parameter is provided
        $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
    }

    // Return an array containing pagination details
    return [
        'start' => $start, // Start index for fetching results
        'num_results_on_page' => $num_results_on_page, // Number of results to display on each page
        'page' => $page // Current page number
    ];
}


/**
 * Retrieves lists of users, regions, and branches for use in dropdowns or select inputs.
 * Assumes 'name' and 'id' fields exist in the respective database tables.
 *
 * @return array Associative array containing lists of users, regions, and branches.
 */
function UserRegionBranch(){
    // Retrieve users and format them as 'name' => 'id'
    $users = User::pluck('name', 'id')->toArray();

    // Retrieve regions and format them as 'name' => 'id'
    $regions = Region::pluck('name', 'id')->toArray();

    // Retrieve branches and format them as 'name' => 'id'
    $branches = Branch::pluck('name', 'id')->toArray();

    // Return the formatted data
    return [
        'users' => $users,
        'regions' => $regions,
        'branches' => $branches
    ];
}
function allBranches(){
    // Retrieve branches and format them as 'name' => 'id'
    return Branch::pluck('name', 'id')->toArray();
}
if (!function_exists('addNotifications')) {
    function addNotifications($data = [])
    {
       \DB::table('notifications')->insert($data);
    }
}
// /////////////
if (!function_exists('intakeYear')) {
    function intakeYear()
    {
        $currentYear = date('Y');
        $years = [];

        for ($i = 0; $i <= 5; $i++) {
            $years[] = $currentYear + $i;
        }

        return $years;
    }
}

///////////////
if (!function_exists('FetchTimeZone')) {
    function FetchTimeZone()
    {
       $timezone =[
        "Africa/Abidjan",
        "Africa/Accra",
        "Africa/Addis_Ababa",
        "Africa/Algiers",
        "Africa/Asmara",
        "Africa/Asmera",
        "Africa/Bamako",
        "Africa/Bangui",
        "Africa/Banjul",
        "Africa/Bissau",
        "Africa/Blantyre",
        "Africa/Brazzaville",
        "Africa/Bujumbura",
        "Africa/Cairo",
        "Africa/Casablanca",
        "Africa/Ceuta",
        "Africa/Conakry",
        "Africa/Dakar",
        "Africa/Dar_es_Salaam",
        "Africa/Djibouti",
        "Africa/Douala",
        "Africa/El_Aaiun",
        "Africa/Freetown",
        "Africa/Gaborone",
        "Africa/Harare",
        "Africa/Johannesburg",
        "Africa/Juba",
        "Africa/Kampala",
        "Africa/Khartoum",
        "Africa/Kigali",
        "Africa/Kinshasa",
        "Africa/Lagos",
        "Africa/Libreville",
        "Africa/Lome",
        "Africa/Luanda",
        "Africa/Lubumbashi",
        "Africa/Lusaka",
        "Africa/Malabo",
        "Africa/Maputo",
        "Africa/Maseru",
        "Africa/Mbabane",
        "Africa/Mogadishu",
        "Africa/Monrovia",
        "Africa/Nairobi",
        "Africa/Ndjamena",
        "Africa/Niamey",
        "Africa/Nouakchott",
        "Africa/Ouagadougou",
        "Africa/Porto-Novo",
        "Africa/Sao_Tome",
        "Africa/Timbuktu",
        "Africa/Tripoli",
        "Africa/Tunis",
        "Africa/Windhoek",
        "America/Adak",
        "America/Anchorage",
        "America/Anguilla",
        "America/Antigua",
        "America/Araguaina",
        "America/Argentina/Buenos_Aires",
        "America/Argentina/Catamarca",
        "America/Argentina/ComodRivadavia",
        "America/Argentina/Cordoba",
        "America/Argentina/Jujuy",
        "America/Argentina/La_Rioja",
        "America/Argentina/Mendoza",
        "America/Argentina/Rio_Gallegos",
        "America/Argentina/Salta",
        "America/Argentina/San_Juan",
        "America/Argentina/San_Luis",
        "America/Argentina/Tucuman",
        "America/Argentina/Ushuaia",
        "America/Aruba",
        "America/Asuncion",
        "America/Atikokan",
        "America/Atka",
        "America/Bahia",
        "America/Bahia_Banderas",
        "America/Barbados",
        "America/Belem",
        "America/Belize",
        "America/Blanc-Sablon",
        "America/Boa_Vista",
        "America/Bogota",
        "America/Boise",
        "America/Buenos_Aires",
        "America/Cambridge_Bay",
        "America/Campo_Grande",
        "America/Cancun",
        "America/Caracas",
        "America/Catamarca",
        "America/Cayenne",
        "America/Cayman",
        "America/Chicago",
        "America/Chihuahua",
        "America/Ciudad_Juarez",
        "America/Coral_Harbour",
        "America/Cordoba",
        "America/Costa_Rica",
        "America/Creston",
        "America/Cuiaba",
        "America/Curacao",
        "America/Danmarkshavn",
        "America/Dawson",
        "America/Dawson_Creek",
        "America/Denver",
        "America/Detroit",
        "America/Dominica",
        "America/Edmonton",
        "America/Eirunepe",
        "America/El_Salvador",
        "America/Ensenada",
        "America/Fort_Nelson",
        "America/Fort_Wayne",
        "America/Fortaleza",
        "America/Glace_Bay",
        "America/Godthab",
        "America/Goose_Bay",
        "America/Grand_Turk",
        "America/Grenada",
        "America/Guadeloupe",
        "America/Guatemala",
        "America/Guayaquil",
        "America/Guyana",
        "America/Halifax",
        "America/Havana",
        "America/Hermosillo",
        "America/Indiana/Indianapolis",
        "America/Indiana/Knox",
        "America/Indiana/Marengo",
        "America/Indiana/Petersburg",
        "America/Indiana/Tell_City",
        "America/Indiana/Vevay",
        "America/Indiana/Vincennes",
        "America/Indiana/Winamac",
        "America/Indianapolis",
        "America/Inuvik",
        "America/Iqaluit",
        "America/Jamaica",
        "America/Jujuy",
        "America/Juneau",
        "America/Kentucky/Louisville",
        "America/Kentucky/Monticello",
        "America/Knox_IN",
        "America/Kralendijk",
        "America/La_Paz",
        "America/Lima",
        "America/Los_Angeles",
        "America/Louisville",
        "America/Lower_Princes",
        "America/Maceio",
        "America/Managua",
        "America/Manaus",
        "America/Marigot",
        "America/Martinique",
        "America/Matamoros",
        "America/Mazatlan",
        "America/Mendoza",
        "America/Menominee",
        "America/Merida",
        "America/Metlakatla",
        "America/Mexico_City",
        "America/Miquelon",
        "America/Moncton",
        "America/Monterrey",
        "America/Montevideo",
        "America/Montreal",
        "America/Montserrat",
        "America/Nassau",
        "America/New_York",
        "America/Nipigon",
        "America/Nome",
        "America/Noronha",
        "America/North_Dakota/Beulah",
        "America/North_Dakota/Center",
        "America/North_Dakota/New_Salem",
        "America/Nuuk",
        "America/Ojinaga",
        "America/Panama",
        "America/Pangnirtung",
        "America/Paramaribo",
        "America/Phoenix",
        "America/Port-au-Prince",
        "America/Port_of_Spain",
        "America/Porto_Acre",
        "America/Porto_Velho",
        "America/Puerto_Rico",
        "America/Punta_Arenas",
        "America/Rainy_River",
        "America/Rankin_Inlet",
        "America/Recife",
        "America/Regina",
        "America/Resolute",
        "America/Rio_Branco",
        "America/Rosario",
        "America/Santa_Isabel",
        "America/Santarem",
        "America/Santiago",
        "America/Santo_Domingo",
        "America/Sao_Paulo",
        "America/Scoresbysund",
        "America/Shiprock",
        "America/Sitka",
        "America/St_Barthelemy",
        "America/St_Johns",
        "America/St_Kitts",
        "America/St_Lucia",
        "America/St_Thomas",
        "America/St_Vincent",
        "America/Swift_Current",
        "America/Tegucigalpa",
        "America/Thule",
        "America/Thunder_Bay",
        "America/Tijuana",
        "America/Toronto",
        "America/Tortola",
        "America/Vancouver",
        "America/Virgin",
        "America/Whitehorse",
        "America/Winnipeg",
        "America/Yakutat",
        "America/Yellowknife",
        "Antarctica/Casey",
        "Antarctica/Davis",
        "Antarctica/DumontDUrville",
        "Antarctica/Macquarie",
        "Antarctica/Mawson",
        "Antarctica/McMurdo",
        "Antarctica/Palmer",
        "Antarctica/Rothera",
        "Antarctica/South_Pole",
        "Antarctica/Syowa",
        "Antarctica/Troll",
        "Antarctica/Vostok",
        "Arctic/Longyearbyen",
        "Asia/Aden",
        "Asia/Almaty",
        "Asia/Amman",
        "Asia/Anadyr",
        "Asia/Aqtau",
        "Asia/Aqtobe",
        "Asia/Ashgabat",
        "Asia/Ashkhabad",
        "Asia/Atyrau",
        "Asia/Baghdad",
        "Asia/Bahrain",
        "Asia/Baku",
        "Asia/Bangkok",
        "Asia/Barnaul",
        "Asia/Beirut",
        "Asia/Bishkek",
        "Asia/Brunei",
        "Asia/Calcutta",
        "Asia/Chita",
        "Asia/Choibalsan",
        "Asia/Chongqing",
        "Asia/Chungking",
        "Asia/Colombo",
        "Asia/Dacca",
        "Asia/Damascus",
        "Asia/Dhaka",
        "Asia/Dili",
        "Asia/Dubai",
        "Asia/Dushanbe",
        "Asia/Famagusta",
        "Asia/Gaza",
        "Asia/Harbin",
        "Asia/Hebron",
        "Asia/Ho_Chi_Minh",
        "Asia/Hong_Kong",
        "Asia/Hovd",
        "Asia/Irkutsk",
        "Asia/Istanbul",
        "Asia/Jakarta",
        "Asia/Jayapura",
        "Asia/Jerusalem",
        "Asia/Kabul",
        "Asia/Kamchatka",
        "Asia/Karachi",
        "Asia/Kashgar",
        "Asia/Kathmandu",
        "Asia/Katmandu",
        "Asia/Khandyga",
        "Asia/Kolkata",
        "Asia/Krasnoyarsk",
        "Asia/Kuala_Lumpur",
        "Asia/Kuching",
        "Asia/Kuwait",
        "Asia/Macao",
        "Asia/Macau",
        "Asia/Magadan",
        "Asia/Makassar",
        "Asia/Manila",
        "Asia/Muscat",
        "Asia/Nicosia",
        "Asia/Novokuznetsk",
        "Asia/Novosibirsk",
        "Asia/Omsk",
        "Asia/Oral",
        "Asia/Phnom_Penh",
        "Asia/Pontianak",
        "Asia/Pyongyang",
        "Asia/Qatar",
        "Asia/Qostanay",
        "Asia/Qyzylorda",
        "Asia/Rangoon",
        "Asia/Riyadh",
        "Asia/Saigon",
        "Asia/Sakhalin",
        "Asia/Samarkand",
        "Asia/Seoul",
        "Asia/Shanghai",
        "Asia/Singapore",
        "Asia/Srednekolymsk",
        "Asia/Taipei",
        "Asia/Tashkent",
        "Asia/Tbilisi",
        "Asia/Tehran",
        "Asia/Tel_Aviv",
        "Asia/Thimbu",
        "Asia/Thimphu",
        "Asia/Tokyo",
        "Asia/Tomsk",
        "Asia/Ujung_Pandang",
        "Asia/Ulaanbaatar",
        "Asia/Ulan_Bator",
        "Asia/Urumqi",
        "Asia/Ust-Nera",
        "Asia/Vientiane",
        "Asia/Vladivostok",
        "Asia/Yakutsk",
        "Asia/Yangon",
        "Asia/Yekaterinburg",
        "Asia/Yerevan",
        "Atlantic/Azores",
        "Atlantic/Bermuda",
        "Atlantic/Canary",
        "Atlantic/Cape_Verde",
        "Atlantic/Faeroe",
        "Atlantic/Faroe",
        "Atlantic/Jan_Mayen",
        "Atlantic/Madeira",
        "Atlantic/Reykjavik",
        "Atlantic/South_Georgia",
        "Atlantic/St_Helena",
        "Atlantic/Stanley",
        "Australia/ACT",
        "Australia/Adelaide",
        "Australia/Brisbane",
        "Australia/Broken_Hill",
        "Australia/Canberra",
        "Australia/Currie",
        "Australia/Darwin",
        "Australia/Eucla",
        "Australia/Hobart",
        "Australia/LHI",
        "Australia/Lindeman",
        "Australia/Lord_Howe",
        "Australia/Melbourne",
        "Australia/NSW",
        "Australia/North",
        "Australia/Perth",
        "Australia/Queensland",
        "Australia/South",
        "Australia/Sydney",
        "Australia/Tasmania",
        "Australia/Victoria",
        "Australia/West",
        "Australia/Yancowinna",
        "Brazil/Acre",
        "Brazil/DeNoronha",
        "Brazil/East",
        "Brazil/West",
        "CET",
        "CST6CDT",
        "Canada/Atlantic",
        "Canada/Central",
        "Canada/Eastern",
        "Canada/Mountain",
        "Canada/Newfoundland",
        "Canada/Pacific",
        "Canada/Saskatchewan",
        "Canada/Yukon",
        "Chile/Continental",
        "Chile/EasterIsland",
        "Cuba",
        "EET",
        "EST",
        "EST5EDT",
        "Egypt",
        "Eire",
        "Etc/GMT",
        "Etc/GMT+0",
        "Etc/GMT+1",
        "Etc/GMT+10",
        "Etc/GMT+11",
        "Etc/GMT+12",
        "Etc/GMT+2",
        "Etc/GMT+3",
        "Etc/GMT+4",
        "Etc/GMT+5",
        "Etc/GMT+6",
        "Etc/GMT+7",
        "Etc/GMT+8",
        "Etc/GMT+9",
        "Etc/GMT-0",
        "Etc/GMT-1",
        "Etc/GMT-10",
        "Etc/GMT-11",
        "Etc/GMT-12",
        "Etc/GMT-13",
        "Etc/GMT-14",
        "Etc/GMT-2",
        "Etc/GMT-3",
        "Etc/GMT-4",
        "Etc/GMT-5",
        "Etc/GMT-6",
        "Etc/GMT-7",
        "Etc/GMT-8",
        "Etc/GMT-9",
        "Etc/GMT0",
        "Etc/Greenwich",
        "Etc/UCT",
        "Etc/UTC",
        "Etc/Universal",
        "Etc/Zulu",
        "Europe/Amsterdam",
        "Europe/Andorra",
        "Europe/Astrakhan",
        "Europe/Athens",
        "Europe/Belfast",
        "Europe/Belgrade",
        "Europe/Berlin",
        "Europe/Bratislava",
        "Europe/Brussels",
        "Europe/Bucharest",
        "Europe/Budapest",
        "Europe/Busingen",
        "Europe/Chisinau",
        "Europe/Copenhagen",
        "Europe/Dublin",
        "Europe/Gibraltar",
        "Europe/Guernsey",
        "Europe/Helsinki",
        "Europe/Isle_of_Man",
        "Europe/Istanbul",
        "Europe/Jersey",
        "Europe/Kaliningrad",
        "Europe/Kiev",
        "Europe/Kirov",
        "Europe/Kyiv",
        "Europe/Lisbon",
        "Europe/Ljubljana",
        "Europe/London",
        "Europe/Luxembourg",
        "Europe/Madrid",
        "Europe/Malta",
        "Europe/Mariehamn",
        "Europe/Minsk",
        "Europe/Monaco",
        "Europe/Moscow",
        "Europe/Nicosia",
        "Europe/Oslo",
        "Europe/Paris",
        "Europe/Podgorica",
        "Europe/Prague",
        "Europe/Riga",
        "Europe/Rome",
        "Europe/Samara",
        "Europe/San_Marino",
        "Europe/Sarajevo",
        "Europe/Saratov",
        "Europe/Simferopol",
        "Europe/Skopje",
        "Europe/Sofia",
        "Europe/Stockholm",
        "Europe/Tallinn",
        "Europe/Tirane",
        "Europe/Tiraspol",
        "Europe/Ulyanovsk",
        "Europe/Uzhgorod",
        "Europe/Vaduz",
        "Europe/Vatican",
        "Europe/Vienna",
        "Europe/Vilnius",
        "Europe/Volgograd",
        "Europe/Warsaw",
        "Europe/Zagreb",
        "Europe/Zaporozhye",
        "Europe/Zurich",
        "GB",
        "GB-Eire",
        "GMT",
        "GMT+0",
        "GMT-0",
        "GMT0",
        "Greenwich",
        "HST",
        "Hongkong",
        "Iceland",
        "Indian/Antananarivo",
        "Indian/Chagos",
        "Indian/Christmas",
        "Indian/Cocos",
        "Indian/Comoro",
        "Indian/Kerguelen",
        "Indian/Mahe",
        "Indian/Maldives",
        "Indian/Mauritius",
        "Indian/Mayotte",
        "Indian/Reunion",
        "Iran",
        "Israel",
        "Jamaica",
        "Japan",
        "Kwajalein",
        "Libya",
        "MET",
        "MST",
        "MST7MDT",
        "Mexico/BajaNorte",
        "Mexico/BajaSur",
        "Mexico/General",
        "NZ",
        "NZ-CHAT",
        "Navajo",
        "PRC",
        "PST8PDT",
        "Pacific/Apia",
        "Pacific/Auckland",
        "Pacific/Bougainville",
        "Pacific/Chatham",
        "Pacific/Chuuk",
        "Pacific/Easter",
        "Pacific/Efate",
        "Pacific/Enderbury",
        "Pacific/Fakaofo",
        "Pacific/Fiji",
        "Pacific/Funafuti",
        "Pacific/Galapagos",
        "Pacific/Gambier",
        "Pacific/Guadalcanal",
        "Pacific/Guam",
        "Pacific/Honolulu",
        "Pacific/Johnston",
        "Pacific/Kanton",
        "Pacific/Kiritimati",
        "Pacific/Kosrae",
        "Pacific/Kwajalein",
        "Pacific/Majuro",
        "Pacific/Marquesas",
        "Pacific/Midway",
        "Pacific/Nauru",
        "Pacific/Niue",
        "Pacific/Norfolk",
        "Pacific/Noumea",
        "Pacific/Pago_Pago",
        "Pacific/Palau",
        "Pacific/Pitcairn",
        "Pacific/Pohnpei",
        "Pacific/Ponape",
        "Pacific/Port_Moresby",
        "Pacific/Rarotonga",
        "Pacific/Saipan",
        "Pacific/Samoa",
        "Pacific/Tahiti",
        "Pacific/Tarawa",
        "Pacific/Tongatapu",
        "Pacific/Truk",
        "Pacific/Wake",
        "Pacific/Wallis",
        "Pacific/Yap",
        "Poland",
        "Portugal",
        "ROC",
        "ROK",
        "Singapore",
        "Turkey",
        "UCT",
        "US/Alaska",
        "US/Aleutian",
        "US/Arizona",
        "US/Central",
        "US/East-Indiana",
        "US/Eastern",
        "US/Hawaii",
        "US/Indiana-Starke",
        "US/Michigan",
        "US/Mountain",
        "US/Pacific",
        "US/Samoa",
        "UTC",
        "Universal",
        "W-SU",
        "WET",
        "Zulu"];

        return $timezone;
    }

        function formatKey($key)
    {
        $keyMappings = [
            'mode_of_payment' => 'Mode Of Payment',
            'mode_of_verification' => 'Mode Of Verification',
            'disability' => 'Disability',
            'english_test' => 'English Test',
            'username' => 'Username',
            'password' => 'Password',
            'drive_link' => 'Drive Link',
            'CAS_Documents_Checklist' => 'CAS Documents Checklist',
            'additional_notes' => 'Additional Notes (if any)',
            'admission_officer' => 'Admission Officer',
            'office_group_email' => 'Office Group Email',
            'brand' => 'Brand',
            'location' => 'Location',
            'passport_number' => 'Passport Number',
            'name' => 'Name',
            'email_id' => 'Email ID',
            'mobile_number' => 'Mobile Number',
            'address' => 'Address',
            'course' => 'Course',
            'email' => 'Email',
            'clientUserID' => 'Client User ID',
        ];

        // If key exists in mappings, return mapped value
        if (isset($keyMappings[$key])) {
            return $keyMappings[$key];
        }

        // Otherwise, convert underscores to spaces and capitalize each word
        return ucwords(str_replace('_', ' ', $key));
    }

    function is_json($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
if (!function_exists('formatLocalDateReturntime')) {
    function formatLocalDateReturntime($datetime, $timezone = 'UTC', $timezoneAbbr = null) {
        if (!$datetime) return null;

        try {
            $dt = new \Carbon\Carbon($datetime, 'UTC');
            $dt->setTimezone($timezone);

            $day = $dt->format('d');
            $month = $dt->format('M');
            $year = $dt->format('Y');
            $time = $dt->format('h:i A');

            if (!$timezoneAbbr) {
                $timezoneAbbr = $dt->format('T');
            }

            return [
                'formattedDate' => "$day, $month $year, $time ($timezoneAbbr)",
                'formattedtime' => "$time ($timezoneAbbr)",
                'timezone' => $timezone,
                'timezoneAbbr' => $timezoneAbbr,
            ];
        } catch (\Exception $e) {
            return [
                'formattedDate' => $datetime,
                'formattedtime' => $datetime,
                'timezone' => $timezone,
                'timezoneAbbr' => $timezoneAbbr,
            ];
        }
    }
}
