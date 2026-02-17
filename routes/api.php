<?php

use App\Http\Controllers\AgencyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginRegisterController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AllowanceController;
use App\Http\Controllers\AllowanceOptionController;
use App\Http\Controllers\AnnouncementCategoryController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApplicationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaySlipController;
use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\AttendanceEmployeeController;
use App\Http\Controllers\AwardTypeController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionsController;
use App\Http\Controllers\CompetenciesController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\CustomQuestionController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DeductionController;
use App\Http\Controllers\DeductionOptionController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\PayslipTypeController;
use App\Http\Controllers\TrainingTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\GoalTrackingController;
use App\Http\Controllers\GoalTypeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\InstituteCategoryController;
use App\Http\Controllers\InstituteController;
use App\Http\Controllers\JobCategoryController;
use App\Http\Controllers\JobStageController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LoanOptionController;
use App\Http\Controllers\OtherPaymentController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ModuleTypeController;
use App\Http\Controllers\MoiAcceptedController;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\PerformanceTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PermissionTypeController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryappriasalController;
use App\Http\Controllers\SetSalaryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TerminationTypeController;
use App\Http\Controllers\ToolkitApplicableFeeController;
use App\Http\Controllers\ToolkitChannelController;
use App\Http\Controllers\ToolkitInstallmentPayOutController;
use App\Http\Controllers\ToolkitLevelController;
use App\Http\Controllers\ToolkitPaymentTypeController;
use App\Http\Controllers\ToolkitTeamController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\UniversityMetaController;
use App\Http\Controllers\DestinationMetaController;
use App\Http\Controllers\UniversityRankController;
use App\Http\Controllers\UniversityRuleController;
use App\Http\Controllers\DestinationRuleController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserReassignController;
use App\Http\Controllers\ELTRequirementsController;
use App\Models\InterviewSchedule;
use App\Models\JobCategory;
use App\Models\TaskFile;
use App\Models\TrainingType;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\AttendanceEmployee;
use Carbon\Carbon;
use App\Http\Controllers\SendQueuedEmailsController;
use App\Http\Controllers\SendGridWebhookController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/sendQueuedEmails', [SendQueuedEmailsController::class, 'handle']);
Route::post('/sendgrid/webhook', [SendGridWebhookController::class, 'handle']);

Route::post('/brandDetailPublic', [UserController::class, 'brandDetailPublic']);

Route::get('/proxy-image', function (Request $request) {
    $url = $request->query('url');

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return response('Invalid URL', 400);
    }

    try {
        $response = Http::withOptions(['verify' => false]) // disable SSL verify if needed
            ->withHeaders([
                'Accept' => 'image/*',
            ])
            ->get($url);

        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type') ?? 'image/png')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        return response('Image fetch failed', $response->status());
    } catch (\Exception $e) {
        return response('Proxy error: ' . $e->getMessage(), 500);
    }
});
    Route::get('/getencrypted', function (Request $request) {
        $plaintext = $request->query('plaintext');
        // More comprehensive validation
        if (!$plaintext ) {
            return response()->json([
                'error' => 'Valid plaintext parameter is required'
            ], 400);
        }
        // Make sure encryptData function is available
        if (!function_exists('encryptData')) {
            return response()->json([
                'error' => 'Encryption service unavailable'
            ], 500);
        }

        try {
            $encrypted = encryptData($plaintext);

            return response()->json([
                'encrypted' => $encrypted,
                'plaintext_length' => $plaintext
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Encryption failed',
                'message' => $e->getMessage()
            ], 500);
        }
    });

    Route::get('/getdecrypted', function (Request $request) {
        $encryptedtext = $request->query('encryptedtext');
        // More comprehensive validation
        if (!$encryptedtext ) {
            return response()->json([
                'error' => 'Valid encryptedtext parameter is required'
            ], 400);
        }
        // Make sure encryptData function is available
        if (!function_exists('decryptData')) {
            return response()->json([
                'error' => 'decryption service unavailable'
            ], 500);
        }

        try {
            $plaintext = decryptData($encryptedtext);

            return response()->json([
                'encryptedtext' => $encryptedtext,
                'plaintext_length' => $plaintext
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Encryption failed',
                'message' => $e->getMessage()
            ], 500);
        }
    });

    Route::get('PayslipAutoGenerateEachMonth/', [PaySlipController::class, 'PayslipAutoGenerateEachMonth']);
    Route::get('/sendexpiredDocumentEmail', [UserController::class, 'sendexpiredDocumentEmail']);
    Route::get('/getCronAttendances', [AttendanceEmployeeController::class, 'getCronAttendances']);
    Route::get('/sendBirthdayAndAnniversaryEmails', [UserController::class, 'sendBirthdayAndAnniversaryEmails']);
    Route::get('/convertToBase64', [GeneralController::class, 'convertToBase64']);
    Route::post('/getTables', [GeneralController::class, 'getTables']);
    Route::post('/getTableData', [GeneralController::class, 'getTableData']);
    Route::post('/generateSop', [OpenAIController::class, 'generateSop']);
    Route::get('/getPublicUniversities', [UniversityController::class, 'getPublicUniversities']);

Route::get('/AttendanceEmployeeCron_old', function () {
    $today = Carbon::today()->toDateString();

    $excludedTypes = ['company', 'team', 'client', 'Agent'];

    // Get employees
    $employees = User::select('id', 'name')
        ->whereNotIn('type', $excludedTypes)
        ->get();

    // Existing attendance records
    $attendance = AttendanceEmployee::where('date', $today)
        ->pluck('employee_id')
        ->toArray();

    // Prepare absent records
    $insertData = [];
    $absentEmployeeNames = []; // to list who was marked absent

    foreach ($employees as $employee) {
        if (!in_array($employee->id, $attendance)) {
            $insertData[] = [
                'employee_id' => $employee->id,
                'date' => $today,
                'status' => 'Absent',
                'clock_in' => '00:00:00',
                'clock_out' => '00:00:00',
                'early_leaving' => '00:00:00',
                'overtime' => '00:00:00',
                'total_rest' => '00:00:00',
                'created_by' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $absentEmployeeNames[] = $employee->name;
        }
    }

    // Insert records
    $insertedCount = 0;
    if (!empty($insertData)) {
        AttendanceEmployee::insert($insertData);
        $insertedCount = count($insertData);
    }

    // Log file entry

    // Activity log
    addLogActivity([
        'type' => 'success',
        'note' => json_encode([
            'title' => 'Attendance marked absent for ' . $insertedCount . ' employees',
            'message' => 'Absent employees: ' . implode(', ', $absentEmployeeNames)
        ]),
        'module_id' => 0, // or some relevant ID if you want
        'module_type' => 'attendance',
        'notification_type' => 'attendance_cron_run',
    ],1);

    return "Attendance marked for absent. Records inserted: {$insertedCount}";
});


// Public routes of authtication
Route::controller(LoginRegisterController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/registerAgent', 'registerAgent');
    Route::post('/login', 'login');
    Route::post('/validateEmpId', 'validateEmpId');
    Route::post('/encryptDataEmpId', 'encryptDataEmpId');
    Route::post('/googlelogin', 'googlelogin');
    Route::post('/checkemail', 'checkemail');  // for checking email already exist or not
    Route::post('/changePassword', 'changePassword');
    Route::post('/forgotpasswordAgentOTP', 'forgotpasswordAgentOTP');
    Route::post('/verifyforgotpasswordOtp', 'verifyforgotpasswordOtp');
    Route::post('/changefogotPassword', 'changefogotPassword');
    Route::post('/acceptInvite', 'acceptInvite');
});

//Route::get('/appMeta', [ProductController::class, 'appMeta']);
Route::post('/jobRequirement', [JobController::class, 'jobRequirement']);
Route::post('/jobApplyData', [JobController::class, 'jobApplyData']);


// Protected routes of product and logout
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/resendAgentOTP', [LoginRegisterController::class, 'resendAgentOTP']);
    Route::post('/verifyOtp', [LoginRegisterController::class, 'verifyOtp']);
    Route::post('/userDetail', [LoginRegisterController::class, 'userDetail']);

    Route::post('AttendanceSetting', [UserController::class, 'AttendanceSetting']);
    Route::post('TargetSetting', [UserController::class, 'TargetSetting']);

    Route::post('/getProfileData', [UserController::class, 'getProfileData']);
    Route::post('/updateUserStatus', [UserController::class, 'updateUserStatus']);
    Route::post('/user/agree-terms', [UserController::class, 'agreeTerms']);
    Route::post('/logout', [LoginRegisterController::class, 'logout']);
    Route::get('/agentRequestGet', [AgentController::class, 'agentRequestGet']);
    Route::post('/agentRequestPost', [AgentController::class, 'agentRequestPost']);
    Route::post('/userTasksGet', [TaskController::class, 'userTasksGet']);
    Route::post('/createtask', [TaskController::class, 'createtask']);
    Route::post('/taskUpdate', [TaskController::class, 'taskUpdate']);
    Route::post('/updateTaskStatus', [TaskController::class, 'updateTaskStatus']);
    Route::post('/ShuffleTaskOwnership', [TaskController::class, 'ShuffleTaskOwnership']);
    Route::post('/getTaskDetails', [TaskController::class, 'getTaskDetails']);
    Route::post('/TaskDetails', [TaskController::class, 'TaskDetails']);

    Route::post('/taskDiscussionStore', [TaskController::class, 'taskDiscussionStore']);
    Route::post('/taskDiscussionUpdate', [TaskController::class, 'taskDiscussionUpdate']);
    Route::post('/taskDiscussionDelete', [TaskController::class, 'taskDiscussionDelete']);
    Route::post('/GetTaskDiscussion', [TaskController::class, 'GetTaskDiscussion']);

    Route::post('/taskDelete', [TaskController::class, 'taskDelete']);
    Route::get('/downloadTasks', [TaskController::class, 'downloadTasks']);
    Route::post('/ApprovedTaskStatus', [TaskController::class, 'ApprovedTaskStatus']);
    Route::post('/GetTaskByRelatedToRelatedType', [TaskController::class, 'GetTaskByRelatedToRelatedType']);



// space space

    // Leads start here
    Route::post('/getLeads', [LeadController::class, 'getLeads']);
    Route::post('/getLeadsByView', [LeadController::class, 'getLeadsByView']);
    Route::post('/leadsKanban', [LeadController::class, 'leadsKanban']);
    Route::post('/fetchColumns', [LeadController::class, 'fetchColumns']);
    Route::post('/importCsv', [LeadController::class, 'importCsv']);
    Route::post('/importEmailMarkitingCsv', [EmailTemplateController::class, 'fetchColumns']);
    Route::post('/getLeadDetails', [LeadController::class, 'getLeadDetails']);
    Route::post('/getLeadDetailOnly', [LeadController::class, 'getLeadDetailOnly']);
    Route::post('/saveLead', [LeadController::class, 'saveLead']);
    Route::post('/updateLead', [LeadController::class, 'updateLead']);
    Route::post('/deleteBulkLeads', [LeadController::class, 'deleteBulkLeads']);
    Route::post('/updateBulkLead', [LeadController::class, 'updateBulkLead']);
    Route::post('/addLeadTags', [LeadController::class, 'addLeadTags']);
    Route::post('/convertToAdmission', [LeadController::class, 'convertToAdmission']);
    Route::post('/leadsLabels', [LeadController::class, 'leadsLabels']);
    Route::post('/leadLabelStore', [LeadController::class, 'leadLabelStore']);
    Route::post('/leadsDelete', [LeadController::class, 'leadsDelete']);
    Route::post('/updateLeadStage', [LeadController::class, 'updateLeadStage']);
    Route::post('/StageHistory', [LeadController::class, 'StageHistory']);
    Route::post('/LeadOrgnizationUpdate', [LeadController::class, 'LeadOrgnizationUpdate']);
    Route::post('/LeadDriveLinkUpdate', [LeadController::class, 'LeadDriveLinkUpdate']);
    Route::post('/CreateOrUpdateLeadNotes', [LeadController::class, 'CreateOrUpdateLeadNotes']);
    Route::post('/GetLeadNotes', [LeadController::class, 'GetLeadNotes']);
    Route::post('/DeleteLeadNotes', [LeadController::class, 'DeleteLeadNotes']);
    Route::post('/EmailMarketing', [LeadController::class, 'EmailMarketing']);
    Route::post('/LeadStageHistory', [LeadController::class, 'LeadStageHistory']);
    Route::post('/dealStageHistory', [DealController::class, 'dealStageHistory']);
    Route::post('/getHistoryStageDays', [LeadController::class, 'getHistoryStageDays']);



    // user/brands
    Route::post('/getBrands', [UserController::class, 'getBrands']);
    Route::post('/addBrand', [UserController::class, 'addBrand']);
    Route::post('/updateBrand', [UserController::class, 'updateBrand']);
    Route::post('/deleteBrand', [UserController::class, 'deleteBrand']);
    Route::post('/brandDetail', [UserController::class, 'brandDetail']);

    // emergency/contact
    Route::post('/emergency-contact', [UserController::class, 'EmergencyContactPost']);
    Route::put('/emergency-contact', [UserController::class, 'EmergencyContactUpdate']);
    Route::delete('/emergency-contact', [UserController::class, 'EmergencyContactDelete']);

    // additional/address
    Route::post('/additional-address', [UserController::class, 'AdditionalAddressPost']);
    Route::post('/additional-address-update', [UserController::class, 'AdditionalAddressUpdate']);
    Route::post('/additional-address-delete', [UserController::class, 'AdditionalAddressDelete']);
    Route::post('/getAdditionalAddresses/{userId}', [UserController::class, 'getAdditionalAddresses']);

    // Salary appriasal
    Route::post('/addSalaryappriasal', [SalaryappriasalController::class, 'addSalaryappriasal']);
    Route::post('/updateSalaryappriasal', [SalaryappriasalController::class, 'updateSalaryappriasal']);
    Route::post('/getSalaryappriasals', [SalaryappriasalController::class, 'getSalaryappriasals']);
    Route::post('/deleteSalaryappriasal', [SalaryappriasalController::class, 'deleteSalaryappriasal']);
    Route::post('/SalaryappriasalDetails', [SalaryappriasalController::class, 'SalaryappriasalDetails']);


    // UserEmployeeFileUpdate
    Route::post('/UserEmployeeFileUpdate', [UserController::class, 'UserEmployeeFileUpdate']);
    Route::post('/BrandAttachments', [UserController::class, 'BrandAttachments']);
    Route::post('/UserEmployeeFileDocument', [UserController::class, 'UserEmployeeFileDocument']);
    Route::post('/agentFileDocument', [UserController::class, 'agentFileDocument']);
    Route::post('/employeeFileAttachments', [UserController::class, 'employeeFileAttachments']);
    Route::post('/employeeDocuments', [UserController::class, 'employeeDocuments']);
    Route::post('/UserEmployeeFileDocumentDelete', [UserController::class, 'UserEmployeeFileDocumentDelete']);
    Route::post('/EmployeeMetaUpdate', [UserController::class, 'storeOrUpdateMetas']);
    Route::post('/getEmployeeMeta', [UserController::class, 'getEmployeeMeta']);


    // trainers
    Route::post('/getTrainers', [TrainerController::class, 'getTrainers']);
    Route::post('/Trainers', [TrainerController::class, 'Trainers']);
    Route::post('/addTrainer', [TrainerController::class, 'addTrainer']);
    Route::post('/updateTrainer', [TrainerController::class, 'updateTrainer']);
    Route::post('/deleteTrainer', [TrainerController::class, 'deleteTrainer']);
    Route::post('/trainerDetail', [TrainerController::class, 'trainerDetail']);

    // Training
    Route::post('/getTraining', [TrainingController::class, 'getTraining']);
    Route::post('/addTraining', [TrainingController::class, 'addTraining']);
    Route::post('/updateTraining', [TrainingController::class, 'updateTraining']);
    Route::post('/deleteTraining', [TrainingController::class, 'deleteTraining']);
    Route::post('/TrainingDetail', [TrainingController::class, 'TrainingDetail']);
    Route::post('/updateTrainingStatus', [TrainingController::class, 'updateTrainingStatus']);

    // Training
    Route::post('/getIndicators', [IndicatorController::class, 'getIndicators']);
    Route::post('/addIndicator', [IndicatorController::class, 'addIndicator']);
    Route::post('/updateIndicator', [IndicatorController::class, 'updateIndicator']);
    Route::post('/deleteIndicator', [IndicatorController::class, 'deleteIndicator']);
    Route::post('/indicatorDetail', [IndicatorController::class, 'indicatorDetail']);

    // Apraisals
    Route::post('/getAppraisals', [AppraisalController::class, 'getAppraisals']);
    Route::post('/addApraisal', [AppraisalController::class, 'addApraisal']);
    Route::post('/updateAppraisal', [AppraisalController::class, 'updateAppraisal']);
    Route::post('/deleteAppraisal', [AppraisalController::class, 'deleteAppraisal']);
    Route::post('/appraisalDetails', [AppraisalController::class, 'appraisalDetails']);
    Route::post('/fetchperformance', [AppraisalController::class, 'fetchperformance']);
    Route::post('/fetchperformanceedit', [AppraisalController::class, 'fetchperformanceedit']);
    Route::get('/appraisalsummaryReport', [AppraisalController::class, 'appraisalSummaryReport']);

    // SetSalaries
    Route::post('/getSetSalaries', [SetSalaryController::class, 'getSetSalaries']);

    // Leaves
    Route::post('/getLeaves', [LeaveController::class, 'getLeaves']);
    Route::post('/getDashboardLeaves', [LeaveController::class, 'getDashboardLeaves']);
    Route::post('/addLeave', [LeaveController::class, 'addLeave']);
    Route::post('/updateLeave', [LeaveController::class, 'updateLeave']);
    Route::post('/deleteLeave', [LeaveController::class, 'deleteLeave']);
    Route::post('/Balance', [LeaveController::class, 'Balance']);
    Route::post('/changeLeaveStatus', [LeaveController::class, 'changeLeaveStatus']);
    // Leaves
    Route::post('/getAttendances', [AttendanceEmployeeController::class, 'getCombinedAttendances']);
    Route::post('/backuplist', [AttendanceEmployeeController::class, 'backuplist']);
    Route::post('/getDashboardAttendances', [AttendanceEmployeeController::class, 'getDashboardAttendances']);

    Route::post('/getemplyee_monthly_attandance', [AttendanceEmployeeController::class, 'getemplyee_monthly_attandance']);

    Route::post('/viewAttendance', [AttendanceEmployeeController::class, 'viewAttendance']);
    Route::post('/addAttendance', [AttendanceEmployeeController::class, 'addAttendance']);
    Route::post('/updateAttendance', [AttendanceEmployeeController::class, 'updateAttendance']);
    Route::post('/deleteAttendance', [AttendanceEmployeeController::class, 'deleteAttendance']);

    // AllowanceController
    Route::post('/getAllowances', [AllowanceController::class, 'getAllowances']);
    Route::post('/addEmpoyeeAllowance', [AllowanceController::class, 'addEmpoyeeAllowance']);
    Route::post('/updateEmployeeAllowance', [AllowanceController::class, 'updateEmployeeAllowance']);
    Route::post('/deleteEmployeeAllownce', [AllowanceController::class, 'deleteEmployeeAllownce']);


    Route::post('/getDeductions', [DeductionController::class, 'getDeductions']);
    Route::post('/addEmployeeDeduction', [DeductionController::class, 'addEmployeeDeduction']);
    Route::post('/updateEmployeeDeduction', [DeductionController::class, 'updateEmployeeDeduction']);
    Route::post('/deleteEmployeeDeduction', [DeductionController::class, 'deleteEmployeeDeduction']);

    Route::post('/getOvertimes', [OvertimeController::class, 'getOvertimes']);
    Route::post('/addEmployeeOvertime', [OvertimeController::class, 'addEmployeeOvertime']);
    Route::post('/updateEmployeeOvertime', [OvertimeController::class, 'updateEmployeeOvertime']);
    Route::post('/deleteEmployeeOvertime', [OvertimeController::class, 'deleteEmployeeOvertime']);

    Route::post('/getLoans', [LoanController::class, 'getLoans']);
    Route::post('/addEmployeeLoan', [LoanController::class, 'addEmployeeLoan']);
    Route::post('/updateEmployeeLoan', [LoanController::class, 'updateEmployeeLoan']);
    Route::post('/deleteEmployeeLoan', [LoanController::class, 'deleteEmployeeLoan']);


    // CommissionsController
    Route::post('/getCommissions', [CommissionsController::class, 'getCommissions']);
    Route::post('/addEmpoyeeCommissions', [CommissionsController::class, 'addEmpoyeeCommissions']);
    Route::post('/updateEmployeeCommissions', [CommissionsController::class, 'updateEmployeeCommissions']);
    Route::post('/deleteEmployeeCommissions', [CommissionsController::class, 'deleteEmployeeCommissions']);

    // OtherPaymentController
    Route::post('/getOtherPayments', [OtherPaymentController::class, 'getOtherPayments']);
    Route::post('/addEmpoyeeOtherPayment', [OtherPaymentController::class, 'addEmpoyeeOtherPayment']);
    Route::post('/updateEmployeeOtherPayment', [OtherPaymentController::class, 'updateEmployeeOtherPayment']);
    Route::post('/deleteEmployeeOtherPayment', [OtherPaymentController::class, 'deleteEmployeeOtherPayment']);

    // agency
    Route::post('/getagency', [AgencyController::class, 'index']);
    Route::post('/storeagency', [AgencyController::class, 'storeagency']);
    Route::post('/updateagency', [AgencyController::class, 'updateagency']);
    Route::post('/GetAgencyDetail', [AgencyController::class, 'GetAgencyDetail']);

    // Apraisals
    Route::post('/getGoalTrackings', [GoalTrackingController::class, 'getGoalTrackings']);
    Route::post('/addGoalTracking', [GoalTrackingController::class, 'addGoalTracking']);
    Route::post('/updateGoalTracking', [GoalTrackingController::class, 'updateGoalTracking']);
    Route::post('/deleteGoalTracking', [GoalTrackingController::class, 'deleteGoalTracking']);
    Route::post('/goalTrackingDetail', [GoalTrackingController::class, 'goalTrackingDetail']);

    Route::resource('leavetype', LeaveTypeController::class);
    Route::post('/leavetype-pluck', [LeaveTypeController::class, 'plucktitle']);


    // user/employees
    Route::post('/getDashboardBrandLastLogin', [UserController::class, 'getDashboardBrandLastLogin']);
    Route::post('/getDashboardholiday', [UserController::class, 'getDashboardholiday']);
    Route::post('/getDashboardCurrentMonthexpiredDocument', [UserController::class, 'getDashboardCurrentMonthexpiredDocument']);
    Route::post('/getDashboardBirthday', [UserController::class, 'getDashboardBirthday']);
    Route::post('/getDashboardLastLogin', [UserController::class, 'getDashboardLastLogin']);
    Route::post('/getDashboardEmployeesCount', [UserController::class, 'getDashboardEmployeesCount']);
    Route::post('/getEmployees', [UserController::class, 'getEmployees']);
    Route::post('/getAgents', [UserController::class, 'getAgents']);
    Route::post('/getAgentTeam', [UserController::class, 'getAgentTeam']);
    Route::post('/inviteAgent', [LoginRegisterController::class, 'inviteAgent']);
    Route::get('/employees', [UserController::class, 'employees']);
    Route::get('/Pluck_All_Users', [UserController::class, 'Pluck_All_Users']);
    Route::post('/Pluck_All_Users_by_filter', [UserController::class, 'Pluck_All_Users_by_filter']);

    Route::get('/get/employee/Details', [UserController::class, 'EmployeeDetails']);
    Route::post('/createEmployee', [UserController::class, 'createEmployee']);
    Route::post('/newEmployeeEmailSend', [UserController::class, 'newEmployeeEmailSend']);
    Route::post('/UpdateEmployee', [UserController::class, 'UpdateEmployee']);
    Route::post('/completeProfile', [UserController::class, 'completeProfile']);
    Route::post('/createAgentProfile', [UserController::class, 'createAgentProfile']);
    Route::post('/createBusinessProfile', [UserController::class, 'createBusinessProfile']);
    Route::post('/TerminateEmployee', [UserController::class, 'TerminateEmployee']);
    Route::post('/change_agent_status', [UserController::class, 'change_agent_status']);

    // Hrm Internal Employee Note Create
    Route::post('/EmployeeNoteGet', [UserController::class, 'HrmInternalEmployeeNoteGet']);
    Route::post('/EmployeeNoteUpdate', [UserController::class, 'HrmInternalEmployeeNoteUpdate']);
    Route::post('/EmployeeNoteStore', [UserController::class, 'HrmInternalEmployeeNoteStore']);
    Route::post('/EmployeeNoteDelete', [UserController::class, 'HrmInternalEmployeeNoteDelete']);

    //organization
    Route::post('getorganization', [OrganizationController::class, 'getorganization']);
    Route::post('organizationstore', [OrganizationController::class, 'organizationstore']);
    Route::post('organizationupdate', [OrganizationController::class, 'organizationupdate']);

    Route::post('organizationshow', [OrganizationController::class, 'organizationshow']);

    // Branches
    Route::post('/getRegions', [RegionController::class, 'getRegions']);
    Route::post('/addRegion', [RegionController::class, 'addRegion']);
    Route::post('/updateRegion', [RegionController::class, 'updateRegion']);
    Route::post('/deleteRegion', [RegionController::class, 'deleteRegion']);
    Route::post('/deleteBulkRegions', [RegionController::class, 'deleteBulkRegions']);
    Route::post('/regionDetail', [RegionController::class, 'regionDetail']);

    // Branches
    Route::post('/branchDetail', [BranchController::class, 'branchDetail']);
    Route::post('/getBrancheslist', [BranchController::class, 'getBranches']);
    Route::post('/addBranch', [BranchController::class, 'addBranch']);
    Route::post('/updateBranch', [BranchController::class, 'updateBranch']);
    Route::post('/deleteBranch', [BranchController::class, 'deleteBranch']);
    // holiday
    Route::post('/HolidayDetail', [HolidayController::class, 'HolidayDetail']);
    Route::post('/getHolidays', [HolidayController::class, 'getHolidays']);
    Route::post('/addHoliday', [HolidayController::class, 'addHoliday']);
    Route::post('/updateHoliday', [HolidayController::class, 'updateHoliday']);
    Route::post('/deleteHoliday', [HolidayController::class, 'deleteHoliday']);

    // Training type
    Route::post('/addTrainingType', [TrainingTypeController::class, 'addTrainingType']);

    Route::get('/TrainingTypes', [TrainingTypeController::class, 'TrainingTypes']);
    Route::get('/getTrainingTypes', [TrainingTypeController::class, 'getTrainingTypes']);
    Route::post('/updateTrainType', [TrainingTypeController::class, 'updateTrainType']);
    Route::post('/deleteTrainingType', [TrainingTypeController::class, 'deleteTrainingType']);

    // Department
    Route::post('/addDepartment', [DepartmentController::class, 'addDepartment']);
    Route::get('/getDepartments', [DepartmentController::class, 'getDepartments']);
    Route::post('/updateDepartment', [DepartmentController::class, 'updateDepartment']);
    Route::post('/deleteDepartment', [DepartmentController::class, 'deleteDepartment']);
    Route::post('/departmentsPluck', [DepartmentController::class, 'departmentsPluck']);



    // AllowanceOption
    Route::post('/addAllowanceOption', [AllowanceOptionController::class, 'addAllowanceOption']);
    Route::get('/getAllowanceOptions', [AllowanceOptionController::class, 'getAllowanceOptions']);
    Route::post('/updateAllowanceOption', [AllowanceOptionController::class, 'updateAllowanceOption']);
    Route::post('/deleteAllowanceOption', [AllowanceOptionController::class, 'deleteAllowanceOption']);
    Route::get('/pluckAllowanceOptions', [AllowanceOptionController::class, 'pluckAllowanceOptions']);

    // LoanOption
    Route::post('/addLoanOption', [LoanOptionController::class, 'addLoanOption']);
    Route::get('/getLoanOptions', [LoanOptionController::class, 'getLoanOptions']);
    Route::get('/pluckLoanOption', [LoanOptionController::class, 'pluckLoanOption']);
    Route::post('/updateLoanOption', [LoanOptionController::class, 'updateLoanOption']);
    Route::post('/deleteLoanOption', [LoanOptionController::class, 'deleteLoanOption']);

    // DeductionOption
    Route::post('/addDeductionOption', [DeductionOptionController::class, 'addDeductionOption']);
    Route::get('/getDeductionOptions', [DeductionOptionController::class, 'getDeductionOptions']);
    Route::get('/pluckDeductionOption', [DeductionOptionController::class, 'pluckDeductionOption']);
    Route::post('/updateDeductionOption', [DeductionOptionController::class, 'updateDeductionOption']);
    Route::post('/deleteDeductionOption', [DeductionOptionController::class, 'deleteDeductionOption']);

    // GoalType
    Route::post('/addGoalType', [GoalTypeController::class, 'addGoalType']);
    Route::get('/getGoalTypes', [GoalTypeController::class, 'getGoalTypes']);
    Route::get('/pluckGoalTypes', [GoalTypeController::class, 'pluckGoalTypes']);
    Route::post('/updateGoalType', [GoalTypeController::class, 'updateGoalType']);
    Route::post('/deleteGoalType', [GoalTypeController::class, 'deleteGoalType']);

    // UniversityRank
    Route::post('/addUniversityRank', [UniversityRankController::class, 'addUniversityRank']);
    Route::get('/getUniversityRanks', [UniversityRankController::class, 'getUniversityRanks']);
    Route::get('/pluckUniversityRanks', [UniversityRankController::class, 'pluckUniversityRanks']);
    Route::post('/updateUniversityRank', [UniversityRankController::class, 'updateUniversityRank']);
    Route::post('/deleteUniversityRank', [UniversityRankController::class, 'deleteUniversityRank']);

    // AwardType
    Route::post('/addAwardType', [AwardTypeController::class, 'addAwardType']);
    Route::get('/getAwardTypes', [AwardTypeController::class, 'getAwardTypes']);
    Route::post('/updateAwardType', [AwardTypeController::class, 'updateAwardType']);
    Route::post('/deleteAwardType', [AwardTypeController::class, 'deleteAwardType']);

    // TerminationType
    Route::post('/addTerminationType', [TerminationTypeController::class, 'addTerminationType']);
    Route::get('/getTerminationTypes', [TerminationTypeController::class, 'getTerminationTypes']);
    Route::post('/updateTerminationType', [TerminationTypeController::class, 'updateTerminationType']);
    Route::post('/deleteTerminationType', [TerminationTypeController::class, 'deleteTerminationType']);

    // JobCategory
    Route::post('/addJobCategory', [JobCategoryController::class, 'addJobCategory']);
    Route::get('/getJobCategorieslist', [JobCategoryController::class, 'getJobCategories']);
    Route::post('/updateJobCategory', [JobCategoryController::class, 'updateJobCategory']);
    Route::post('/deleteJobCategory', [JobCategoryController::class, 'deleteJobCategory']);

    // JobStage
    Route::post('/addJobStage', [JobStageController::class, 'addJobStage']);
    Route::get('/getJobStages', [JobStageController::class, 'getJobStages']);
    Route::get('/PluckJobStages', [JobStageController::class, 'PluckJobStages']);
    Route::post('/updateJobStage', [JobStageController::class, 'updateJobStage']);
    Route::post('/deleteJobStage', [JobStageController::class, 'deleteJobStage']);


    // PerformanceType
    Route::post('/addPerformanceType', [PerformanceTypeController::class, 'addPerformanceType']);
    Route::get('/getPerformanceTypes', [PerformanceTypeController::class, 'getPerformanceTypes']);
    Route::post('/updatePerformanceType', [PerformanceTypeController::class, 'updatePerformanceType']);
    Route::post('/deletePerformanceType', [PerformanceTypeController::class, 'deletePerformanceType']);


    // Competency
    Route::post('/addCompetency', [CompetenciesController::class, 'addCompetency']);
    Route::get('/getCompetencies', [CompetenciesController::class, 'getCompetencies']);
    Route::post('/updateCompetency', [CompetenciesController::class, 'updateCompetency']);
    Route::post('/deleteCompetency', [CompetenciesController::class, 'deleteCompetency']);
    Route::post('/getCompetenciesByType', [CompetenciesController::class, 'getCompetenciesByType']);

    // Branches
    Route::post('/getInterviews', [InterviewScheduleController::class, 'getInterviews']);
    Route::post('/showInterviews', [InterviewScheduleController::class, 'show']);
    Route::post('/updateInterviews', [InterviewScheduleController::class, 'update']);
    Route::post('/deleteInterviews', [InterviewScheduleController::class, 'destroy']);
    Route::post('/addInterveiw', [InterviewScheduleController::class, 'addInterveiw']);

    // Courses
    Route::post('/getCourses', [CourseController::class, 'getCourses']);
    Route::post('/showCourses', [CourseController::class, 'show']);
    Route::post('/updateCourses', [CourseController::class, 'updateCourses']);
    Route::post('/deleteCourse', [CourseController::class, 'deleteCourse']);
    Route::post('/addCourses', [CourseController::class, 'addCourses']);
    Route::post('/getCourseDetail', [CourseController::class, 'getCourseDetail']);
    Route::post('/pluckCourse', [CourseController::class, 'pluckCourse']);

    //  Job Applications
    Route::post('/candidate', [JobApplicationController::class, 'candidate']);
    Route::post('/getJobApplications', [JobApplicationController::class, 'getJobApplications']);
    Route::post('/getJobApplicationDetails', [JobApplicationController::class, 'getJobApplicationDetails']);
    Route::post('/archiveJobApplication', [JobApplicationController::class, 'archiveJobApplication']);
    Route::post('/getarchiveJobApplication', [JobApplicationController::class, 'getarchiveJobApplication']);
    Route::post('/jobBoardStore', [JobApplicationController::class, 'jobBoardStore']);
    Route::post('/getJobBoardDetail', [JobApplicationController::class, 'getJobBoardDetail']);
    Route::post('/deleteJobOnBoard', [JobApplicationController::class, 'deleteJobOnBoard']);
    Route::post('/addJobApplicationSkill', [JobApplicationController::class, 'addJobApplicationSkill']);
    Route::post('/addJobApplicationNote', [JobApplicationController::class, 'addJobApplicationNote']);
    Route::post('/deleteJobApplicationNote', [JobApplicationController::class, 'deleteJobApplicationNote']);
    Route::post('/getjobBoardStore', [JobApplicationController::class, 'getjobBoardStore']);
    Route::post('archiveApplication', [JobApplicationController::class, 'archiveApplication']);
    Route::post('/job-board/update', [JobApplicationController::class, 'jobBoardUpdate']);
    // Jobs
    Route::post('/getJobs', [JobController::class, 'getJobs']);
    Route::post('/PluckJobs', [JobController::class, 'PluckJobs']);
    Route::post('/createJob', [JobController::class, 'createJob']);
    Route::post('/getJobDetails', [JobController::class, 'getJobDetails']);
    Route::post('/updateJob', [JobController::class, 'updateJob']);
    Route::post('/updateJobStatus', [JobController::class, 'updateJobStatus']);
    Route::post('/deleteJob', [JobController::class, 'deleteJob']);

    // Custom Question
    Route::post('/getQuestions', [CustomQuestionController::class, 'getQuestions']);
    Route::post('/createQuestion', [CustomQuestionController::class, 'createQuestion']);
    Route::post('/updateQuestion', [CustomQuestionController::class, 'updateQuestion']);
    Route::post('/deleteQuestion', [CustomQuestionController::class, 'deleteQuestion']);


    // Pay slip

    Route::post('getpayslips/', [PaySlipController::class, 'index']);
    Route::get('payslips/{id}', [PaySlipController::class, 'show']);
    Route::post('payslips/', [PaySlipController::class, 'store']);
    Route::post('CreatePaySlips/', [PaySlipController::class, 'CreatePaySlips']);


    Route::post('payslips-show', [PaySlipController::class, 'searchJson']);
    Route::match(['put', 'patch'], 'payslips/{id}', [PaySlipController::class, 'update']);
    Route::delete('payslips/{id}', [PaySlipController::class, 'destroy']);
    Route::post('/deleteBulkPayslip', [PaySlipController::class, 'deleteBulkPayslip']);
    Route::post('Payslip_fetch', [PaySlipController::class, 'Payslip_fetch']);


    //
    Route::post('updateEmployeeSalary/{id}', [PaySlipController::class, 'updateEmployeeSalary']);

    // Pay slip Type
    Route::get('/payslip-types', [PayslipTypeController::class, 'index'])->middleware('can:manage payslip type');
    Route::get('/pluckPayslip', [PayslipTypeController::class, 'pluckPayslip'])->middleware('can:manage payslip type');
    Route::post('/payslip-store', [PayslipTypeController::class, 'store'])->middleware('can:create payslip type');
    Route::get('/payslip-get/{id}', [PayslipTypeController::class, 'show'])->middleware('can:manage payslip type');
    Route::put('/payslip-update/{id}', [PayslipTypeController::class, 'update'])->middleware('can:edit payslip type');
    Route::post('/payslip-delete/{id}', [PayslipTypeController::class, 'destroy'])->middleware('can:delete payslip type');

    Route::post('/instituteDetail', [InstituteController::class, 'instituteDetail']);
    Route::post('/getInstitutes', [InstituteController::class, 'getInstitutes']);
    Route::post('/pluckInstitute', [InstituteController::class, 'pluckInstitutes']);
    Route::post('/addInstitute', [InstituteController::class, 'addInstitute']);
    Route::post('/updateInstitute', [InstituteController::class, 'updateInstitute']);
    Route::post('/deleteInstitute', [InstituteController::class, 'deleteInstitute']);

    Route::post('/getUniversities', [UniversityController::class, 'getUniversities']);
    Route::post('/pluckUniversities', [UniversityController::class, 'pluckInstitutes']);
    Route::post('/addUniversities', [UniversityController::class, 'addUniversities']);
    Route::post('/updateUniversities', [UniversityController::class, 'updateUniversities']);
    Route::post('/deleteUniversities', [UniversityController::class, 'deleteUniversities']);
    Route::post('/universityDetail', [UniversityController::class, 'universityDetail']);
    Route::post('/applicationCountByUniversty', [UniversityController::class, 'applicationCountByUniversty']);
    Route::post('/addmissionCountByUniversty', [UniversityController::class, 'addmissionCountByUniversty']);
    Route::post('/brandWiseByUniversity', [UniversityController::class, 'brandWiseByUniversity']);
    Route::post('/updateUniversitiesByKey', [UniversityController::class, 'updateUniversitiesByKey']);
    Route::post('/SaveToggleCourse', [UniversityController::class, 'SaveToggleCourse']);
    Route::post('/getUniversityCourseStatus', [UniversityController::class, 'getUniversityCourseStatus']);
    Route::post('/addUpdateUniversityMeta', [UniversityMetaController::class, 'storeOrUpdateMetas']);
    Route::post('/getUniversityMeta', [UniversityMetaController::class, 'getUniversityMeta']);
    Route::post('/updateUniversityStatus', [UniversityController::class, 'updateUniversityStatus']);
    Route::post('/updateUniversityCourseStatus', [UniversityController::class, 'updateUniversityCourseStatus']);
    Route::post('/updateUniversityMOIStatus', [UniversityController::class, 'updateUniversityMOIStatus']);
    Route::post('/updateUniversityInternationalStatus', [UniversityController::class, 'updateUniversityInternationalStatus']);
    Route::post('/updateUniversityhomeStatus', [UniversityController::class, 'updateUniversityhomeStatus']);
    Route::post('/updateAboutUniversity', [UniversityController::class, 'updateAboutUniversity']);
    Route::post('/getPublicUniversitiesTiles', [UniversityController::class, 'getPublicUniversitiesTiles']);
    Route::post('/getIntakeMonthByUniversity', [UniversityController::class, 'getIntakeMonthByUniversity']);
    Route::post('/get_course_campus', [UniversityController::class, 'get_course_campus']);

     //   Institute Category
     Route::post('/addInstituteCategory', [InstituteCategoryController::class, 'addInstituteCategory']);
     Route::get('/getInstituteCategoryPluck', [InstituteCategoryController::class, 'getInstituteCategoryPluck']);
     Route::get('/getInstituteCategories', [InstituteCategoryController::class, 'getInstituteCategories']);
     Route::post('/updateInstituteCategory', [InstituteCategoryController::class, 'updateInstituteCategory']);
     Route::post('/deleteInstituteCategory', [InstituteCategoryController::class, 'deleteInstituteCategory']);
     //   Announcement Category
     Route::post('/addAnnouncementCategory', [AnnouncementCategoryController::class, 'addAnnouncementCategory']);
     Route::get('/getAnnouncementCategoryPluck', [AnnouncementCategoryController::class, 'getAnnouncementCategoryPluck']);
     Route::get('/getAnnouncementCategories', [AnnouncementCategoryController::class, 'getAnnouncementCategories']);
     Route::post('/updateAnnouncementCategory', [AnnouncementCategoryController::class, 'updateAnnouncementCategory']);
     Route::post('/deleteAnnouncementCategory', [AnnouncementCategoryController::class, 'deleteAnnouncementCategory']);
     //   Announcement
     Route::post('/addAnnouncement', [AnnouncementController::class, 'addAnnouncement']);
     Route::post('/getAnnouncement', [AnnouncementController::class, 'index']);
     Route::post('/updateAnnouncement', [AnnouncementController::class, 'updateAnnouncement']);
     Route::post('/deleteAnnouncement', [AnnouncementController::class, 'deleteAnnouncement']);
     Route::post('/announcementDetail', [AnnouncementController::class, 'announcementDetail']);

     //   Institute Category
     Route::post('/addTag', [TagController::class, 'addTag']);
     Route::post('/getTagPluck', [TagController::class, 'getTagPluck']);
     Route::get('/getTagsbytype', [TagController::class, 'getTags']);
     Route::post('/updateTag', [TagController::class, 'updateTag']);
     Route::post('/deleteTag', [TagController::class, 'deleteTag']);

       //   Designation
     Route::post('/addDesignation', [DesignationController::class, 'addDesignation']);
     Route::post('/getDesignationPluck', [DesignationController::class, 'getDesignationPluck']);
     Route::get('/getDesignations', [DesignationController::class, 'getDesignations']);
     Route::post('/updateDesignation', [DesignationController::class, 'updateDesignation']);
     Route::post('/deleteDesignation', [DesignationController::class, 'deleteDesignation']);

           //   Moduletype
     Route::post('/addModuleType', [ModuleTypeController::class, 'addModuleType']);
     Route::post('/getModuleTypePluck', [ModuleTypeController::class, 'getModuleTypePluck']);
     Route::get('/getModuleTypes', [ModuleTypeController::class, 'getModuleTypes']);
     Route::post('/updateModuleType', [ModuleTypeController::class, 'updateModuleType']);
     Route::post('/deleteModuleType', [ModuleTypeController::class, 'deleteModuleType']);
      //   PermissionType
     Route::post('/addPermissionType', [PermissionTypeController::class, 'addPermissionType']);
     Route::post('/getPermissionTypePluck', [PermissionTypeController::class, 'getPermissionTypePluck']);
     Route::get('/getPermissionTypes', [PermissionTypeController::class, 'getPermissionTypes']);
     Route::post('/updatePermissionType', [PermissionTypeController::class, 'updatePermissionType']);
     Route::post('/deletePermissionType', [PermissionTypeController::class, 'deletePermissionType']);

      //   ToolkitLevel
     Route::post('/addToolkitLevel', [ToolkitLevelController::class, 'addToolkitLevel']);
     Route::post('/getToolkitLevelPluck', [ToolkitLevelController::class, 'getToolkitLevelPluck']);
     Route::get('/getToolkitLevels', [ToolkitLevelController::class, 'getToolkitLevels']);
     Route::post('/updateToolkitLevel', [ToolkitLevelController::class, 'updateToolkitLevel']);
     Route::post('/deleteToolkitLevel', [ToolkitLevelController::class, 'deleteToolkitLevel']);

      //   ToolkitTeam
     Route::post('/addToolkitTeam', [ToolkitTeamController::class, 'addToolkitTeam']);
     Route::post('/getToolkitTeamPluck', [ToolkitTeamController::class, 'getToolkitTeamPluck']);
     Route::get('/getToolkitTeams', [ToolkitTeamController::class, 'getToolkitTeams']);
     Route::post('/updateToolkitTeam', [ToolkitTeamController::class, 'updateToolkitTeam']);
     Route::post('/deleteToolkitTeam', [ToolkitTeamController::class, 'deleteToolkitTeam']);

      //   ToolkitChannel
     Route::post('/addToolkitChannel', [ToolkitChannelController::class, 'addToolkitChannel']);
     Route::post('/getToolkitChannelPluck', [ToolkitChannelController::class, 'getToolkitChannelPluck']);
     Route::get('/getToolkitChannels', [ToolkitChannelController::class, 'getToolkitChannels']);
     Route::post('/updateToolkitChannel', [ToolkitChannelController::class, 'updateToolkitChannel']);
     Route::post('/deleteToolkitChannel', [ToolkitChannelController::class, 'deleteToolkitChannel']);


      //   ToolkitApplicableFee
     Route::post('/addToolkitApplicableFee', [ToolkitApplicableFeeController::class, 'addToolkitApplicableFee']);
     Route::post('/getToolkitApplicableFeePluck', [ToolkitApplicableFeeController::class, 'getToolkitApplicableFeePluck']);
     Route::get('/getToolkitApplicableFees', [ToolkitApplicableFeeController::class, 'getToolkitApplicableFees']);
     Route::post('/updateToolkitApplicableFee', [ToolkitApplicableFeeController::class, 'updateToolkitApplicableFee']);
     Route::post('/deleteToolkitApplicableFee', [ToolkitApplicableFeeController::class, 'deleteToolkitApplicableFee']);

      //   ToolkitPaymentType
     Route::post('/addToolkitPaymentType', [ToolkitPaymentTypeController::class, 'addToolkitPaymentType']);
     Route::post('/getToolkitPaymentTypePluck', [ToolkitPaymentTypeController::class, 'getToolkitPaymentTypePluck']);
     Route::get('/getToolkitPaymentTypes', [ToolkitPaymentTypeController::class, 'getToolkitPaymentTypes']);
     Route::post('/updateToolkitPaymentType', [ToolkitPaymentTypeController::class, 'updateToolkitPaymentType']);
     Route::post('/deleteToolkitPaymentType', [ToolkitPaymentTypeController::class, 'deleteToolkitPaymentType']);

      //   ToolkitInstallmentPayOut
     Route::post('/addToolkitInstallmentPayOut', [ToolkitInstallmentPayOutController::class, 'addToolkitInstallmentPayOut']);
     Route::post('/getToolkitInstallmentPayOutPluck', [ToolkitInstallmentPayOutController::class, 'getToolkitInstallmentPayOutPluck']);
     Route::get('/getToolkitInstallmentPayOuts', [ToolkitInstallmentPayOutController::class, 'getToolkitInstallmentPayOuts']);
     Route::post('/updateToolkitInstallmentPayOut', [ToolkitInstallmentPayOutController::class, 'updateToolkitInstallmentPayOut']);
     Route::post('/deleteToolkitInstallmentPayOut', [ToolkitInstallmentPayOutController::class, 'deleteToolkitInstallmentPayOut']);

        //   PermissionType
     Route::post('/addPermission', [PermissionController::class, 'addPermission']);
     Route::post('/getPermissionPluck', [PermissionController::class, 'getPermissionPluck']);
     Route::get('/getPermissions', [PermissionController::class, 'getPermissions']);
     Route::post('/updatePermission', [PermissionController::class, 'updatePermission']);
     Route::post('/deletePermission', [PermissionController::class, 'deletePermission']);
     Route::post('/allPermissions', [PermissionController::class, 'allPermissions']);
        //    EmailT emplate
     Route::post('/addEmailTemplate', [EmailTemplateController::class, 'addEmailTemplate']);
     Route::post('/getEmailTemplatePluck', [EmailTemplateController::class, 'getEmailTemplatePluck']);
     Route::get('/getEmailTemplates', [EmailTemplateController::class, 'getEmailTemplates']);
     Route::post('/updateEmailTemplate', [EmailTemplateController::class, 'updateEmailTemplate']);
     Route::post('/deleteEmailTemplate', [EmailTemplateController::class, 'deleteEmailTemplate']);
     Route::post('email_template_submit_to_queue', [EmailTemplateController::class, 'email_template_submit_to_queue'])->name('email_template_submit_to_queue');
     Route::get('email-marketing-queue', [EmailTemplateController::class, 'email_marketing_queue'])->name('email_marketing_queue');
     Route::post('email_marketing_queue_detail', [EmailTemplateController::class, 'email_marketing_queue_detail'])->name('email_marketing_queue');
     Route::post('email-marketing-approved-reject', [EmailTemplateController::class, 'email_marketing_approved_reject'])->name('email_marketing_approved_reject');
     Route::post('getEmailTemplateDetail', [EmailTemplateController::class, 'getEmailTemplateDetail'])->name('getEmailTemplateDetail');
     //    Role
     Route::post('/getRoleDetail', [RoleController::class, 'getRoleDetail']);
     Route::post('/addRole', [RoleController::class, 'addRole']);
     Route::post('/updateRole', [RoleController::class, 'updateRole']);
     Route::post('/getRoles', [RoleController::class, 'getRoles']);


     //   Institute DocumentType
     Route::post('/addDocumentType', [DocumentTypeController::class, 'addDocumentType']);
     Route::post('/getDocumentTypePluck', [DocumentTypeController::class, 'getDocumentTypePluck']);
     Route::get('/getDocumentTypes', [DocumentTypeController::class, 'getDocumentTypes']);
     Route::post('/updateDocumentType', [DocumentTypeController::class, 'updateDocumentType']);
     Route::post('/deleteDocumentType', [DocumentTypeController::class, 'deleteDocumentType']);


     //     University Rules
     Route::post('/getClients', [ClientController::class, 'getClients']);
     Route::post('/clientDetail', [ClientController::class, 'clientDetail']);
     Route::post('/updateClient', [ClientController::class, 'updateClient']);

     //     University Rules
     Route::post('/addUniversityRule', [UniversityRuleController::class, 'addUniversityRule']);
     Route::post('/getUniversityRules', [UniversityRuleController::class, 'getUniversityRules']);
     Route::post('/updateUniversityRule', [UniversityRuleController::class, 'updateUniversityRule']);
     Route::post('/deleteUniversityRule', [UniversityRuleController::class, 'deleteUniversityRule']);
     Route::post('/updateUniversityRulePosition', [UniversityRuleController::class, 'updateUniversityRulePosition']);



     //     Destination
     Route::post('/addDestination', [DestinationController::class, 'addDestination']);
     Route::post('/getDestinations', [DestinationController::class, 'getDestinations']);
     Route::post('/updateDestinationByKey', [DestinationController::class, 'updateDestinationByKey']);
     Route::post('/deleteDestination', [DestinationController::class, 'deleteDestination']);
     Route::post('/destinationDetail', [DestinationController::class, 'destinationDetail']);
     Route::post('/updateDestinationStatus', [DestinationController::class, 'updateDestinationStatus']);
     Route::post('/pluckDestinations', [DestinationController::class, 'pluckDestinations']);


    Route::post('/addUpdateDestinationMeta', [DestinationMetaController::class, 'storeOrUpdateMetas']);
    Route::post('/getDestinationMeta', [DestinationMetaController::class, 'getDestinationMeta']);

     //     Destination Rules
     Route::post('/addDestinationRule', [DestinationRuleController::class, 'addDestinationRule']);
     Route::post('/getDestinationRules', [DestinationRuleController::class, 'getDestinationRules']);
     Route::post('/updateDestinationRule', [DestinationRuleController::class, 'updateDestinationRule']);
     Route::post('/deleteDestinationRule', [DestinationRuleController::class, 'deleteDestinationRule']);
     Route::post('/updateDestinationRulePosition', [DestinationRuleController::class, 'updateDestinationRulePosition']);


     //     adminission
     Route::post('/getAdmission', [DealController::class, 'getAdmission']);
     Route::post('/getAdmissionByView', [DealController::class, 'getAdmissionByView']); // get by view
     Route::post('/getAdmissionDetails', [DealController::class, 'getAdmissionDetails']);
     Route::post('/getMoveApplicationPluck', [DealController::class, 'getMoveApplicationPluck']);
     Route::post('/moveApplicationsave', [DealController::class, 'moveApplicationsave']);
     Route::post('/updateAdmission', [DealController::class, 'updateAdmission']);
     Route::post('/GetadmissionNotes', [DealController::class, 'GetadmissionNotes']);


     //   Announcement Category
     Route::post('/addELTRequirement', [ELTRequirementsController::class, 'addELTRequirement']);
     Route::get('/getELTRequirementsPluck', [ELTRequirementsController::class, 'getELTRequirementsPluck']);
     Route::get('/getELTRequirements', [ELTRequirementsController::class, 'getELTRequirements']);
     Route::post('/updateELTRequirement', [ELTRequirementsController::class, 'updateELTRequirement']);
     Route::post('/deleteELTRequirement', [ELTRequirementsController::class, 'deleteELTRequirement']);




     //     application
     Route::post('/getApplications', [ApplicationsController::class, 'getApplications']);
     Route::post('/getApplicationsByView', [ApplicationsController::class, 'getApplicationsByView']);
     Route::post('/getDetailApplication', [ApplicationsController::class, 'getDetailApplication']);
     Route::post('/updateApplication', [ApplicationsController::class, 'updateApplication']);
     Route::post('/storeApplication', [ApplicationsController::class, 'storeApplication']);
     Route::post('/deleteApplication', [ApplicationsController::class, 'deleteApplication']);
     Route::post('/updateApplicationStage', [ApplicationsController::class, 'updateApplicationStage']);
     Route::post('/DeleteApplicationNotes', [ApplicationsController::class, 'DeleteApplicationNotes']);

     Route::post('/application_request_save_deposite', [ApplicationsController::class, 'application_request_save_deposite']);

     Route::post('/applicationAppliedStage', [ApplicationsController::class, 'applicationAppliedStage']);
     Route::post('/saveApplicationDepositRequest', [ApplicationsController::class, 'saveApplicationDepositRequest']);
     Route::post('/applicationNotesStore', [ApplicationsController::class, 'applicationNotesStore']);
     Route::post('/getApplicationNotes', [ApplicationsController::class, 'getApplicationNotes']);

     //     University Rules
     Route::post('/addMOIInstitutes', [MoiAcceptedController::class, 'addMOIInstitutes']);
     Route::post('/getMIOList', [MoiAcceptedController::class, 'getMIOList']);
     Route::post('/updateMOIInstitutes', [MoiAcceptedController::class, 'updateMOIInstitutes']);
    //  Route::post('/deleteUniversityRule', [UniversityRuleController::class, 'deleteUniversityRule']);
    // reports
    Route::post('/reports/visa-analysis', [ReportsController::class, 'visaAnalysis']);
    Route::get('/reports/deposit-analysis', [ReportsController::class, 'depositAnalysis']);



    // general routes
    Route::get('/getRolesPluck', [GeneralController::class, 'getRolesPluck']);
    Route::get('/getAllBrands', [GeneralController::class, 'getAllBrands']);
    Route::post('/getDefaultFiltersData', [GeneralController::class, 'getDefaultFiltersData']);
    Route::get('/getAllProjectDirectors', [GeneralController::class, 'getAllProjectDirectors']);
    Route::post('/getRegionBrands', [GeneralController::class, 'getRegionBrands']);
    Route::post('/agentTeamPluck', [GeneralController::class, 'agentTeamPluck']);
    Route::post('/getMultiRegionBrands', [GeneralController::class, 'getMultiRegionBrands']);
    Route::post('/getFilterData', [GeneralController::class, 'getFilterData']);
    Route::post('/getFilterBranchUsers', [GeneralController::class, 'getFilterBranchUsers']);
    Route::post('/getSavedFilters', [GeneralController::class, 'getSavedFilters']);
    Route::get('/getSources', [GeneralController::class, 'getSources']);
    Route::get('/getBranches', [GeneralController::class, 'getBranches']);
    Route::get('/getStages', [GeneralController::class, 'getStages']);
    Route::get('/getapplicationStagesPluck', [GeneralController::class, 'getapplicationStagesPluck']);
    Route::get('/getTags', [GeneralController::class, 'getTags']);
    Route::get('/getAllcurrencies', [GeneralController::class, 'getAllcurrencies']);
    Route::get('/getTagsByBrandId', [GeneralController::class, 'getTagsByBrandId']);
    Route::get('/getJobCategories', [GeneralController::class, 'getJobCategories']);
    Route::post('/FilterSave', [GeneralController::class, 'FilterSave']);
    Route::post('/UpdateFilterSave', [GeneralController::class, 'UpdateFilterSave']);
    Route::post('/Country', [GeneralController::class, 'Country']);
    Route::post('/Country/by/code', [GeneralController::class, 'CountryByCode']);
    Route::post('/UniversityByCountryCode', [GeneralController::class, 'UniversityByCountryCode']);
    Route::post('/getLogActivity', [GeneralController::class, 'getLogActivity']);
    Route::get('/getDistinctModuleTypes', [GeneralController::class, 'getDistinctModuleTypes']);
    Route::post('/DeleteSavedFilter', [GeneralController::class, 'DeleteSavedFilter']);
    Route::post('/GetBranchByType', [GeneralController::class, 'GetBranchByType']);
    Route::post('/leadsrequireddata', [GeneralController::class, 'leadsrequireddata']);
    Route::post('/getCitiesOnCode', [GeneralController::class, 'getCitiesOnCode']);

    Route::post('/DealTagPluck', [GeneralController::class, 'DealTagPluck']);
    Route::post('/DealStagPluck', [GeneralController::class, 'DealStagPluck']);
    Route::post('/ApplicationStagPluck', [GeneralController::class, 'ApplicationStagPluck']);
    Route::post('/getemailTags', [GeneralController::class, 'getemailTags']);
    Route::post('/getemailTagstype', [GeneralController::class, 'getemailTagstype']);
    // check is live
    Route::post('/totalSummary', [GeneralController::class, 'totalSummary']);
    Route::post('/getAttendanceReport', [GeneralController::class, 'getAttendanceReport']);
    Route::post('/saveSystemSettings', [GeneralController::class, 'saveSystemSettings']);
    Route::post('/fetchSystemSettings', [GeneralController::class, 'fetchSystemSettings']);

    // User Reassign

    Route::post('/reassignUserData', [UserReassignController::class, 'reassignUserData']);

});
