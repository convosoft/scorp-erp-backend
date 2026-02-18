<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Stage;
use App\Models\Utility;
use App\Models\Pipeline;
use App\Models\ClientDeal;
use App\Models\University;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\ApplicationNote;
use App\Models\ApplicationStage;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\Deal;
use App\Models\SavedFilter;
use App\Models\StageHistory;
use Illuminate\Http\Request;
use App\Models\DealApplication;
use App\Models\DealTask;
use App\Models\LeadTag;
use App\Models\Region;
use App\Models\ApplicationView;
use Illuminate\Support\Facades\Validator;
use Session;

class ApplicationsController extends Controller
{

    public function getApplications(Request $request)
    {
        $usr = \Auth::user();

        if (!($usr->can('view application') || in_array($usr->type, ['super admin', 'company', 'Admin Team']) || $usr->can('level 1'))) {
            return response()->json(['status' => 'error', 'message' => __('Permission Denied.')], 403);
        }

        $perPage = (int) $request->input('num_results_on_page', env("RESULTS_ON_PAGE", 50));
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);

        $app_query = DealApplication::select('deal_applications.*', 'users.name as UserName', 'lead_tags.tag as TagName')
            ->join('deals', 'deals.id', 'deal_applications.deal_id')
            ->leftJoin('leads', 'leads.is_converted', '=', 'deal_applications.deal_id')
            ->leftJoin('lead_tags', 'lead_tags.id', '=', 'deal_applications.tag_ids')
            ->leftJoin('users', 'users.id', '=', 'deals.assigned_to')
            ->orderBy('deal_applications.created_at', 'desc');

        // Role-based filtering
        if ($usr->type == 'super admin' || $usr->type == 'Admin Team' || $usr->can('level 1')) {
        } else if ($usr->type == 'company') {
            $app_query->where('deals.brand_id', $usr->id);
        } elseif (in_array($usr->type, ['Project Director', 'Project Manager']) || $usr->can('level 2')) {
            $app_query->whereIn('deals.brand_id', $brand_ids);
        } elseif (($usr->type == 'Region Manager' || $usr->can('level 3')) && !empty($usr->region_id)) {
            $app_query->where('deals.region_id', $usr->region_id);
        } elseif (in_array($usr->type, ['Branch Manager', 'Admissions Officer', 'Career Consultant', 'Admissions Manager', 'Marketing Officer']) || ($usr->can('level 4') && !empty($usr->branch_id))) {
            $app_query->where('deals.branch_id', $usr->branch_id);
        } elseif ($usr->type === 'Agent') {
            $app_query->where(function ($query) use ($usr) {
                $query->where('deals.assigned_to', $usr->id)
                    ->orWhere('deals.created_by', $usr->id);
            });
        } else {
            $app_query->where('deals.assigned_to', $usr->id);
        }

        // Apply filters
        $filters = $this->ApplicationFilters($request);
        foreach ($filters as $column => $value) {
            match ($column) {
                'name' => $app_query->whereIn('deal_applications.name', $value),
                'stage_id' => $app_query->whereIn('deal_applications.stage_id', $value),
                'university_id' => $app_query->whereIn('deal_applications.university_id', $value),
                'created_by' => $app_query->whereIn('deal_applications.created_by', $value),
                'brand' => $app_query->where('deals.brand_id', $value),
                'region_id' => $app_query->where('deals.region_id', $value),
                'branch_id' => $app_query->where('deals.branch_id', $value),
                'assigned_to' => $app_query->where('deals.assigned_to', $value),
                'created_at_from' => $app_query->whereDate('deal_applications.created_at', '>=', $value),
                'created_at_to' => $app_query->whereDate('deal_applications.created_at', '<=', $value),
                'tag' => $app_query->whereRaw('FIND_IN_SET(?, deal_applications.tag_ids)', [$value]),
                default => null,
            };
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            if (strpos($search, 'APC') === 0) {
                $numericId = preg_replace('/^[A-Z]+/', '', $search);
                $app_query->where('deal_applications.id', $numericId);
            } else {
                $app_query->where(function ($query) use ($search) {
                    $query->where('deal_applications.name', 'like', '%' . $search . '%')
                        ->orWhere('deal_applications.application_key', 'like', '%' . $search . '%')
                        ->orWhere('deal_applications.course', 'like', '%' . $search . '%');
                });
            }
        }


        // Get paginated results
        $applications = $app_query->distinct()->paginate($perPage);


        return response()->json([
            'status' => 'success',
            'data' => $applications->items(),
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
            'total_records' => $applications->total(),
            'per_page' => $applications->perPage(),
        ]);
    }

    public function getApplicationsByView(Request $request)
{
    $usr = \Auth::user();

    // Permission check
    if (!($usr->can('view application') || in_array($usr->type, ['super admin', 'company', 'Admin Team']) || $usr->can('level 1'))) {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission Denied.')
        ], 403);
    }

    $perPage = (int) $request->input('num_results_on_page', env("RESULTS_ON_PAGE", 50));
    $page = (int) $request->input('page', 1);

    // Start query on the view
    $query = ApplicationView::query();

    // Role-based filtering
    $userType = $usr->type;
    if ($userType === 'company') {
        $query->where('brand_id', $usr->id);
    } elseif ($userType === 'Region Manager' && $usr->region_id) {
        $query->where('region_id', $usr->region_id);
    } elseif ($userType === 'Branch Manager' && $usr->branch_id) {
        $query->where('branch_id', $usr->branch_id);
    } elseif ($userType === 'Agent') {
        $query->where('agent_id', $usr->agent_id);
    } elseif (!in_array($userType, ['super admin', 'Admin Team'])) {
        $query->where('assigned_to', $usr->id); // fallback
    }

    // Filters from request
    $filters = $this->ApplicationFilters($request); // your existing filter method
    foreach ($filters as $column => $value) {
        match ($column) {
            'name' => $query->where('name', 'like', "%{$value}%"),
            'stage_id' => $query->where('stage_id', $value),
            'university_id' => $query->where('university_id', $value),
            'created_by' => $query->where('created_by', $value),
            'brand' => $query->where('brand_id', $value),
            'region_id' => $query->where('region_id', $value),
            'branch_id' => $query->where('branch_id', $value),
            'assigned_to' => $query->where('assigned_to', $value),
            'created_at_from' => $query->whereDate('created_at', '>=', $value),
            'created_at_to' => $query->whereDate('created_at', '<=', $value),
            'tag' => $query->whereRaw('FIND_IN_SET(?, tag_ids)', [$value]),
            default => null,
        };
    }

    // Search filter
    if ($request->filled('search')) {
        $search = $request->input('search');
        if (strpos($search, 'APC') === 0) {
            $numericId = preg_replace('/^[A-Z]+/', '', $search);
            $query->where('id', $numericId);
        } else {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('application_key', 'like', "%{$search}%")
                  ->orWhere('course', 'like', "%{$search}%")
                  ->orWhere('university_name', 'like', "%{$search}%");
            });
        }
    }

    // Fetcttype filter (your existing logic)
    if ($request->filled('fetcttype')) {
        $type = $request->fetcttype;
        if ($type === 'yourapplications') $query->where('created_by', $usr->id);
        if ($type === 'assigntome') $query->where('assigned_to', $usr->id);
        if ($type === 'agentapplications') $query->whereNotNull('agent_id');
        else $query->whereNull('agent_id');
    }

    // CSV Export
    if ($request->input('download_csv')) {
        $applicationsCsv = $query->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="applications_'.time().'.csv"',
        ];

        $callback = function () use ($applicationsCsv) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID', 'Application Key', 'Name', 'University', 'Course', 'Brand', 'Branch', 'Assigned User', 'Stage', 'Created At'
            ]);

            foreach ($applicationsCsv as $app) {
                fputcsv($file, [
                    $app->id,
                    $app->application_key,
                    $app->name,
                    $app->university_name,
                    $app->course_name,
                    $app->brand_name,
                    $app->branch_name,
                    $app->assigned_user_name,
                    $app->stage_name,
                    $app->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Kanban view
    if ($request->input('view') === 'kanban') {
        $KANBAN_PER_PAGE = 1000;
        $applications = $query->orderBy('created_at', 'desc')->limit($KANBAN_PER_PAGE)->get();

        $stages = DB::table('application_stages')->select('id', 'name')->get();
        $colors = [
            1 => ['#4F46E5', '#eef2ff'],
            2 => ['#F59E0B', '#fff7ed'],
            3 => ['#22C55E', '#f0fdf4'],
            4 => ['#EC928E', '#fef2f2'],
            5 => ['#0EA5E9', '#e0f2fe'],
            6 => ['#6B7280', '#f3f4f6'],
        ];

        $kanban = [];
        foreach ($stages as $stage) {
            $stageApps = $applications->where('stage_id', $stage->id)->values();
            $kanban[] = [
                'stage_id' => $stage->id,
                'title' => $stage->name,
                'count' => $stageApps->count(),
                'color' => $colors[$stage->id][0] ?? '#000',
                'bgColor' => $colors[$stage->id][1] ?? '#fff',
                'applications' => $stageApps->map(fn($app) => [
                    'id' => $app->id,
                    'name' => $app->name,
                    'course' => $app->course,
                    'university' => $app->university_name,
                    'assigned_to' => $app->assigned_user_name,
                    'stage' => $app->stage_name,
                ]),
            ];
        }

        return response()->json([
            'status' => 'success',
            'view' => 'kanban',
            'data' => $kanban,
            'total_records' => $applications->count(),
        ]);
    }

    // List view - simple pagination
    $applications = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

    return response()->json([
        'status' => 'success',
        'data' => $applications->items(),
        'current_page' => $applications->currentPage(),
        'last_page' => $applications->lastPage(),
        'total_records' => $applications->total(),
        'per_page' => $applications->perPage(),
    ]);
}


    private function ApplicationFilters(Request $request)
    {
        $filters = [];

        if ($request->filled('applications')) {
            $filters['name'] = $request->input('applications');
        }

        if ($request->filled('stages')) {
            $filters['stage_id'] = $request->input('stages');
        }

        if ($request->filled('created_by')) {
            $filters['created_by'] = $request->input('created_by');
        }

        if ($request->filled('universities')) {
            $filters['university_id'] = $request->input('universities');
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

        if ($request->filled('created_at_from')) {
            $filters['created_at_from'] = $request->input('created_at_from');
        }

        if ($request->filled('created_at_to')) {
            $filters['created_at_to'] = $request->input('created_at_to');
        }

        if ($request->filled('lead_assigned_user')) {
            $filters['assigned_to'] = $request->input('lead_assigned_user');
        }

        if ($request->filled('tag')) {
            $filters['tag'] = $request->input('tag');
        }

        return $filters;
    }

    public function getDetailApplication(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:deal_applications,id',
        ]);

        $id = $request->id;

        $application = DealApplication::with([
            'city:id,name',
            'preinstitute:id,name',
            'country:country_code,name'
        ])->where('id', $id)->first();

        if ($application && $application->university_id) {
            $university = University::where('id', $application->university_id)->first();

            if ($university) {
                $country = Country::where('name', 'like', '%' . $university->country . '%')->first();
            } else {
                $country = null; // Handle case where the university doesn't exist
            }
        } else {
            $country = null; // Handle case where the application or university_id is invalid
        }

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found.'
            ], 404);
        }

        $stages = ApplicationStage::orderBy('id')->pluck('name', 'id')->toArray();
        $tags = [];

        $user = Auth::user();
        if ($user) {
            if (in_array($user->type, ['super admin', 'Admin Team'])) {
                $tags = LeadTag::pluck('id', 'tag')->toArray();
            } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('id', 'tag')->toArray();
            } elseif ($user->type == 'Region Manager') {
                $tags = LeadTag::where('region_id', $user->region_id)->pluck('id', 'tag')->toArray();
            } else {
                $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('id', 'tag')->toArray();
            }
        }

        $stage_histories = StageHistory::where('type', 'application')
            ->where('type_id', $id)
            ->pluck('stage_id')
            ->toArray();

        $SixTask = \App\Models\DealTask::where('related_to', $application->id)
                ->where('related_type', 'application')
                ->where('stage_request', 6)
                ->latest('id')->first();

        $OneTask = \App\Models\DealTask::where('related_to', $application->id)
                ->where('related_type', 'application')
                ->where('stage_request', 1)
                ->latest('id')->first();


        $deposit_meta = DB::table('meta')->where([
            ['parent_id', '=', $application->id],
            ['stage_id', '=', 4]
        ])->get();

        $applied_meta = DB::table('meta')->where([
            ['parent_id', '=', $application->id],
            ['stage_id', '=', 5]
        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'OneTask' => $OneTask,
                'SixTask' => $SixTask,
                'application' => $application,
                'country' => $country,
                'stages' => $stages,
                'tags' => $tags,
                'stage_histories' => $stage_histories,
                'deposit_meta' => $deposit_meta,
                'applied_meta' => $applied_meta
            ]
        ]);
    }

    public function storeApplication_old(Request $request)
    {
        if (!\Auth::user()->can('create application')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ]);
        }

        $validator = \Validator::make($request->all(), [
            'university' => 'required|exists:universities,id',
            'status' => 'required',
            'intake_month' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ]);
        }

        $university_name = optional(University::find($request->university))->name;
        if (!$university_name) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid university selected.')
            ]);
        }
        $university_name = str_replace(' ', '-', $university_name);

        $deal = Deal::find($request->Deal_id);
        if ($deal && $deal->clients->first()) {
            $passport_number = $deal->clients->first()->passport_number;
        } else {
            // Handle case where Deal or related Client is not found
            $passport_number = null;
        }
        if (!$deal) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid deal selected.')
            ]);
        }

        $userName = optional(User::find(optional(ClientDeal::where('deal_id', $request->Deal_id)->first())->client_id))->name;
        $is_exist = DealApplication::whereRaw(
            "application_key LIKE ?",
            ['%' . $passport_number . '-' . $university_name . '%']
        )->first();

        if ($passport_number && $is_exist) {
            return response()->json([
                'status' => 'error',
                'message' => __('An application with the passport number <b style="font-size: 1.2em;">' . $passport_number . '</b> already exists. Please use the Contacts section and search by passport number to view the student\'s application details.')
            ], 409);
        }
        if (!empty($request->courses_id)) {
            $course = Course::find($request->courses_id);
            $courseName = $course ?
                "{$course->name} - {$course->campus} - {$course->intake_month} - {$course->intakeYear} ({$course->duration})"
                : null;
        } else {
            $courseName = $request->CoursesName ?? null;
        }
        $new_app = DealApplication::create([
            'application_key' => "{$userName}-{$passport_number}-{$university_name}",
            'deal_id' => $request->Deal_id,
            'university_id' => $request->university,
            'course' => $courseName,
            'stage_id' => $request->status,
            'external_app_id' => $request->application_key,
            'intake' => $request->intake_month,
            'name' => "{$deal->name}-{$courseName}-{$university_name}-{$request->application_key}",
            'created_by' => Session::get('auth_type_id') ?? \Auth::id(),
        ]);

        $new_app->student_origin_country = $request->student_origin_country;
        $new_app->student_origin_city = $request->student_origin_city;
        $new_app->student_previous_university = $request->student_previous_university;
        $new_app->tag_ids = $request->tag_ids ?? '';
        $new_app->brand_id = $deal->brand_id;
        $new_app->region_id = $deal->region_id;
        $new_app->region_id = $deal->region_id;
        $new_app->assigned_to = $deal->assigned_to;
        $new_app->country_id = Country::where('country_code', $request?->countryId)->first()?->id;
        $new_app->campus = $request->campus;
        $new_app->intakeYear = $request->intakeYear;
        $new_app->course_id = $request->courses_id ?? "0";
        $new_app->course_id = $request->courses_id ?? "0";
        $new_app->save();
        // Update deal stage
        $highestStageApp = DealApplication::where('deal_id', $request->Deal_id)->orderByDesc('stage_id')->first();
        $deal->stage_id = match ($highestStageApp?->stage_id) {
            '0' => 0,
            '1', '2' => 1,
            '3', '4' => 2,
            '5', '6' => 3,
            '7', '8' => 4,
            '9', '10' => 5,
            '11' => 6,
            '12' => 7,
            default => 0,
        };
        $deal->save();

        addLeadHistory([
            'stage_id' => $request->status,
            'type_id' => $new_app->id,
            'type' => 'application'
        ]);

        addLogActivity([
            'type' => 'info',
            'note' => json_encode(['title' => 'Stage Updated', 'message' => 'Application stage updated successfully.']),
            'module_id' => $new_app->id,
            'module_type' => 'application',
            'notification_type' => 'Stage Updated',
        ]);

        return response()->json([
            'status' => 'success',
            'app_id' => $new_app->id,
            'message' => __('Application successfully created!')
        ]);
    }


    public function storeApplication(Request $request)
    {
        if (\Auth::user()->can('create application')) {
        $user_id = ClientDeal::where('deal_id', $request->id)->value('client_id');
        if (!$user_id || !User::find($user_id)) {
            return  response()->json([
                'status' => 'error',
                'message' => 'Client not found for this admission.'
            ]);
        }
        $user = User::find($user_id); // Single object, not plural
        $university = University::select('name')->where('id', (int)$request->university)->first(); // Fixed missing semicolon
        // Validation rules
        $validator = \Validator::make($request->all(), [
            'university' => [
                'required',
                function ($attribute, $value, $fail) use ($user_id, $user, $university) {
                    if (DealApplication::where('contact_id', $user_id)
                        ->where('university_id', $value)
                        ->exists()) {
                        $fail(($user?->name ?? '') . ' - ' .
                              ($user?->passport_number ?? '') . ' - ' .
                              ($university?->name ?? '') . ' - This Client and University combination already exists.'
                            );
                    }
                },
            ],
            'status' => 'required',
            'intake_month' => 'required',
            'tag_ids' => 'required|array',
        ]);

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return response()->json([
                'status' => 'error',
                'errors' => $messages
            ]);
        }



            //check application exist or not
            $passport_number = $request->passport_number;
            $university_name = University::select('name')->where(['id' => (int)$request->university])->first()->name;
            $university_name = str_replace(' ', '-', $university_name);

            $deal = Deal::findOrFail($request->id);
            $userName = optional(
                User::find(
                    optional(
                        ClientDeal::where('deal_id', $request->id)->first()
                    )->client_id
                )
            )->name;
            //
            if (!empty($request->course)) {
                $course = Course::find($request->course);
                if (!empty($course)) {
                    $course_id = $course->id;
                    $courseName = $course->name . ' - ' . $course->campus . ' - ' . $course->intake_month . ' - ' . $course->intakeYear . ' (' . $course->duration . ')';
                }
            } else {
                $course_id = null;
                $courseName = $request->course2;
            }

             $student_origin_country = $request->student_origin_country;
            $student_origin_country = Country::where('country_code', $student_origin_country)->first();
             $countryId = $request->countryId;
            $countryId = Country::where('country_code', $countryId)->first();

            $new_app = DealApplication::create([
                'student_origin_country' => $student_origin_country->id ?? null,
                'student_origin_city' => $request->student_origin_city ?? null,
                'student_previous_university' => $request->student_previous_university ?? null,
                'application_key' => $userName . '-' . $passport_number . '-' . $university_name,
                'deal_id' => $request->id,
                'university_id' => (int)$request->university,
                'course' => $courseName,
                'stage_id' => $request->status,
                'external_app_id' => $request->application_key,
                'intake' => $request->intake_month,
                'name' => $deal->name . '-' . $courseName . '-' . $university_name . '-' . $request->application_key,
                'created_by' => Session::get('auth_type_id') != null ? Session::get('auth_type_id') : \Auth::user()->id,
            ]);
            $new_app->tag_ids     = !empty($request->tag_ids) ? implode(',', $request->tag_ids) : '';
            $new_app->brand_id = $deal->brand_id;
            $new_app->country_id = $countryId->id;
            $new_app->agent_id = $deal->agent_id;
            $new_app->campus = $request->campus;
            $new_app->intakeYear = $request->intakeYear;
            $new_app->course_id = $course_id;
            $new_app->contact_id = $user_id;
            $new_app->save();

            // Latest Stage Update Of Admission
            $DealApplication = DealApplication::where('deal_id', $request->id)->orderBy('stage_id', 'desc')->first();
            if(!empty($DealApplication)){
                if($DealApplication->stage_id == '0'){
                    $deal->stage_id = 0;
                }elseif($DealApplication->stage_id == '1' || $DealApplication->stage_id == '2')
                {
                    $deal->stage_id = 1;
                }elseif($DealApplication->stage_id == '3' || $DealApplication->stage_id == '4'){

                    $deal->stage_id = 2;
                }elseif($DealApplication->stage_id == '5' || $DealApplication->stage_id == '6'){

                    $deal->stage_id = 3;
                }elseif($DealApplication->stage_id == '7' || $DealApplication->stage_id == '8'){

                    $deal->stage_id = 4;
                }elseif($DealApplication->stage_id == '9' || $DealApplication->stage_id == '10'){

                    $deal->stage_id = 5;
                }elseif($DealApplication->stage_id == '11'){

                    $deal->stage_id = 6;
                }elseif($DealApplication->stage_id == '12'){

                    $deal->stage_id = 7;
                }

                $deal->save();
            }else{
                $deal->stage_id = 0;
                $deal->save();
            }

            //Add Stage History
            $data_for_stage_history = [
                'stage_id' => $request->status,
                'type_id' => $new_app->id,
                'type' => 'application'
            ];
            addLeadHistory($data_for_stage_history);


            //Log
            $data = [
                'type' => 'info',
                'note' => response()->json([
                    'title' => 'Stage Updated',
                    'message' => 'Application stage updated successfully.'
                ]),
                'module_id' => $new_app->id,
                'module_type' => 'application',
                'notification_type' => 'Stage Updated'
            ];
            addLogActivity($data);

            return response()->json([
                'status' => 'success',
                'app_id' => $new_app->id,
                'message' => __('Application successfully created!')
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ]);
        }
    }


    public function updateApplication(Request $request)
    {
        if (!\Auth::user()->can('create application')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ]);
        }

        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:deal_applications,id',
            'university' => 'required|exists:universities,id',
            'status' => 'required|integer',
            'intake_month' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ]);
        }

        $university_name = optional(University::find($request->university))->name;
        if (!$university_name) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid university selected.')
            ]);
        }
        $university_name = str_replace(' ', '-', $university_name);
        $application = DealApplication::find($request->id);
        $deal = Deal::find($application->deal_id);
        if ($deal && $deal->clients->first()) {
            $passport_number = $deal->clients->first()->passport_number;
        } else {
            // Handle case where Deal or related Client is not found
            $passport_number = null;
        }
        if (!$deal) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid deal selected.')
            ]);
        }

        $userName = optional(User::find(optional(ClientDeal::where('deal_id', $application->deal_id)->first())->client_id))->name;
        $deal = Deal::findOrFail($application->deal_id);

        $university = University::find($request->university);
        $university_name = str_replace(' ', '-', $university->name);
        $passport_number = $request->passport_number;
        $application_key = $passport_number . '-' . $university_name;

        // Duplicate Check
        $duplicate = DealApplication::join('deals', 'deal_applications.deal_id', '=', 'deals.id')
            ->whereRaw("application_key LIKE ?", ["%$application_key%"])
            ->select('deal_applications.*')
            ->first();

        if ($passport_number && $duplicate && $duplicate->id != $application->id && User::find($duplicate->created_by)) {
            $existing_deal = Deal::find($duplicate->deal_id);
            $existing_brand = User::find($existing_deal?->brand_id)?->name ?? '';
            $regionName = Region::find($deal->region_id)?->name;
            $branchName = Branch::find($deal->branch_id)?->name;
            $admissionName = $deal?->name;

            return response()->json([
                'status' => false,
                'message' => __('Application already created by ' . allUsers()[$duplicate->created_by] . ' from ' . '“' . $existing_brand . '”' . '-' . '“' . $regionName . '”' . '-' . '“' . $branchName . '”' . ' for ' . '“' . $admissionName . '”' . ' in ' . allUniversities()[$duplicate->university_id] . '. You can still apply for this application in any other university.'),
            ], 409);
        }

        if (!empty($request->courses_id)) {
            $course = Course::find($request->courses_id);
            $courseName = $course ?
                "{$course->name} - {$course->campus} - {$course->intake_month} - {$course->intakeYear} ({$course->duration})"
                : null;
        } else {
            $courseName = $request->CoursesName ?? null;
        }
        $application->country_id = $request->country_id;
        $application->student_origin_country = $request->student_origin_country;
        $application->student_origin_city = $request->student_origin_city;
        $application->student_previous_university = $request->student_previous_university;
        $application->application_key = "{$userName}-{$passport_number}-{$university_name}";
        $application->deal_id = $application->deal_id;
        $application->university_id = $request->university;
        $application->course = $courseName;
        $application->stage_id = $request->status;
        $application->external_app_id = $request->application_key;
        $application->intake = $request->intake_month;
        $application->name = "{$deal->name}-{$courseName}-{$university_name}-{$request->application_key}";
        $application->created_by = Session::get('auth_type_id') ?? \Auth::id();
        $application->tag_ids = $request->tag_ids ?? '';
        $application->brand_id = $deal->brand_id;
        $application->region_id = $deal->region_id;
        $application->region_id = $deal->region_id;
        $application->assigned_to = $deal->assigned_to;
        $application->campus = $request->campus;
        $application->country_id = Country::where('country_code', $request?->countryId)->first()?->id;
        $application->intakeYear = $request->intakeYear;
        $application->course_id = $request->courses_id ?? "0";
        $application->course_id = $request->courses_id ?? "0";
        $application->save();

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Application Updated',
                    'message' => 'Fields updated successfully',
                    'changes' => $changes
                ]),
                'module_id' => $application->id,
                'module_type' => 'application',
                'notification_type' => 'Application Updated'
            ]);
        }

        // Update Deal stage
        $latestStage = DealApplication::where('deal_id', $application->deal_id)->orderByDesc('stage_id')->first();
        if ($latestStage) {
            $deal->stage_id = match (true) {
                in_array($latestStage->stage_id, [0]) => 0,
                in_array($latestStage->stage_id, [1, 2]) => 1,
                in_array($latestStage->stage_id, [3, 4]) => 2,
                in_array($latestStage->stage_id, [5, 6]) => 3,
                in_array($latestStage->stage_id, [7, 8]) => 4,
                in_array($latestStage->stage_id, [9, 10]) => 5,
                $latestStage->stage_id == 11 => 6,
                $latestStage->stage_id == 12 => 7,
                default => 0,
            };
        } else {
            $deal->stage_id = 0;
        }
        $deal->save();

        // Add stage history
        addLeadHistory([
            'stage_id' => $request->status,
            'type_id' => $application->id,
            'type' => 'application',
        ]);

        return response()->json([
            'status' => "success",
            'message' => 'Application successfully updated!',
        ]);
    }

    public function deleteApplication(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:deal_applications,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (!\Auth::user()->can('delete application')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Fetch application
        $dealApplication = DealApplication::find($request->id);

        if (!$dealApplication) {
            return response()->json([
                'status' => 'error',
                'message' => __('Application not found.')
            ], 404);
        }

        $dealId = $dealApplication->deal_id;

        // Delete application
        $dealApplication->delete();

        // Update deal stage
        $deal = Deal::find($dealId);
        if ($deal) {
            $latestApplication = DealApplication::where('deal_id', $dealId)
                ->orderBy('stage_id', 'desc')
                ->first();

            if ($latestApplication) {
                $stageId = (int) $latestApplication->stage_id;
                if ($stageId === 0) {
                    $deal->stage_id = 0;
                } elseif (in_array($stageId, [1, 2])) {
                    $deal->stage_id = 1;
                } elseif (in_array($stageId, [3, 4])) {
                    $deal->stage_id = 2;
                } elseif (in_array($stageId, [5, 6])) {
                    $deal->stage_id = 3;
                } elseif (in_array($stageId, [7, 8])) {
                    $deal->stage_id = 4;
                } elseif (in_array($stageId, [9, 10])) {
                    $deal->stage_id = 5;
                } elseif ($stageId === 11) {
                    $deal->stage_id = 6;
                } elseif ($stageId === 12) {
                    $deal->stage_id = 7;
                }
            } else {
                $deal->stage_id = 0;
            }

            $deal->save();
        }

        // Log activity
        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Application Deleted',
                'message' => 'Application deleted and deal stage updated.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'application',
            'notification_type' => 'Application Deleted'
        ];
        addLogActivity($logData);

        return response()->json([
            'status' => 'success',
            'message' => __('Application successfully deleted!')
        ], 200);
    }


    public function updateApplicationStage()
    {
        $application_id = (int)($_POST['application_id'] ?? 0);
        $stage_id = (int)($_POST['stage_id'] ?? 0);

        if (!$application_id || !$stage_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid application ID or stage ID',
            ], 200);
        }

        // Fetch the application
        $application = DealApplication::find($application_id);
        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found',
            ], 200);
        }

        $current_stage = $application->stage_id;

        $hasUncompletedTasks = \App\Models\DealTask::where([
            'related_to' => $application_id,
            'related_type' => 'application',
        ])->where('tasks_type', 'Quality')->latest()->first();
                $hasUncompletedTasksCompliance = \App\Models\DealTask::where([
            'related_to' => $application_id,
            'related_type' => 'application',
        ])->where('tasks_type', 'Compliance')->latest()->first();
        // ......
        $tasksStatusInvalid = isset($hasUncompletedTasks->tasks_type_status) && in_array($hasUncompletedTasks->tasks_type_status, ['2', '0']);
        $request_stage = explode(',', trim($application->request_stage ?? '', ','));
        $stages = [
            'initial' => [0, 1, 2, 3, 4, 5, 6],
            'final' => [7, 8, 9, 10, 11, 12]
        ];
        if (in_array($stage_id, [1, 6]) && $tasksStatusInvalid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot move to stages 1 or 6 with uncompleted tasks',
                ], 200);
        }
        $initial_stages = [0, 1, 2, 3, 4, 5, 6];
        $final_stages = [7, 8, 9, 10, 11, 12];
        if (in_array($stage_id, $initial_stages)) {
            if ($stage_id < 12 && $current_stage !== 12) {
                if (!in_array($current_stage, [2, 3, 4])) {
                    if ($current_stage > $stage_id) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Stage ID cannot be decreased',
                            ], 200);
                    }
                }
                $request_stage = explode(',', trim($application->request_stage ?? '', ','));
                if (!in_array(1, $request_stage) || $tasksStatusInvalid) {
                    if ($application->university_id != 7) {
                        if (!isset($hasUncompletedTasks->tasks_type_status)) {
                            $newStage = ApplicationStage::find($stage_id);
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Quality Check is mandatory before moving to stage ' . $newStage->name ?? '',
                            ], 200);
                        } else if (isset($hasUncompletedTasks->tasks_type_status) && $hasUncompletedTasks->tasks_type_status != '1') {
                            $newStage = ApplicationStage::find($stage_id);
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Quality Check is mandatory before moving to stage ' . $newStage->name ?? '',
                            ], 200);
                        }
                    }
                }
                // Update application stage
                $application->update([
                    'stage_id' => $stage_id,
                    'request_stage' => null,
                ]);
            } else {
                $request_stage = explode(',', trim($application->request_stage ?? '', ','));
                if (!in_array(1, $request_stage) || $tasksStatusInvalid) {
                    if ($application->university_id != 7) {
                        if (!isset($hasUncompletedTasks->tasks_type_status)) {
                            $newStage = ApplicationStage::find($stage_id);
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Quality Check is mandatory before moving to stage ' . $newStage->name ?? '',
                            ], 200);
                        } else if (isset($hasUncompletedTasks->tasks_type_status) && $hasUncompletedTasks->tasks_type_status != '1') {
                            $newStage = ApplicationStage::find($stage_id);
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Quality Check is mandatory before moving to stage ' . $newStage->name ?? '',
                            ], 200);
                        }
                    }
                }

                if (!empty($hasUncompletedTasksCompliance) && $hasUncompletedTasksCompliance->tasks_type == 'Quality') {
                    StageHistory::where('type_id', $application->id)->where('type', 'application')->whereIn('stage_id', ['2', '3', '4', '5', '6', '7', '8', '9', '10', '11'])->delete();
                }
                if (!empty($hasUncompletedTasksCompliance) && $hasUncompletedTasksCompliance->tasks_type_status != '1' && $hasUncompletedTasksCompliance->tasks_type_status != '2') {
                    $hasUncompletedTasksCompliance->tasks_type_status ='0';
                    $hasUncompletedTasksCompliance->stage_request ='';
                    $hasUncompletedTasksCompliance->save();
                    $application->request_stage = '';
                } elseif (!empty($hasUncompletedTasksCompliance) && $hasUncompletedTasksCompliance->tasks_type == 'Compliance') {
                    $application->request_stage = '1,';
                    StageHistory::where('type_id', $application->id)->where('type', 'application')->whereIn('stage_id', ['7', '8', '9', '10', '11'])->delete();
                    $hasUncompletedTasksCompliance->tasks_type_status ='0';
                    $hasUncompletedTasksCompliance->stage_request ='';
                    $hasUncompletedTasksCompliance->save();
                }
                $application->save();
                $application->update(['stage_id' => $stage_id]);
            }
        } elseif (in_array($stage_id, $final_stages)) {
            if ($stage_id < 12 && $current_stage !== 12) {
                $request_stage = explode(',', trim($application->request_stage ?? '', ','));
                if (!in_array(6, $request_stage) || $tasksStatusInvalid) {
                    $newStage = ApplicationStage::find($stage_id);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Compliance Checks is mandatory before moving to stage " . $newStage->name ?? '',
                    ], 200);
                }
                if ($current_stage >= $stage_id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Stage ID cannot be decreased.",
                    ], 200);
                }
                if ($application->university_id == 380) {
                        $application->update(['stage_id' => $stage_id]);
                }else{
                    if (($stage_id < 11 && $stage_id === $current_stage + 1) || ($stage_id >= 11 && $current_stage >= 10)) {
                        $application->update(['stage_id' => $stage_id]);
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Stage " . ($current_stage + 1) . " is required before moving to stage $stage_id.",
                        ], 200);
                    }
                }

            } else {
                if ($current_stage != 11) {
                    if (in_array($stage_id, [7, 8, 9, 10, 11])) {
                        if ($current_stage > $stage_id) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Stage ID cannot be decreased.',
                            ], 200);
                        }
                    }
                    if (!empty($hasUncompletedTasks) && $hasUncompletedTasks->tasks_type == 'Quality') {
                        StageHistory::where('type_id', $application->id)->where('type', 'application')->whereIn('stage_id', ['2', '3', '4', '5', '6', '7', '8', '9', '10', '11'])->delete();
                    }
                    if (!empty($hasUncompletedTasks) && $hasUncompletedTasks->tasks_type_status != '1' && $hasUncompletedTasks->tasks_type_status != '2') {
                        $hasUncompletedTasks->tasks_type_status ='0';
                        $hasUncompletedTasks->stage_request ='';
                        $hasUncompletedTasks->save();
                        $application->request_stage = '';
                    } elseif (!empty($hasUncompletedTasks) && $hasUncompletedTasks->tasks_type == 'Compliance') {
                        $application->request_stage = '1,';
                        StageHistory::where('type_id', $application->id)->where('type', 'application')->whereIn('stage_id', ['7', '8', '9', '10', '11'])->delete();
                        $hasUncompletedTasks->tasks_type_status ='0';
                        $hasUncompletedTasks->stage_request ='';
                        $hasUncompletedTasks->save();
                    }
                    $application->save();
                    $application->update(['stage_id' => $stage_id]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Enrolled application has been completed and is now locked in the system.',
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid stage transition',
            ], 200);
        }
        // Update Deal's latest stage
        $deal = Deal::find($application->deal_id);
        if ($deal) {
            $latestStage = DealApplication::where('deal_id', $application->deal_id)
                ->orderBy('stage_id', 'desc')
                ->first();

            if (!empty($latestStage)) {
                if ($latestStage->stage_id == '0') {
                    $deal->stage_id = 0;
                } elseif ($latestStage->stage_id == '1' || $latestStage->stage_id == '2') {
                    $deal->stage_id = 1;
                } elseif ($latestStage->stage_id == '3' || $latestStage->stage_id == '4') {

                    $deal->stage_id = 2;
                } elseif ($latestStage->stage_id == '5' || $latestStage->stage_id == '6') {

                    $deal->stage_id = 3;
                } elseif ($latestStage->stage_id == '7' || $latestStage->stage_id == '8') {

                    $deal->stage_id = 4;
                } elseif ($latestStage->stage_id == '9' || $latestStage->stage_id == '10') {

                    $deal->stage_id = 5;
                } elseif ($latestStage->stage_id == '11') {

                    $deal->stage_id = 6;
                } elseif ($latestStage->stage_id == '12') {

                    $deal->stage_id = 7;
                }

                $deal->save();
            } else {
                $deal->stage_id = 0;
                $deal->save();
            }
        }

        // Add Stage History
        addLeadHistory([
            'stage_id' => $stage_id,
            'type_id' => $application_id,
            'type' => 'application',
        ]);

        // Log activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Stage Updated',
                'message' => 'Application stage updated successfully.',
            ]),
            'module_id' => $application_id,
            'module_type' => 'application',
            'notification_type' => 'application stage update',
        ]);
         return response()->json([
                'status' => 'success',
                'message' => 'Application stage updated successfully',
            ], 200);
    }

    public function applicationAppliedStage(Request $request)
    {
        // ✅ Validate the request
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer|exists:deal_applications,id',
            'stage_id' => 'required|integer|exists:application_stages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $applicationId = (int) $request->application_id;
        $stageId = (int) $request->stage_id;

        $application = DealApplication::with(['deal.client', 'university', 'course'])->find($applicationId);
        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found.',
            ], 404);
        }

        $currentStage = $application->stage_id;

        $hasQualityTask = DealTask::where([
            'related_to' => $applicationId,
            'related_type' => 'application',
            'tasks_type' => 'Quality',
        ])->latest()->first();

        $hasComplianceTask = DealTask::where([
            'related_to' => $applicationId,
            'related_type' => 'application',
            'tasks_type' => 'Compliance',
        ])->latest()->first();

        $qualityInvalid = isset($hasQualityTask->tasks_type_status) && !in_array($hasQualityTask->tasks_type_status, ['2', '0']);
        $complianceValid = isset($hasComplianceTask->tasks_type_status) && in_array($hasComplianceTask->tasks_type_status, ['2', '0']);

        $requestStages = explode(',', trim($application->request_stage ?? '', ','));

        $initialStages = [0, 1, 2, 3, 4, 5, 6];
        $finalStages = [7, 8, 9, 10, 11, 12];

        // ✅ Check permission
        if (!auth()->user()->can('edit application')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

        // ✅ Stage transition logic
        if (in_array($stageId, $initialStages)) {
            if (!in_array($currentStage, [1, 5])) {
                if ($currentStage > $stageId || !in_array(1, $requestStages) || $qualityInvalid) {
                    if ($hasQualityTask && $hasQualityTask->tasks_type_status !== '1') {
                        $stageName = ApplicationStage::find($stageId)->name ?? '';
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Quality Check is mandatory before moving to stage ' . $stageName,
                        ]);
                    }
                }
            }

            if (!in_array($currentStage, [5])) {
                if ($currentStage > $stageId || !in_array(1, $requestStages) || $complianceValid) {
                    $stageName = ApplicationStage::find($stageId)->name ?? '';
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Deposit is mandatory before moving to stage ' . $stageName,
                    ]);
                }

                $stageName = ApplicationStage::find($stageId)->name ?? '';
                return response()->json([
                    'status' => 'error',
                    'message' => 'Deposit is mandatory before moving to stage ' . $stageName,
                ]);
            }
        } elseif (in_array($stageId, $finalStages)) {
            if ($currentStage >= $stageId || !in_array(6, $requestStages) || $complianceValid) {
                $stageName = ApplicationStage::find($stageId)->name ?? '';
                return response()->json([
                    'status' => 'error',
                    'message' => 'Compliance Checks are mandatory before moving to stage ' . $stageName,
                ]);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid stage transition.',
            ]);
        }

        // ✅ Collect required data
        $universities = University::select('id', 'name')->get();
        $statuses = ['Pending', 'Approved', 'Rejected'];
        $dealPassport = $application->deal->client ?? null;
        $stages = ApplicationStage::select('id', 'name')->get();
        $campuss = Course::where('id', $application->course_id)
            ->where('university_id', $application->university_id)
            ->whereNotNull('campus')
            ->pluck('campus')
            ->flatMap(fn($c) => array_map('trim', explode(',', $c)))
            ->toArray();

        $course = Course::where('university_id', $application->university_id)->get();

        // Intake Month logic
        $intake_month = [];
        $IntakeMonths = [];
        $university = $application->university;

        if ($university) {
            $monthsMap = [
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
                'DEC' => 'December'
            ];

            if ($university->status == '0') {
                $intake_month = array_map('trim', explode(',', $university->intake_months ?? ''));
                $intake_month = array_map(fn($m) => $monthsMap[$m] ?? $m, $intake_month);
            } elseif ($university->status == '1') {
                $intake_month = Course::where('id', $application->course_id)
                    ->pluck('intake_month')
                    ->flatMap(fn($val) => array_map('trim', explode(',', $val)))
                    ->toArray();
            }
        }

        // ✅ Return JSON API response
        return response()->json([
            'status' => 'success',
            'message' => 'Application can be moved to the selected stage.',
            'data' => [
                'stageId' => $stageId,
                'deal' => $application->deal,
                'university' => $university,
                'intake_month' => $intake_month,
                'campuss' => $campuss,
                'course' => $course,
                'IntakeMonths' => $IntakeMonths,
                'application' => $application,
                'universities' => $universities,
                'statuses' => $statuses,
                'deal_passport' => $dealPassport,
                'stages' => $stages,
            ]
        ]);
    }

    public function saveApplicationDepositRequest(Request $request)
    {
        // ✅ Initial validation
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:deal_applications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $id = $request->id;

        // 🔐 Permission check
        if (!auth()->user()->can('edit application')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // ✅ Fetch application
        $application = DealApplication::where('id', $id)->first();

        // ✅ Dynamic stage-specific validation
        $rules = [];
        if ($request->stage_id == 6) {
            if (in_array($request->english_test, ['IELTS', 'OIDI (ELLT)', 'PTE', 'TOEFL'])) {
                $rules = [
                    'disability' => 'required',
                    'english_test' => 'required',
                    'drive_link' => 'required',
                    'Mode_of_Verification' => 'required',
                    'Mode_of_Payment' => 'required',
                    'username' => 'required',
                    'password' => 'required',
                    'email' => ['required', 'email', Rule::unique('users')->ignore($request->clientUserID)],
                    'CAS_Documents_Checklist' => 'required|array',
                ];
            } else {
                $rules = [
                    'Mode_of_Verification' => 'required',
                    'Mode_of_Payment' => 'required',
                    'disability' => 'required',
                    'english_test' => 'required',
                    'drive_link' => 'required',
                    'email' => ['required', 'email', Rule::unique('users')->ignore($request->clientUserID)],
                    'CAS_Documents_Checklist' => 'required|array',
                ];
            }
        } else {
            $rules = [
                'destination' => 'required',
                'Source' => 'required',
                'institution' => 'required',
                'Date_of_deposit' => 'required|date',
                'Amount_of_deposit' => 'required',
                'Mode_of_payment' => 'required',
                'Folder_link' => 'required',
                'Mode_of_verification' => 'required',
                'Declaration' => 'required|array',
            ];
            if (isset($request->DealTask)) {
                $rules['Reasons_for_resubmission'] = 'required';
            }
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // ✅ Update client info
        $client = User::find($request->clientUserID);
        if ($client) {
            $client->email = $request->email;
            $client->phone = $request->mobile_number;
            $client->address = $request->address;
            $client->save();
        }

        // ✅ Update application request_stage
        $currentStages = array_filter(explode(',', trim($application->request_stage, ',')));
        $requestedStage = (string)$request->stage_id;

        if ($requestedStage === '4') {
            if (in_array('6', $currentStages)) {
                $currentStages = array_diff($currentStages, ['5']);
            }
            $currentStages[] = '4';
        } elseif ($requestedStage === '6') {
            if (in_array('4', $currentStages) && !in_array('6', $currentStages)) {
                $currentStages[] = '6';
            } else {
                $currentStages[] = '6';
            }
        }

        $currentStages = array_unique($currentStages);
        sort($currentStages);
        $application->request_stage = ',' . implode(',', $currentStages);
        $application->save();

        // ✅ Save meta data
        foreach ($request->except(['_token', '_method', 'submit']) as $key => $value) {
            DB::table('meta')->updateOrInsert(
                [
                    'created_by' => Auth::id(),
                    'parent_id' => $id,
                    'stage_id' => $request->stage_id,
                    'meta_key' => $key
                ],
                [
                    'meta_value' => is_array($value) ? json_encode($value) : $value
                ]
            );
        }

        // ✅ Create or update DealTask
        $dealTask = DealTask::where([
            ['related_to', '=', $id],
            ['related_type', '=', 'application'],
            ['tasks_type', '=', 'Compliance']
        ])->first();

        $passport_number = optional($client)->passport_number ?? 'APC' . $id;
        $taskName = $passport_number . ($request->stage_id == 1 ? ' Quality Checks' : ' Compliance Checks');
        $taskType = $request->stage_id == 1 ? 'Quality' : 'Compliance';

        if (!$dealTask) {
            $dealTask = new DealTask();
            $dealTask->related_to = $id;
            $dealTask->related_type = 'application';
            $dealTask->created_by = Auth::id();
            $dealTask->deal_id = $id;
            $dealTask->branch_id = 262;
            $dealTask->region_id = 56;
            $dealTask->brand_id = 3751;
            $dealTask->assigned_to = 3751;
            $dealTask->due_date = now()->toDateString();
            $dealTask->start_date = now()->toDateString();
            $dealTask->date = now()->toDateString();
            $dealTask->remainder_date = now()->toDateString();
            $dealTask->status = 0;
            $dealTask->description = '';
            $dealTask->visibility = 'Public';
            $dealTask->priority = 1;
            $dealTask->time = now()->toTimeString();
            $dealTask->stage_request = $request->stage_id;
            $dealTask->name = $taskName;
            $dealTask->tasks_type = $taskType;
            $dealTask->tasks_type_status = '0';


            $dealTask->save();
        }

        // ✅ Add activity log
        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Application Updated',
                'message' => 'Application stage request and deposit details were updated successfully.'
            ]),
            'module_id' => $id,
            'module_type' => 'application',
            'notification_type' => 'Application Updated'
        ];
        addLogActivity($logData);

        return response()->json([
            'status' => 'success',
            'app_id' => $id,
            'message' => 'Application updated successfully!'
        ]);
    }
    public function DeleteApplicationNotes(Request $request)
    {
        $rules = [
            'id' => 'required|integer|min:1',
        ];
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }
        $discussions = ApplicationNote::find($request->id);
        if (! empty($discussions)) {
            $discussions->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Application Note deleted!'),
        ], 201);
    }
    public function applicationNotesStore(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:deal_applications,id', // application ID
            'description' => 'required',
            'note_id' => 'nullable|exists:application_notes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ]);
        }

        $applicationId = $request->id;
        $authId = Session::get('auth_type_id') ?? \Auth::id();

        if (!empty($request->note_id)) {
            // Update existing note
            $note = ApplicationNote::where('id', $request->note_id)->first();
            $note->description = $request->description;
            $note->update();

            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Application Notes Updated',
                    'message' => 'Application notes updated successfully',
                ]),
                'module_id' => $applicationId,
                'module_type' => 'application',
                'notification_type' => 'Application Notes Updated',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Notes updated successfully'),
                'note' => $note,
            ]);
        }

        // Create new note
        $note = new ApplicationNote();
        $note->description = $request->description;
        $note->created_by = $authId;
        $note->application_id = $applicationId;
        $note->save();

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Notes Created',
                'message' => 'Application notes created successfully',
            ]),
            'module_id' => $applicationId,
            'module_type' => 'application',
            'notification_type' => 'Application Notes Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Notes added successfully'),
            'note' => $note,
        ]);
    }

    public function getApplicationNotes(Request $request)
    {
        // ✅ Validate required input
        $request->validate([
            'application_id' => 'required|exists:deal_applications,id',
        ]);

        $user = Auth::user();

        // ✅ Permission check
        if (!$user->can('view application') && $user->type !== 'super admin') {
            return response()->json([
                'status' => false,
                'message' => 'Permission Denied.',
            ], 403);
        }

        // ✅ Fetch and format notes
        $notes = ApplicationNote::where('application_id', $request->application_id)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->map(function ($note) {
                return [
                    'id'        => $note->id,
                    'text'      => htmlspecialchars_decode($note->description),
                    'author'    => $note?->author?->name,
                    'time'      => $note->created_at->diffForHumans(),
                    'pinned'    => false, // default as required
                    'timestamp' => $note->created_at->toISOString(),
                ];
            });

        // ✅ Return structured response
        return response()->json([
            'status'  => true,
            'message' => 'Application notes fetched successfully.',
            'data'    => $notes,
        ]);
    }

    public function application_request_save_deposite(Request $request)
    {
        $id=$request->appid;
        $application = DealApplication::with('university')->find($id);

        $currentStages = array_filter(explode(',', trim($application->request_stage, ',')));
        $requestedStage = (string) $request->stage_id;

        if ($requestedStage === '4') {
            if (in_array('6', $currentStages)) {
                $currentStages = array_diff($currentStages, ['5']);
            }
            $currentStages[] = '4';
        } elseif ($requestedStage === '6') {
            if (in_array('4', $currentStages)) {
                if (!in_array('6', $currentStages)) {
                    $currentStages[] = '6';
                }
            } else {
                $currentStages[] = '6';
            }
        }

        $application->request_stage = ',' . implode(',', array_unique($currentStages));
        $application->save();

        $ApplicationStage_new = optional(ApplicationStage::find($request->stage_id))->name;


        $client = \App\Models\User::join('client_deals', 'client_deals.client_id', '=', 'users.id')
            ->where('client_deals.deal_id', $application->deal_id)
            ->first();

        $dealTask = \App\Models\DealTask::where('related_to', $id)
            ->where('related_type', 'application')
            ->where('tasks_type', 'Compliance')
            ->first();
        if (!empty($dealTask)) {
            // dd(2);
            $dealTask->deal_id = $id;
            $dealTask->related_to = $id;
            $dealTask->related_type = 'application';
            $dealTask->branch_id = 262;
            $dealTask->region_id = 56;
            $dealTask->brand_id = 3751;
            $dealTask->created_by = \Auth::id();
            $dealTask->assigned_to = 3751;
            $dealTask->due_date = \Carbon\Carbon::now()->toDateString();
            $dealTask->start_date = \Carbon\Carbon::now()->toDateString();
            $dealTask->date = \Carbon\Carbon::now()->toDateString();
            $dealTask->status = 0;
            $dealTask->remainder_date = \Carbon\Carbon::now()->toDateString();
            $dealTask->description = '';
            $dealTask->visibility = '';
            $dealTask->priority = 1;
            $dealTask->tasks_type_status = '0';
            $dealTask->time = \Carbon\Carbon::now()->toTimeString();
            $dealTask->stage_request = $request->stage_id;
            if ($request->stage_id == '1') {
                if ($client) {
                    $passport_number = $client->passport_number ?? '';
                    $dealTask->name = $passport_number . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                } else {
                    $dealTask->name = 'APC' . $application->id . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                }
                $dealTask->stage_request = $request->stage_id;
                $dealTask->tasks_type = 'Quality';
            } else {
                if ($client) {
                    $passport_number = $client->passport_number ?? '';
                    $dealTask->name = $passport_number . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                } else {
                    $dealTask->name = 'APC' . $application->id . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                }
                $dealTask->stage_request = $request->stage_id;
                $dealTask->tasks_type = 'Compliance';
            }
            $dealTask->save();
        } else {
            $dealTask = new \App\Models\DealTask();
            $dealTask->deal_id = $id;
            $dealTask->related_to = $id;
            $dealTask->related_type = 'application';
            $dealTask->branch_id = 262;
            $dealTask->region_id = 56;
            $dealTask->brand_id = 3751;
            $dealTask->created_by = \Auth::id();
            $dealTask->assigned_to = 3751;
            $dealTask->due_date = \Carbon\Carbon::now()->toDateString();
            $dealTask->start_date = \Carbon\Carbon::now()->toDateString();
            $dealTask->date = \Carbon\Carbon::now()->toDateString();
            $dealTask->status = 0;
            $dealTask->remainder_date = \Carbon\Carbon::now()->toDateString();
            $dealTask->description = '';
            $dealTask->visibility = '';
            $dealTask->priority = 1;
            $dealTask->time = \Carbon\Carbon::now()->toTimeString();
            $dealTask->stage_request = $request->stage_id;
            if ($request->stage_id == '1') {
                if ($client) {
                    $passport_number = $client->passport_number ?? '';
                    $dealTask->name = $passport_number . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                } else {
                    $dealTask->name = 'APC' . $application->id . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                }
                $dealTask->stage_request = $request->stage_id;
                $dealTask->tasks_type = 'Quality';
            } else {
                if ($client) {
                    $passport_number = $client->passport_number ?? '';
                    $dealTask->name = $passport_number . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                } else {
                    $dealTask->name = 'APC' . $application->id . ' ' . $application->university->name ?? ''; // Fixed the concatenation
                }
                $dealTask->stage_request = $request->stage_id;
                $dealTask->tasks_type = 'Compliance';
            }
            $dealTask->save();
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Application updated successfully!',
        ], 200);
    }

    public function addApplicationTags(Request $request)
    {

      // Validate Input
        $validator = \Validator::make($request->all(), [
            'selectedIds' => 'required|string', // Expecting comma-separated IDs
            'tagid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }
        $ids = explode(',', $request->selectedIds);
        $Leads = DealApplication::whereIn('id', $ids)->get();
        if (!empty($ids) && !empty($Leads) && $Leads->count() > 0) {
            foreach ($Leads as $Lead) {
                $Lead->tag_ids = $Lead->tag_ids ? $Lead->tag_ids . ',' . $request->tagid : $request->tagid;
                $Lead->save();

                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $Lead->name. ' Tag Updated for application',
                        'message' => $Lead->name. " Tag Updated for application",
                    ]),
                    'module_id' => $Lead->id,
                    'module_type' => 'application',
                    'notification_type' => 'Tag Updated',
                ]);
            }

            return json_encode([
                'status' => 'success',
                'msg' => 'Tag added successfully'
            ]);
        }
    }
}
