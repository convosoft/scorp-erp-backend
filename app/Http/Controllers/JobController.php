<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomQuestion;
use App\Models\InterviewSchedule;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Utility;
use App\Models\JobApplication;
use App\Models\JobApplicationNote;
use App\Models\JobCategory;
use App\Models\LogActivity;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
// set pull


    public function getJobs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'created_at' => 'nullable|date',
            'price_operator' => 'nullable|string|in:=,>,<,>=,<=',
            'price_value' => 'nullable|numeric',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = \Auth::user();

        if (!$user->can('manage job')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 403);
        }

        // Default pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Build the query
        $jobsQuery = Job::select(
            'jobs.*',
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'assigned_to.name as created_user'
        )
            ->leftJoin('users', 'users.id', '=', 'jobs.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'jobs.branch')
            ->leftJoin('regions', 'regions.id', '=', 'jobs.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'jobs.created_by');

        // Apply role-based filtering
        $jobsQuery = RoleBaseTableGet($jobsQuery, 'jobs.brand_id', 'jobs.region_id', 'jobs.branch', 'jobs.created_by');

        // Apply filters
        if ($request->filled('brand')) {
            $jobsQuery->where('jobs.brand_id', $request->brand);
        }

        if ($request->filled('region_id')) {
            $jobsQuery->where('jobs.region_id', $request->region_id);
        }

        if ($request->filled('branch_id')) {
            $jobsQuery->where('jobs.branch', $request->branch_id);
        }

        if ($request->filled('status')) {
            $jobsQuery->where('jobs.status', $request->status);
        }

        if ($request->filled('created_at')) {
            $jobsQuery->whereDate('jobs.created_at', '=', $request->created_at);
        }

        if ($request->filled('price_operator') && $request->filled('price_value')) {
            $jobsQuery->where('jobs.price', $request->price_operator, $request->price_value);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $jobsQuery->where(function ($query) use ($search) {
                $query->where('jobs.title', 'like', "%$search%")
                    ->orWhere('users.name', 'like', "%$search%")
                    ->orWhere('branches.name', 'like', "%$search%")
                    ->orWhere('regions.name', 'like', "%$search%");
            });
        }

        // Fetch paginated jobs
        $jobs = $jobsQuery
            ->orderBy('jobs.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Get summary data for active/inactive jobs
        $summary = [
            'total' => CountJob(['active', 'in_active']),
            'active' => CountJob(['active']),
            'in_active' => CountJob(['in_active']),
        ];

        // Return JSON response
        return response()->json([
            'status' => 'success',

            'data' => $jobs->items(),
            'current_page' => $jobs->currentPage(),
            'last_page' => $jobs->lastPage(),
            'total_records' => $jobs->total(),
            'per_page' => $jobs->perPage(),
            'summary' => $summary,

            'message' => __('Jobs retrieved successfully'),
        ]);
    }


    /**
     * Filters for jobs.
     */

    private function jobsFilters(Request $request)
    {
        $filters = [];

        if ($request->filled('name')) {
            $filters['name'] = $request->input('name');
        }

        if ($request->filled('brand')) {
            $filters['brand'] = $request->input('brand');
        }

        if ($request->filled('region_id')) {
            $filters['region_id'] = $request->input('region_id');
        }

        if ($request->filled('branch_id')) {
            $filters['branch_id'] = $request->input('branch_id');
        }

        if ($request->filled('lead_assigned_user')) {
            $filters['deal_assigned_user'] = $request->input('lead_assigned_user');
        }

        if ($request->filled('stages')) {
            $filters['stage_id'] = $request->input('stages');
        }

        if ($request->filled('users')) {
            $filters['users'] = $request->input('users');
        }

        if ($request->filled('created_at_from')) {
            $filters['created_at_from'] = $request->input('created_at_from');
        }

        if ($request->filled('created_at_to')) {
            $filters['created_at_to'] = $request->input('created_at_to');
        }

        if ($request->filled('tag')) {
            $filters['tag'] = $request->input('tag');
        }

        if ($request->filled('price')) {
            $price = $request->input('price');
            $operator = '=';
            $value = $price;

            if (preg_match('/^(<=|>=|<|>)/', $price, $matches)) {
                $operator = $matches[1];
                $value = (float) substr($price, strlen($operator));
            }

            $filters['price'] = ['operator' => $operator, 'value' => $value];
        }

        return $filters;
    }

    public function createJob(Request $request)
    {
        // Check permission
        if (!\Auth::user()->can('create job')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validation rules
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'brand' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'category' => 'required|integer|exists:job_categories,id',
            'skill' => 'required|string|max:255',
            'position' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'required|string',
            'requirement' => 'string',
            'status' => 'nullable|in:active,inactive',
            'applicant' => 'nullable|array',
            'visibility' => 'nullable|array',
            'custom_question' => 'nullable|array',
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create a new Job instance
        $job = new Job();
        $job->title = $request->title;
        $job->brand_id = $request->brand;
        $job->region_id = $request->region_id;
        $job->branch = $request->branch_id;
        $job->category = $request->category;
        $job->skill = $request->skill;
        $job->position = $request->position;
        $job->status = $request->status ?? 'active'; // Default to 'active' if not provided
        $job->start_date = $request->start_date;
        $job->end_date = $request->end_date;
        $job->description = $request->description;
        $job->requirement = $request->requirement;
        $job->code = uniqid();
        $job->applicant = $request->has('applicant') ? implode(',', $request->applicant) : '';
        $job->visibility = $request->has('visibility') ? implode(',', $request->visibility) : '';
        $job->custom_question = $request->has('custom_question') ? implode(',', $request->custom_question) : '';
        $job->created_by = \Auth::id();

        // Save the job
        $job->save();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Job successfully created.',
            'data' => $job
        ], 201);
    }

    public function getJobDetails(Request $request)
    {
        // Validate the request to ensure jobID is provided and is an integer
        $validator = Validator::make($request->all(), [
            'jobID' => 'required|integer|exists:jobs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Fetch the job by ID
        $job = Job::find($request->jobID);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found.',
            ], 404);
        }

        // Extract and process relevant data
        $status = Job::$status;
        $job->applicant = !empty($job->applicant) ? explode(',', $job->applicant) : [];
        $job->visibility = !empty($job->visibility) ? explode(',', $job->visibility) : [];
        $job->skill = !empty($job->skill) ? explode(',', $job->skill) : [];


        // Return the response
        return response()->json([
            'status' => 'success',
            'message' => 'Job details retrieved successfully.',
            'data' =>  $job,
            'status_options' => $status,
        ], 200);
    }

    public function updateJob(Request $request)
    {
        // Check if the user has permission to edit jobs
        if (!\Auth::user()->can('edit job')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.',
            ], 403);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'jobID' => 'required|integer|exists:jobs,id',
            'title' => 'required',
            'brand' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'category' => 'required',
            'skill' => 'required',
            'position' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'required',
            'requirement' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Find the job using the jobID
        $job = Job::find($request->jobID);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found.',
            ], 404);
        }

        // Update job details
        $job->title = $request->title;
        $job->brand_id = $request->brand;
        $job->region_id = $request->region_id;
        $job->branch = $request->branch_id;
        $job->category = $request->category;
        $job->skill = $request->skill;
        $job->position = $request->position;
        $job->status = $request->status ?? $job->status; // Retain existing status if not provided
        $job->start_date = $request->start_date;
        $job->end_date = $request->end_date;
        $job->description = $request->description;
        $job->requirement = $request->requirement;
        $job->applicant = !empty($request->applicant) ? implode(',', $request->applicant) : '';
        $job->visibility = !empty($request->visibility) ? implode(',', $request->visibility) : '';
        $job->custom_question = !empty($request->custom_question) ? implode(',', $request->custom_question) : '';
        $job->save();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Job successfully updated.',
            'data' => $job,
        ], 200);
    }
    public function updateJobStatus(Request $request)
    {
        // Check if the user has permission to edit jobs
        if (!\Auth::user()->can('edit job')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'jobID' => 'required|integer|exists:jobs,id',
            'status' => 'required|in:active,in_active',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Find the job using the jobID
        $job = Job::find($request->jobID);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found.',
            ], 404);
        }

        // Update job details

        $job->status = $request->status ?? $job->status; // Retain existing status if not provided
        $job->save();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Job Status successfully updated.',
            'data' => $job,
        ], 200);
    }

    public function deleteJob(Request $request)
    {
        try {
            // Check if the user has permission to delete jobs
            if (!\Auth::user()->can('delete job')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission denied.',
                ], 403);
            }
    
            // Validate the job ID in the request
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:jobs,id',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }
    
            // Attempt to delete the job
            $deleted = Job::where('id', $request->id)->delete();
    
            if ($deleted) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Job successfully deleted.',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete the job.',
                ], 500);
            }
        } catch (\Exception $e) {
            // Catch any unexpected errors and log them
            \Log::error('Error deleting job: ' . $e->getMessage());
    
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }    

    public function jobRequirement(Request $request)
    {
        // Validate the incoming request
        $validator = \Validator::make($request->all(), [
            'code' => 'required|exists:jobs,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Find the job by code
        $job = Job::where('code', $request->code)->first();

        // Check if the job exists
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found.',
            ], 404);
        }

        // Check if the job status is 'in_active'
        if ($job->status == 'in_active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }



        // Fetch company settings
        $companySettings = [
            'title_text'      => \DB::table('settings')->where('created_by', $job->created_by)->where('name', 'title_text')->first(),
            'footer_text'     => \DB::table('settings')->where('created_by', $job->created_by)->where('name', 'footer_text')->first(),
            'company_favicon' => \DB::table('settings')->where('created_by', $job->created_by)->where('name', 'company_favicon')->first(),
            'company_logo'    => \DB::table('settings')->where('created_by', $job->created_by)->where('name', 'company_logo')->first(),
        ];




        // Return the data in a structured JSON response
        return response()->json([
            'status' => 'success',
            'companySettings' => $companySettings,
            'data' => $job,
        ]);
    }

    public function jobApplyData(Request $request)
    {
        // Validate the incoming request
        $validator = \Validator::make($request->all(), [
            'code' => 'required|exists:jobs,code',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'profile' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
            'academic_documents' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'id_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'start_date' => 'nullable|date',
            'how_did_you_hear' => 'nullable|string|max:255',
            'cover_letter' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'question' => 'nullable|array',
            'stage' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Check if the job exists
        $job = Job::where('code', $request->code)->first();
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found.',
            ], 404);
        }

        // Handle file uploads
        $filePaths = [];
        foreach (['profile', 'resume', 'academic_documents', 'id_card'] as $fileKey) {
            if ($request->hasFile($fileKey)) {
                $filePaths[$fileKey] = $request->file($fileKey)->store('JobApplicant', 'public');
            }
        }

        // Get the first job stage
        $stage = JobStage::where('id', $request->stage)->first();
        if (!$stage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job stage does not exist.',
            ], 400);
        }

        // Create the job application
        $jobApplication = new JobApplication();
        $jobApplication->job = $job->id;
        $jobApplication->name = $request->name;
        $jobApplication->email = $request->email;
        $jobApplication->phone = $request->phone;
        $jobApplication->profile = $filePaths['profile'] ?? '';
        $jobApplication->resume = $filePaths['resume'] ?? '';
        $jobApplication->academic_documents = $filePaths['academic_documents'] ?? '';
        $jobApplication->id_card = $filePaths['id_card'] ?? '';
        $jobApplication->start_date = $request->start_date;
        $jobApplication->how_did_you_hear = $request->how_did_you_hear;
        $jobApplication->cover_letter = $request->cover_letter;
        $jobApplication->dob = $request->dob;
        $jobApplication->gender = $request->gender;
        $jobApplication->country = $request->country;
        $jobApplication->state = $request->state;
        $jobApplication->city = $request->city;
        $jobApplication->custom_question = json_encode($request->question);
        $jobApplication->created_by = $job->created_by;
        $jobApplication->stage = $request->stage;
        $jobApplication->save();

        // Log the activity
        $log = new LogActivity();
        $log->type = 'info';
        $log->start_date = now()->toDateString();
        $log->time = now()->toTimeString();
        $log->note = json_encode([
            'title' => 'New Job Applicant',
            'message' => 'New Job Applicant created successfully',
        ]);
        $log->module_type = 'hrm';
        $log->module_id = $job->created_by;
        $log->created_by = $job->created_by;
        $log->save();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Job application successfully submitted.',
            'data' => [
                'jobApplication' => $jobApplication,
            ],
        ]);
    }

    public function pluckJobs(Request $request)
    {


        // Build the query for the Job model
        $query = Job::query();

        // Apply additional filters using the RoleBaseTableGet function
        $query = RoleBaseTableGet($query, 'brand_id', 'region_id', 'branch', 'created_by');

        // Fetch the jobs, ordered by title, and pluck the title and ID
        $jobs = $query->orderBy('title', 'ASC')->pluck('title', 'id')->toArray();

        // Return the results as a JSON response
        return response()->json(['status' => 'success', 'data' => $jobs], 200);
    }


}
