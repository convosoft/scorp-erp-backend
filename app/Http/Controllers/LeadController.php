<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\ClientDeal;
use App\Models\Country;
use App\Models\Deal;
use App\Models\DealCall;
use App\Models\DealDiscussion;
use App\Models\DealEmail;
use App\Models\DealFile;
use App\Models\DealNote;
use App\Models\DealTask;
use App\Models\EmailMarkittingFileEmail;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\Label;
use App\Models\Lead;
use App\Models\LeadActivityLog;
use App\Models\LeadCall;
use App\Models\LeadDiscussion;
use App\Models\LeadEmail;
use App\Models\LeadFile;
use App\Models\LeadNote;
use App\Models\LeadStage;
use App\Models\LeadTag;
use App\Models\LeadView;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\SavedFilter;
use App\Models\Stage;
use App\Models\StageHistory;
use App\Models\User;
use App\Models\UserDeal;
use App\Models\UserLead;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Session;
use Spatie\Permission\Models\Role;
use SplFileObject;
use Carbon\Carbon;

class LeadController extends Controller
{
    public function getLeads(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'name' => 'nullable|string',
            'view' => 'required|string',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer|exists:lead_stages,id',
            'users' => 'nullable|array',
            'lead_assigned_user' => 'sometimes|nullable',
            'created_by' => 'sometimes|nullable',
            'created_at_from' => 'nullable|date',
            'created_at_to' => 'nullable|date',
            'tag' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $view = $request->input('view', 'list'); // list | kanban

        $usr = \Auth::user();

        // Default pagination settings
        $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50)); // Default 50 records per page
        $page = $request->input('page', 1); // Default page 1

        $leadsQuery = Lead::select(
            'leads.id',
            'leads.name',
            'leads.city',
            'leads.brand_id',
            'leads.email',
            'leads.branch_id',
            'leads.phone',
            'leads.created_by',
            'leads.user_id',
            'leads.stage_id',
            'leads.created_at',
            'leads.tag_ids'
        )
            ->with('assignto:id,name')
            ->with('brand:id,name')
            ->with('stage')
            ->with('branch:id,name')
            ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')
             // Join only CURRENT stage history of the lead
            ->leftJoin('stage_histories as sh', function ($join) {
                $join->on('sh.type_id', '=', 'leads.id')
                    ->where('sh.type', '=', 'lead')
                    ->whereColumn('sh.stage_id', 'leads.stage_id');
            });

        // Apply Filters
        if ($request->filled('Assigned')) {
            $leadsQuery->whereNotNull('leads.user_id');
        }

        if ($request->filled('Unassigned')) {
            $leadsQuery->whereNull('leads.user_id');
        }

        if ($request->filled('brand')) {
            $leadsQuery->where('leads.brand_id', $request->brand);
        }

        if ($request->filled('region_id')) {
            $leadsQuery->where('leads.region_id', $request->region_id);
        }

        if ($request->filled('branch_id')) {
            $leadsQuery->where('leads.branch_id', $request->branch_id);
        }

        if ($request->filled('stage_id')) {
            $leadsQuery->where('leads.stage_id', $request->stage_id);
        }

        if ($request->filled('tag')) {
            $leadsQuery->whereRaw('FIND_IN_SET(?, leads.tag_ids)', [$request->tag]);
        }

        if ($request->filled('lead_assigned_user')) {
            $leadsQuery->where('leads.user_id', $request->lead_assigned_user);
        }
        if ($request->filled('created_by')) {
            $leadsQuery->where('leads.created_by', $request->created_by);
        }

        if ($request->filled('created_at_to')) {
            $leadsQuery->whereDate('leads.created_at', '<=', $request->created_at_to);
        }

        if ($request->filled('created_at_from')) {
            $leadsQuery->whereDate('leads.created_at', '>=', $request->created_at_from);
        }

        if ($request->filled('days_at_stage')) {

            $days = $request->days_at_stage;

            if ($days === '30+') {

                // Greater than 30 days
                $leadsQuery->whereDate(
                    'sh.created_at',
                    '<=',
                    Carbon::now()->subDays(30)
                );

            } else {

                // Exactly 3, 5, 15, 30 days (range-based)
                $from = Carbon::now()->subDays((int) $days);
                $to   = Carbon::now()->subDays(((int) $days) - 1);

                $leadsQuery->whereBetween('sh.created_at', [$from, $to]);
            }
        }


        // User Permissions Filtering
        $userType = $usr->type;
        if ($userType === 'company') {
            $leadsQuery->where('leads.brand_id', $usr->id);
        } elseif ($userType === 'Region Manager' && $usr->region_id) {
            $leadsQuery->where('leads.region_id', $usr->region_id);
        } elseif ($userType === 'Branch Manager' && $usr->branch_id) {
            $leadsQuery->where('leads.branch_id', $usr->branch_id);
        } elseif ($userType === 'Agent') {
            $leadsQuery->where('leads.agent_id', $usr->agent_id);
        }

        // Apply Search Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $leadsQuery->where(function ($query) use ($search) {
                $query->where('leads.name', 'like', "%$search%")
                    ->orWhere('leads.email', 'like', "%$search%")
                    ->orWhere('leads.phone', 'like', "%$search%");
            });
        }

        // Apply Pagination........................
        // Apply Pagination........................
        // Apply Pagination........................
        // Apply Pagination........................
        // Apply Pagination........................
        // Apply Pagination.......................

        if ($request->input('download_csv')) {
            $download_csv = $leadsQuery->where('is_converted', 0)->get();

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="leads_'.time().'.csv"',
                'Cache-Control' => 'no-store, no-cache',
                'Pragma' => 'no-cache',
            ];

            $callback = function () use ($download_csv) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Name', 'Email', 'Brand', 'Branch', 'AssignTo']);

                foreach ($download_csv as $download) {
                    fputcsv($file, [
                        $download->id,
                        $download->name,
                        $download->email,
                        $download?->brand?->name,
                        $download?->branch?->name,
                        $download?->assignto?->name,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }
        if ($view === 'kanban') {

            $KANBAN_PER_PAGE = 1000;
            $page = $request->input('page', 1);

            // Paginate leads
            $leads = $leadsQuery
                ->where('leads.is_converted', 0)
                ->orderBy('leads.created_at', 'desc')
                ->paginate($KANBAN_PER_PAGE, ['*'], 'page', $page);

            // Get stages
            $stages = DB::table('lead_stages')->select('id', 'name')->get();

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

                $stageLeads = $leads->getCollection()
                    ->where('stage_id', $stage->id)
                    ->values();

                $kanban[] = [
                    'stage_id' => $stage->id,
                    'title' => $stage->name,
                    'count' => $stageLeads->count(),
                    'color' => $colors[$stage->id][0] ?? '#000',
                    'bgColor' => $colors[$stage->id][1] ?? '#fff',
                    'leads' => $stageLeads->map(fn ($lead) => [
                        'id' => $lead->id,
                        'name' => $lead->name,
                        'phone' => $lead->phone,
                        'email' => $lead->email,
                        'city' => $lead->city,
                        'leadAge' =>$lead->created_at->diffForHumans(),
                    ]),
                ];
            }

            return response()->json([
                'status' => 'success',
                'view' => 'kanban',
                'data' => $kanban,
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total_records' => $leads->total(),
                'per_page' => $KANBAN_PER_PAGE,
            ]);
        }

        $leads = $leadsQuery
            ->orderBy('leads.created_at', 'desc')->where('is_converted', 0)
            ->paginate($perPage, ['*'], 'page', $page);

        $leadsWithTags = $leads->getCollection()->map(function ($lead) {
            $lead->tags = LeadTag::whereRaw('FIND_IN_SET(id, ?)', [$lead->tag_ids])->get();

            return $lead;
        });

        return response()->json([
            'status' => 'success',
            'data' => $leadsWithTags,
            'current_page' => $leads->currentPage(),
            'last_page' => $leads->lastPage(),
            'total_records' => $leads->total(),
            'per_page' => $leads->perPage(),
        ], 200);
    }

    public function getLeadsByView(Request $request)
{
    // ... validation stays same ...

        $validator = Validator::make($request->all(), [

            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'name' => 'nullable|string',
            'view' => 'required|string',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer|exists:lead_stages,id',
            'users' => 'nullable|array',
            'lead_assigned_user' => 'sometimes|nullable',
            'created_by' => 'sometimes|nullable',
            'created_at_from' => 'nullable|date',
            'created_at_to' => 'nullable|date',
            'tag' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

    $view = $request->input('view', 'list');
    $usr = \Auth::user();
    $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
    $page = $request->input('page', 1);

    // ✅ Use the view instead of complex joins
    $leadsQuery = LeadView::query();

    // ✅ All filters become simple where clauses
    if ($request->filled('Assigned')) {
        $leadsQuery->whereNotNull('user_id');
    }

    if ($request->filled('Unassigned')) {
        $leadsQuery->whereNull('user_id');
    }

    if ($request->filled('brand')) {
        $leadsQuery->where('brand_id', $request->brand);
    }

    if ($request->filled('region_id')) {
        $leadsQuery->where('region_id', $request->region_id);
    }

    if ($request->filled('branch_id')) {
        $leadsQuery->where('branch_id', $request->branch_id);
    }

    if ($request->filled('stage_id')) {
        $leadsQuery->where('stage_id', $request->stage_id);
    }

    if ($request->filled('tag')) {
        $leadsQuery->whereRaw('FIND_IN_SET(?, tag_ids_list)', [$request->tag]);
    }

    if ($request->filled('lead_assigned_user')) {
        $leadsQuery->where('user_id', $request->lead_assigned_user);
    }

    if ($request->filled('created_by')) {
        $leadsQuery->where('created_by', $request->created_by);
    }

    if ($request->filled('created_at_from')) {
        $leadsQuery->whereDate('created_at', '>=', $request->created_at_from);
    }

    if ($request->filled('created_at_to')) {
        $leadsQuery->whereDate('created_at', '<=', $request->created_at_to);
    }

    // ✅ Days at stage filter - now super simple!
    if ($request->filled('days_at_stage')) {
        $days = $request->days_at_stage;

        if ($days === '30+') {
            $leadsQuery->where('days_at_stage', '>=', 30);
        } else {
            $leadsQuery->whereBetween('days_at_stage', [(int)$days, (int)$days]);
        }
    }

    if ($request->filled('fetcttype')) {

        if($request->fetcttype=='yourleads'){
            $leadsQuery->where('created_by', '=', $usr->id);
        }

        if($request->fetcttype=='assigntome'){
            $leadsQuery->where('user_id', '=', $usr->id);
        }




    }

        if ($request->fetcttype === 'agentleads') {
                $leadsQuery->whereNotNull('agent_id');
            } else {
                $leadsQuery->whereNull('agent_id');
            }

    // User permissions
    $userType = $usr->type;
    if ($userType === 'company') {
        $leadsQuery->where('brand_id', $usr->id);
    } elseif ($userType === 'Region Manager' && $usr->region_id) {
        $leadsQuery->where('region_id', $usr->region_id);
    } elseif ($userType === 'Branch Manager' && $usr->branch_id) {
        $leadsQuery->where('branch_id', $usr->branch_id);
    } elseif ($userType === 'Agent') {
        $leadsQuery->where('agent_id', $usr->agent_id);
    }

    // Search
    if ($request->filled('search')) {
        $search = $request->input('search');
        $leadsQuery->where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%");
        });
    }

    // ✅ CSV Export - No joins needed!
    if ($request->input('download_csv')) {
        $download_csv = $leadsQuery->where('is_converted', 0)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_'.time().'.csv"',
        ];

        $callback = function () use ($download_csv) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Email', 'Brand', 'Branch', 'AssignTo', 'Stage', 'Days at Stage']);

            foreach ($download_csv as $lead) {
                fputcsv($file, [
                    $lead->id,
                    $lead->name,
                    $lead->email,
                    $lead->brand_name,        // ✅ Already in view
                    $lead->branch_name,       // ✅ Already in view
                    $lead->assigned_to_name,  // ✅ Already in view
                    $lead->stage_name,        // ✅ Already in view
                    $lead->days_at_stage,     // ✅ Already calculated
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ✅ Kanban View
    if ($view === 'kanban') {
        $KANBAN_PER_PAGE = 1000;

        $leads = $leadsQuery
            ->where('is_converted', 0)
            ->orderBy('created_at', 'desc')
            ->limit($KANBAN_PER_PAGE)
            ->get();

        $stages = DB::table('lead_stages')->select('id', 'name')->get();

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
            $stageLeads = $leads->where('stage_id', $stage->id)->values();

            $kanban[] = [
                'stage_id' => $stage->id,
                'title' => $stage->name,
                'count' => $stageLeads->count(),
                'color' => $colors[$stage->id][0] ?? '#000',
                'bgColor' => $colors[$stage->id][1] ?? '#fff',
                'leads' => $stageLeads->map(fn ($lead) => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                    'city' => $lead->city,
                    'days_at_stage' => $lead->days_at_stage, // ✅ Pre-calculated
                    'assigned_to' => $lead->assigned_to_name,
                ]),
            ];
        }

        return response()->json([
            'status' => 'success',
            'view' => 'kanban',
            'data' => $kanban,
            'total_records' => $leads->count(),
        ]);
    }

    // ✅ List View - Simple pagination
    $leads = $leadsQuery
        ->where('is_converted', 0)
        ->orderBy('created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);

    return response()->json([
        'status' => 'success',
        'data' => $leads->items(), // ✅ Everything already loaded!
        'current_page' => $leads->currentPage(),
        'last_page' => $leads->lastPage(),
        'total_records' => $leads->total(),
        'per_page' => $leads->perPage(),
    ], 200);
}

    public function saveLead(Request $request)
    {
        $user = \Auth::user();

        // dd($user->can('create lead'));

        // // Check Permissions
        // if (!$user->can('create lead')) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission Denied.'),
        //     ], 200);
        // }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'lead_stage' => 'required',
            'brand_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'lead_branch' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
            'lead_phone' => 'nullable',
            'lead_email' => 'required',
            'lead_country' => 'nullable',
            'lead_city' => 'nullable',
            'lead_state' => 'nullable',
            'lead_postal_code' => 'nullable',
            'lead_street' => 'nullable',
            // 'lead_last_education' => 'required',
            // 'lead_cgpa_percentage' => 'required',
            // 'lead_passing_year' => 'required',
            // 'lead_language_test' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Pipeline & Stage Setup
        $pipeline = $user->default_pipeline
            ? Pipeline::find($user->default_pipeline)
            : Pipeline::first();

        $stage = LeadStage::where('pipeline_id', $pipeline->id)
            ->orderBy('order', 'asc')
            ->first();

        if (! $stage) {
            return response()->json([
                'status' => 'error',
                'message' => __('Please Create Stage for This Pipeline.'),
            ], 422);
        }

        // Check for Duplicate Lead
        $leadExist = Lead::where('email', $request->lead_email)
            ->where('brand_id', $request->brand_id)
            ->where('region_id', $request->region_id)
            ->where('branch_id', $request->lead_branch)
            ->first();

        if ($leadExist) {
            return response()->json([
                'status' => 'error',
                'message' => __('Lead already exists.'),
                'lead_id' => $leadExist->id,
            ], 422);
        }

        // Create New Lead
        $lead = new Lead;
        $lead->title = $request->lead_prefix ?? '';
        $lead->name = $request->name;
        $lead->email = $request->lead_email;
        $lead->phone = $request->lead_phone;
        $lead->mobile_phone = $request->lead_phone;
        $lead->branch_id = $request->lead_branch;
        $lead->brand_id = $request->brand_id;
        $lead->region_id = $request->region_id;
        $lead->organization_id = is_string($request->lead_organization) ? 0 : $request->lead_organization;
        $lead->organization_link = $request->lead_organization_link;
        $lead->sources = $request->lead_source;
        $lead->referrer_email = $request->referrer_email;
        $lead->street = $request->lead_street;
        $lead->city = $request->lead_city;
        $lead->state = $request->lead_state;
        $lead->postal_code = $request->lead_postal_code;
        $lead->country = $request->lead_country;
        $lead->keynotes = $request->lead_description;
        $lead->tags = $request->lead_tags_list;
        $lead->stage_id = $request->lead_stage;
        $lead->subject = "{$request->lead_first_name} {$request->lead_last_name}";
        $lead->user_id = $request->lead_assigned_user;
        $lead->tag_ids = ! empty($request->tag_ids) ? implode(',', $request->tag_ids) : '';
        $lead->pipeline_id = $pipeline->id;
        $lead->created_by = Session::get('auth_type_id') ?? $user->id;
        $lead->agent_id =  $user->agent_id;
        $lead->date = now()->format('Y-m-d');
        $lead->drive_link = $request->drive_link ?? null;
        $lead->language_test = $request->lead_language_test ?? null;
        $lead->passing_year = $request->lead_passing_year ?? null;
        $lead->cgpa_percentage = $request->lead_cgpa_percentage ?? null;
        $lead->last_education = $request->lead_last_education ?? null;
        $lead->branch_nearby = $request->lead_branch_nearby ?? null;
        $lead->preferred_course = $request->lead_preferred_course ?? null;
        $lead->preferred_destination = $request->lead_preferred_destination ?? null;

        $lead->save();

        // Associate Lead with User
        UserLead::create([
            'user_id' => $user->id,
            'lead_id' => $lead->id,
        ]);

        // Add Stage History
        addLeadHistory([
            'stage_id' => $request->lead_stage,
            'type_id' => $lead->id,
            'type' => 'lead',
        ]);

        // Log Activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $request->name.' Lead Created',
                'message' => $request->name.' Lead created successfully',
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Created',
        ]);

        // Send Notification Email (if enabled)
        if (! empty($request->lead_assigned_user)) {
            $assignedUser = User::find($request->lead_assigned_user);
            $settings = Utility::settings();

            if ($settings['lead_assigned'] == 1) {
                $emailData = [
                    'lead_name' => $lead->name,
                    'lead_email' => $lead->email,
                    'lead_subject' => $lead->subject,
                    'lead_pipeline' => $pipeline->name,
                    'lead_stage' => $stage->name,
                ];

                $resp = Utility::sendEmailTemplate('lead_assigned', [$assignedUser->id => $assignedUser->email], $emailData);

                if (! $resp['is_success']) {
                    return response()->json([
                        'status' => 'success',
                        'lead_id' => $lead->id,
                        'lead' => $lead,
                        'message' => __('Lead successfully created!'),
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'lead_id' => $lead->id,
            'lead' => $lead,
            'message' => __('Lead successfully created!'),
        ], 201);
    }
    public function LeadStageHistory(Request $request)
    {
        // Validate Input
        $validator = \Validator::make($request->all(), [
            'type' => 'required|string',
            'id'   => 'required|exists:leads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $stage_histories = StageHistory::where('type', $request->type)
            ->where('type_id', $request->id)
            ->pluck('stage_id')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data'   => $stage_histories,
        ], 200);
    }

    public function updateLead(Request $request)
    {
        $user = \Auth::user();

        // // Check Permissions
        // if (! $user->can('edit lead') && $user->type !== 'super admin') {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission Denied.'),
        //     ], 200);
        // }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'lead_stage' => 'required',
            'lead_id' => 'required|exists:leads,id',
            'brand_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'lead_branch' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
            'lead_phone' => 'nullable',
            'lead_email' => 'required|email',
            'lead_country' => 'nullable',
            'lead_city' => 'nullable',
            'lead_state' => 'nullable',
            'lead_postal_code' => 'nullable',
            'lead_street' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Fetch Lead
        $lead_id = $request->lead_id;
        $lead = Lead::find($lead_id);

        if (! $lead) {
            return response()->json([
                'status' => 'error',
                'message' => __('Lead not found.'),
            ], 404);
        }

        // Update Lead Data
        $lead->title = $request->lead_prefix ?? $lead->title;
        $lead->name = $request->name;
        $lead->email = $request->lead_email;
        $lead->phone = $request->lead_phone;
        $lead->mobile_phone = $request->lead_phone;
        $lead->branch_id = $request->lead_branch;
        $lead->brand_id = $request->brand_id;
        $lead->region_id = $request->region_id;
        $lead->organization_id = is_string($request->lead_organization) ? 0 : $request->lead_organization;
        $lead->organization_link = $request->lead_organization_link;
        $lead->sources = $request->lead_source;
        $lead->referrer_email = $request->referrer_email;
        $lead->street = $request->lead_street;
        $lead->city = $request->lead_city;
        $lead->state = $request->lead_state;
        $lead->postal_code = $request->lead_postal_code;
        $lead->country = $request->lead_country;
        $lead->keynotes = $request->lead_description;
        $lead->tags = $request->lead_tags_list;
        $lead->stage_id = $request->lead_stage;
        $lead->subject = "{$request->lead_first_name} {$request->lead_last_name}";
        $lead->user_id = $request->lead_assigned_user;
        $lead->tag_ids = ! empty($request->tag_ids) ? implode(',', $request->tag_ids) : $lead->tag_ids;
        $lead->drive_link = $request->drive_link ?? $lead->drive_link;
        $lead->language_test = $request->lead_language_test ;
        $lead->passing_year = $request->lead_passing_year ;
        $lead->cgpa_percentage = $request->lead_cgpa_percentage ;
        $lead->last_education = $request->lead_last_education ;
        $lead->branch_nearby = $request->lead_branch_nearby ?? null;
        $lead->preferred_course = $request->lead_preferred_course ?? null;
        $lead->preferred_destination = $request->lead_preferred_destination ?? null;

        $lead->save();

        // Update User-Lead Association
        UserLead::updateOrCreate(
            ['lead_id' => $lead->id],
            ['user_id' => $user->id]
        );

        // Add Stage History (if stage changed)
        if ($lead->wasChanged('stage_id')) {
            addLeadHistory([
                'stage_id' => $request->lead_stage,
                'type_id' => $lead->id,
                'type' => 'lead',
            ]);
        }

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Updated',
                'message' => 'Lead updated successfully',
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Updated',
        ]);

        // Send Notification Email (if enabled)
        if (! empty($request->lead_assigned_user)) {
            $assignedUser = User::find($request->lead_assigned_user);
            $settings = Utility::settings();

            if (isset($settings)) {
                $emailData = [
                    'lead_name' => $lead->name,
                    'lead_email' => $lead->email,
                    'lead_subject' => $lead->subject,
                    'lead_pipeline' => Pipeline::find($lead->pipeline_id)->name,
                    'lead_stage' => LeadStage::find($lead->stage_id)->name,
                ];

                $resp = Utility::sendEmailTemplate('lead_updated', [$assignedUser->id => $assignedUser->email], $emailData);

                if (! $resp['is_success']) {
                    return response()->json([
                        'status' => 'info',
                        'lead_id' => $lead->id,
                        'message' => __('Lead successfully updated!'),
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'info',
            'lead_id' => $lead->id,
            'message' => __('Lead successfully updated!'),
        ], 200);
    }

    public function fetchColumns(Request $request)
    {

        $user = \Auth::user();

        // Check Permissions
        if (! $user->can('create lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'leads_file' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('leads_file')) {

            $file = $request->file('leads_file');
            $extension = $file->getClientOriginalExtension();
            if ($extension == 'csv') {
                $first_row = $this->readCsvHeader($file);
            } else {
                $first_row = $this->readExcelHeader($file);
            }

            $users = User::where('created_by', \Auth::user()->creatorId())
                ->where('is_active', 1)
                ->whereNotIn('type', ['Agent', 'client', 'company'])
                ->where('id', '!=', \Auth::id())
                ->pluck('name', 'id');

            $pipelines = Pipeline::get()->pluck('name', 'id');
            $companies = FiltersBrands();

            $filter = BrandsRegionsBranches();
            $companies = $filter['brands'];
            $regions = $filter['regions'];
            $branches = $filter['branches'];
            $employees = $filter['employees'];

            // Render the getDiscussions partial view and store the HTML in $returnHTML
            $returnHTML = ['first_row' => $first_row, 'users' => $users, 'pipelines' => $pipelines, 'companies' => $companies, 'regions' => $regions, 'branches' => $branches, 'employees' => $employees];

            return response()->json(['status' => 'success', 'data' => $returnHTML]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'No CSV file uploaded']);
        }
    }

    private function readExcelHeader($file)
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $first_row = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $cleaned_row = [];
            foreach ($row->getCellIterator() as $cell) {
                $cellValue = $cell->getValue();
                $clean_string = preg_replace('/[^\x20-\x7E]/', '', $cellValue);
                if (empty($clean_string)) {
                    continue;
                }
                $cleaned_row[] = $clean_string;
            }
            $first_row = $cleaned_row;
            break;
        }

        return $first_row;
    }

    public function readCsvHeader($file)
    {

        // $delimater = $this->getFileDelimiter($file, 1);

        $handle = fopen($file->getPathname(), 'r');
        $first_row = [];

        while ($line = fgets($handle)) {
            // Remove BOM
            if (substr($line, 0, 3) == pack('CCC', 0xEF, 0xBB, 0xBF)) {
                $line = substr($line, 3);
            }

            // Remove null bytes
            $clean_line = str_replace("\x00", '', $line);

            // Decode UTF-16LE
            $clean_line = utf8_encode(utf8_decode($clean_line));

            $clean_line = str_replace('??', '', $clean_line);
            $delimater = $this->getFileDelimiter($file, 1);
            $fields = explode($delimater, $clean_line);

            $fields = explode($delimater, $line);

            foreach ($fields as $field) {
                $clean_string = preg_replace('/[^\x20-\x7E]/', '', $field);
                if (! empty($clean_string)) {
                    $first_row[] = $clean_string;
                }
            }
            break;
        }
        fclose($handle);

        return $first_row;
    }

    public function getFileDelimiter($file, $checkLines = 2)
    {
        $file = new SplFileObject($file);
        $delimiters = [
            ',',
            "\t",
            ';',
            '|',
            ':',
        ];
        $results = [];
        $i = 0;
        while ($file->valid() && $i <= $checkLines) {
            $line = $file->fgets();
            foreach ($delimiters as $delimiter) {
                $regExp = '/['.$delimiter.']/';
                $fields = preg_split($regExp, $line);
                if (count($fields) > 1) {
                    if (! empty($results[$delimiter])) {
                        $results[$delimiter]++;
                    } else {
                        $results[$delimiter] = 1;
                    }
                }
            }
            $i++;
        }
        $results = array_keys($results, max($results));

        return $results[0];
    }

    public function importCsv(Request $request)
    {

        $user = \Auth::user();

        // Check Permissions
        if (! $user->can('create lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'leads_file' => 'required',
            'extension' => 'required',
            'brand_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'lead_branch' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }
        $usr = \Auth::user();

        $file = $request->file('leads_file');

        $column_arr = [];

        // Default Field Value
        if ($usr->default_pipeline) {
            $pipeline = Pipeline::where('id', '=', $usr->default_pipeline)->first();
            if (! $pipeline) {
                $pipeline = Pipeline::first();
            }
        } else {
            $pipeline = Pipeline::first();
        }

        $stage = LeadStage::where('pipeline_id', '=', $pipeline->id)->orderBy('order', 'asc')->first();

        $file = $request->file('leads_file');
        $extension = $file->getClientOriginalExtension();
        if ($extension == 'csv') {
            $response = $this->csvSheetDataSaved($request, $file, $pipeline, $stage);
        } else {
            $response = $this->excelSheetDataSaved($request, $file, $pipeline, $stage);
        }

        if ($response = true) {
            return json_encode([
                'status' => 'success',
                'message' => 'Leads Import successfully',
            ]);
        } else {
            return json_encode([
                'status' => 'error',
                'message' => 'Permission denied.',
            ]);
        }
    }

    private function csvSheetDataSaved($request, $file, $pipeline, $stage)
    {
        $usr = \Auth::user();
        $column_arr = [];
        $handle = fopen($file->getPathname(), 'r');
        $key = 0;

        while ($line = fgets($handle)) {

            // Remove BOM
            if (substr($line, 0, 3) == pack('CCC', 0xEF, 0xBB, 0xBF)) {
                $line = substr($line, 3);
            }

            // Remove null bytes
            $clean_line = str_replace("\x00", '', $line);

            // Decode UTF-16LE
            $clean_line = utf8_encode(utf8_decode($clean_line));

            $clean_line = str_replace('??', '', $clean_line);
            $delimater = $this->getFileDelimiter($file, 1);
            $line = explode($delimater, $clean_line);

            if ($key == 0) {
                foreach ($line as $column_key => $column) {
                    $column = preg_replace('/[^\x20-\x7E]/', '', $column);

                    if (empty($_POST['columns'][$column])) {
                        continue;
                    }

                    $column_arr[$column_key] = $_POST['columns'][$column];
                }
                $key++;

                continue;
            }

            $lead = new Lead;
            $test = [];
            foreach ($line as $column_key => $column) {

                $column = preg_replace('/[^\x20-\x7E]/', '', $column);
                if (! empty($column_arr[$column_key])) {
                    $test[$column_arr[$column_key]] = $column;
                    $lead->{$column_arr[$column_key]} = str_replace('"', '', $column);
                }
            }

            if (filter_var($test['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $lead_exist = Lead::where('email', $test['email'])
                    ->where('brand_id', $request->brand_id)
                    ->where('region_id', $request->region_id)
                    ->where('branch_id', $request->lead_branch)
                    ->where('created_by', $usr->id)
                    ->first();

                if ($lead_exist) {
                    continue;
                }
                $lead->email = $test['email'];
            } else {
                $lead->email = 'N/A';
            }

            $lead->subject = $test['subject'] ?? 'Default Subject';

            $lead->user_id = $request->lead_assigned_user;
            $lead->brand_id = $request->brand_id;
            $lead->region_id = $request->region_id;
            $lead->branch_id = $request->lead_branch;

            $lead->pipeline_id = $pipeline->id;
            if (! isset($stage->id)) {
                return redirect()->back()->with('error', 'Please create lead stage first');
            }

            $lead->stage_id = $stage->id;
            $lead->created_by = $usr->id;
            if($usr->type == 'Agent'){
                $lead->agent_id = $usr->agent_id;
            }
            $lead->date = date('Y-m-d');

            if (! empty($test['name']) || ! empty($test['email']) || ! empty($test['phone']) || ! empty($test['subject']) || ! empty($test['notes'])) {
                $lead->save();

                        // Add Stage History
                addLeadHistory([
                    'stage_id' => $stage->id,
                    'type_id' => $lead->id,
                    'type' => 'lead',
                ]);

                // Log Activity
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $lead->name.' Lead Created',
                        'message' => $lead->name.' Lead created successfully',
                    ]),
                    'module_id' => $lead->id,
                    'module_type' => 'lead',
                    'notification_type' => 'Lead Created',
                ]);
                // dd($test);
                if (in_array('notes', $column_arr)) {
                    $notes = new LeadNote;
                    $notes->description = str_replace('"', '', $test['notes'] ?? '') ?? '';
                    $notes->created_by = auth()->id();
                    $notes->lead_id = $lead->id;
                    $notes->save();
                }
                UserLead::create(
                    [
                        'user_id' => $usr->id,
                        'lead_id' => $lead->id,
                    ]
                );

                $usrEmail = User::find($request->lead_assigned_user);

                // Send Email
                $setings = Utility::settings();
                if ($setings['lead_assigned'] == 1) {

                    $usrEmail = User::find($request->lead_assigned_user);
                    $leadAssignArr = [
                        'lead_name' => $lead->name,
                        'lead_email' => $lead->email,
                        'lead_subject' => $lead->subject,
                        'lead_pipeline' => $pipeline->name,
                        'lead_stage' => $stage->name,

                    ];

                    $resp = Utility::sendEmailTemplate('lead_assigned', [$usrEmail->id => $usrEmail->email], $leadAssignArr);

                    // return redirect()->back()->with('success', __('Lead successfully created!') . (($resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
                }

                // Slack Notification
                // $setting  = Utility::settings($usr->id);
                // if (isset($setting['lead_notification']) && $setting['lead_notification'] == 1) {
                //     $msg = __("New Lead created by") . ' ' . $usr->name . '.';
                //     Utility::send_slack_msg($msg);
                // }

                // Telegram Notification
                // $setting  = Utility::settings($usr->id);
                // if (isset($setting['telegram_lead_notification']) && $setting['telegram_lead_notification'] == 1) {
                //     $msg = __("New Lead created by") . ' ' . $usr->name . '.';
                //     Utility::send_telegram_msg($msg);
                // }
            }
            // $lead->save();
        }

        return true;
        // return redirect()->back()->with('success', __('Lead successfully created!'));
    }

    private function excelSheetDataSaved($request, $file, $pipeline, $stage)
    {
        $usr = \Auth::user();
        $column_arr = [];
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $key = 0;

        // Extract column mapping
        foreach ($worksheet->getRowIterator() as $line) {
            if ($key == 0) {
                foreach ($line->getCellIterator() as $column_key => $column) {
                    $column = preg_replace('/[^\x20-\x7E]/', '', $column);
                    if (empty($_POST['columns'][$column])) {
                        continue;
                    }
                    $column_arr[$column_key] = $_POST['columns'][$column];
                }
                $key++;

                continue;
            }

            $lead = new Lead;
            $test = [];

            // Assign attributes to leads, then check if the email already exists in the database or if it's a new lead.
            foreach ($line->getCellIterator() as $column_key => $column) {
                $column = preg_replace('/[^\x20-\x7E]/', '', $column);
                if (! empty($column_arr[$column_key])) {
                    $test[$column_arr[$column_key]] = $column;
                    $lead->{$column_arr[$column_key]} = $column;
                }
            }

            // Check if the lead exists
            if (filter_var($test['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $lead_exist = Lead::where('email', $lead->email)->where('brand_id', $request->brand_id)->where('region_id', $request->region_id)->where('branch_id', $request->lead_branch)->first();
                if ($lead_exist) {
                    continue;
                }
                $lead->email = in_array('email', $column_arr) ? $lead->email : '';
            } else {
                $lead->email = 'N/A';
            }
            $lead->subject = in_array('subject', $column_arr) ? $lead->subject : '';

            $lead->user_id = $request->lead_assigned_user;
            $lead->brand_id = $request->brand_id;
            $lead->region_id = $request->region_id;
            $lead->branch_id = $request->lead_branch;
            $lead->pipeline_id = $pipeline->id;

            if (! isset($stage->id)) {
                return redirect()->back()->with('error', 'Please create lead stage first');
            }

            $lead->stage_id = $stage->id;
            $lead->created_by = \Auth::user()->id;
             if($usr->type == 'Agent'){
                $lead->agent_id = $usr->agent_id;
            }
            $lead->date = date('Y-m-d');
            if (! empty($test['name']) || ! empty($test['email']) || ! empty($test['phone']) || ! empty($test['subject']) || ! empty($test['notes'])) {
                $lead->save();

                          // Add Stage History
                addLeadHistory([
                    'stage_id' => $stage->id,
                    'type_id' => $lead->id,
                    'type' => 'lead',
                ]);

                // Log Activity
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $lead->name.' Lead Created',
                        'message' => $lead->name.' Lead created successfully',
                    ]),
                    'module_id' => $lead->id,
                    'module_type' => 'lead',
                    'notification_type' => 'Lead Created',
                ]);

                if (in_array('notes', $column_arr)) {
                    $notes = new LeadNote;
                    $notes->description = str_replace('"', '', $test['notes']) ?? '';
                    $notes->created_by = auth()->id();
                    $notes->lead_id = $lead->id;
                    $notes->save();
                }

                UserLead::create([
                    'user_id' => $usr->id,
                    'lead_id' => $lead->id,
                ]);

                $usrEmail = User::find($request->lead_assigned_user);

                // Send Email
                $setings = Utility::settings();
                if ($setings['lead_assigned'] == 1) {
                    $leadAssignArr = [
                        'lead_name' => $lead->name,
                        'lead_email' => $lead->email,
                        'lead_subject' => $lead->subject,
                        'lead_pipeline' => $pipeline->name,
                        'lead_stage' => $stage->name,
                    ];

                    $resp = Utility::sendEmailTemplate('lead_assigned', [$usrEmail->id => $usrEmail->email], $leadAssignArr);
                }
            }
        }

        return true;
    }

    public function getLeadDetails(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Fetch Lead Details
        $lead = Lead::with('assignto')
            ->with('brand')
            ->with('stage')
            ->with('branch')
            ->with('region')
            ->with('pipeline')
            ->with('created_by')
            ->with('Agency')
            ->with('Source')
            ->select('leads.*')
            ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')->findOrFail($request->lead_id);

        if ($lead->is_active) {
            $calendarTasks = [];
            $deal = Deal::find($lead->is_converted);

            // Calculate Lead Stage Progress
            $stages = LeadStage::where('pipeline_id', $lead->pipeline_id)->get();
            $currentStageIndex = $stages->pluck('id')->search($lead->stage_id) + 1;
            $progressPercentage = $stages->count() > 0
                ? number_format(($currentStageIndex * 100) / $stages->count())
                : 0;

            // Fetch Related Data
            $tasks = DealTask::where([
                'related_to' => $lead->id,
                'related_type' => 'lead',
            ])->orderBy('status')->get();

            // $branches = Branch::pluck('name', 'id');
            // $users = allUsers();
            $logActivities = getLogActivity($lead->id, 'lead');

            // Lead Stage History
            $stageHistories = StageHistory::where('type', 'lead')
                ->where('type_id', $lead->id)
                ->pluck('stage_id')
                ->toArray();

            // Tags Based on User Role
            $tags = [];
            if (\Auth::check()) {
                $user = \Auth::user();

                if (in_array($user->type, ['super admin', 'Admin Team'])) {
                    $tags = LeadTag::pluck('id', 'tag')->toArray();
                } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                    $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('id', 'tag')->toArray();
                } elseif ($user->type === 'Region Manager') {
                    $tags = LeadTag::where('region_id', $user->region_id)->pluck('id', 'tag')->toArray();
                } else {
                    $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('id', 'tag')->toArray();
                }
            }

            // Fetch Agencies
            $agencies = Agency::pluck('organization_name', 'id');

            // Return JSON Response
            return response()->json([
                'status' => 'success',
                'data' => [
                    'lead' => $lead,
                    'deal' => $deal,
                    'stages' => $stages,
                    'current_stage_index' => $currentStageIndex,
                    'progress_percentage' => $progressPercentage,
                    'tasks' => $tasks,
                    // 'branches' => $branches,
                    // 'users' => $users,
                    'log_activities' => $logActivities,
                    'stage_histories' => $stageHistories,
                    'tags' => $tags,
                    'agencies' => $agencies,
                ],
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Lead is not active.',
        ], 400);
    }

    public function getLeadDetailOnly(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Fetch Lead Details
        $lead = Lead::select('leads.*')
            ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')
            ->where('leads.id', $request->lead_id)
            ->first(); // Use `first` instead of `findOrFail` for a conditional check

        if ($lead) {
            return response()->json([
                'status' => 'success',
                'data' => $lead,
            ], 200);
        }

        // Return error if lead is not active or not found
        return response()->json([
            'status' => 'error',
            'message' => 'Lead is not active.',
        ], 400);
    }

    public function deleteBulkLeads(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (! $user->can('delete lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'ids' => 'required|string', // Expecting comma-separated IDs
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Parse and Delete Leads
        $leadIds = array_filter(explode(',', $request->ids));

        if (empty($leadIds)) {
            return response()->json([
                'status' => 'error',
                'message' => __('At least select one lead.'),
            ], 400);
        }

        $deletedCount = Lead::whereIn('id', $leadIds)->delete();

        if ($deletedCount > 0) {
            // Log Activity
            addLogActivity([
                'type' => 'warning',
                'note' => json_encode([
                    'title' => 'Leads Deleted',
                    'message' => count($leadIds).' leads deleted successfully',
                ]),
                'module_id' => null,
                'module_type' => 'lead',
                'notification_type' => 'Leads Deleted',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Leads deleted successfully.'),
                'deleted_count' => $deletedCount,
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('No leads were deleted. Please check the IDs.'),
            ], 404);
        }
    }

    // ......
    public function updateBulkLead(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (! $user->can('edit lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'selectedIds' => 'required|string', // Expecting comma-separated IDs
            'brand' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'branch_id' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // Parse and Validate IDs
        $ids = array_filter(explode(',', $request->selectedIds));

        if (empty($ids)) {
            return response()->json([
                'status' => 'error',
                'message' => __('At least select one lead.'),
            ], 400);
        }

        // Prepare Update Data
        $updateData = [];
        if (! empty($request->brand)) {
            $updateData['brand_id'] = $request->brand;
            $updateData['region_id'] = $request->region_id;
            $updateData['branch_id'] = $request->branch_id;
            $updateData['user_id'] = $request->lead_assigned_user;
        } elseif (! empty($request->region_id)) {
            $updateData['region_id'] = $request->region_id;
            $updateData['branch_id'] = $request->branch_id;
            $updateData['user_id'] = $request->lead_assigned_user;
        } elseif (! empty($request->branch_id)) {
            $updateData['branch_id'] = $request->branch_id;
            $updateData['user_id'] = $request->lead_assigned_user;
        }

        if (empty($updateData)) {
            return response()->json([
                'status' => 'error',
                'message' => __('No valid fields provided for update.'),
            ], 400);
        }

        // Update Leads
        $updatedCount = Lead::whereIn('id', $ids)->update($updateData);

        if ($updatedCount > 0) {
            // Log Activity
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Leads Updated',
                    'message' => count($ids).' leads updated successfully',
                ]),
                'module_id' => null,
                'module_type' => 'lead',
                'notification_type' => 'Leads Updated',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Leads updated successfully.'),
                'updated_count' => $updatedCount,
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('No leads were updated. Please check the IDs.'),
            ], 404);
        }
    }

    public function addLeadTags(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (! $user->can('edit lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'deal_id' => 'nullable|exists:deals,id',
            'lead_id' => 'nullable|exists:leads,id',
            'selectedIds' => 'required|string', // Expecting comma-separated IDs
            'old_tag_id' => 'nullable|string',
            'new_tag_id' => 'nullable|string',
            'tagid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // ✅ 1. Update Tags for Deals
        if (! empty($request->deal_id)) {
            $deal = Deal::find($request->deal_id);

            if (! empty($request->old_tag_id) && ! empty($request->new_tag_id)) {
                if ($request->old_tag_id == $request->new_tag_id) {
                    return response()->json([
                        'status' => 'success',
                        'message' => __('Tag updated successfully.'),
                    ], 200);
                }

                $tags = explode(',', $deal->tag_ids ?? '');
                $tags = array_filter($tags, fn ($tag) => $tag != $request->old_tag_id);
                $tags[] = $request->new_tag_id;

                $deal->tag_ids = implode(',', $tags);
                $deal->save();

                // ✅ Add Log Activity
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Tag Updated for Deal',
                        'message' => "Tag updated from {$request->old_tag_id} to {$request->new_tag_id} for Deal ID: {$request->deal_id}",
                    ]),
                    'module_id' => $deal->id,
                    'module_type' => 'deal',
                    'notification_type' => 'Tag Updated',
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Tag updated successfully.'),
                ], 200);
            }
        }

        // ✅ 2. Bulk Add Tags to Leads
        if (! empty($request->selectedIds)) {
            $ids = explode(',', $request->selectedIds);
            $leads = Lead::whereIn('id', $ids)->get();

            if ($leads->count() > 0) {
                foreach ($leads as $lead) {
                    $tags = explode(',', $lead->tag_ids ?? '');
                    $tags[] = $request->tagid;
                    $lead->tag_ids = implode(',', array_unique($tags));
                    $lead->save();

                    // ✅ Add Log Activity for each Lead
                    addLogActivity([
                        'type' => 'info',
                        'note' => json_encode([
                            'title' => 'Tag Added to Lead',
                            'message' => "Tag ID: {$request->tagid} added to Lead ID: {$lead->id}",
                        ]),
                        'module_id' => $lead->id,
                        'module_type' => 'lead',
                        'notification_type' => 'Tag Added',
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => __('Tag added successfully to selected leads.'),
                ], 200);
            }
        }

        // ✅ 3. Update Tags for Individual Lead
        if (! empty($request->lead_id)) {
            $lead = Lead::find($request->lead_id);

            if (! empty($request->old_tag_id) && ! empty($request->new_tag_id)) {
                if ($request->old_tag_id == $request->new_tag_id) {
                    return response()->json([
                        'status' => 'success',
                        'message' => __('Tag updated successfully.'),
                    ], 200);
                }

                $tags = explode(',', $lead->tag_ids ?? '');
                $tags = array_filter($tags, fn ($tag) => $tag != $request->old_tag_id);
                $tags[] = $request->new_tag_id;

                $lead->tag_ids = implode(',', $tags);
                $lead->save();

                // ✅ Add Log Activity
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Tag Updated for Lead',
                        'message' => "Tag updated from {$request->old_tag_id} to {$request->new_tag_id} for Lead ID: {$lead->id}",
                    ]),
                    'module_id' => $lead->id,
                    'module_type' => 'lead',
                    'notification_type' => 'Tag Updated',
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Tag updated successfully.'),
                ], 200);
            }
        }

        // Default Fallback
        return response()->json([
            'status' => 'error',
            'message' => __('Invalid request or no valid data provided.'),
        ], 400);
    }

    public function convertToAdmission(Request $request)
    {
        $id = $request->id ?? '';
        $lead = Lead::findOrFail($id);
        $usr = \Auth::user();
        $user = \Auth::user();

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'client_passport' => 'required',
                'intake_month' => 'required',
                'intake_year' => 'required',
                'drive_link' => 'required',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->errors();

            return response()->json([
                'status' => 'error',
                'message' => $messages->first(),
            ]);
        }

        // Check if lead is already converted
        if ($lead->is_converted != 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sorry This Lead already converted',
            ]);
        }
        // Check if passport is blocked
        $blocked_status = User::where('passport_number', $request->client_passport)
            ->where('blocked_status', '1')
            ->first();

        if (! empty($blocked_status)) {
            return response()->json([
                'status' => 'error',
                'message' => __('The passport number \''.$request->client_passport.'\' is currently blocked and cannot proceed with the application. Please contact support for further assistance.'),
            ]);
        }

        // Find or create client
        $client = User::where('passport_number', $request->client_passport)->first();

        if (! $client) {
            $validator = \Validator::make($request->all(), [
                'client_name' => 'required',
                'client_email' => 'required|email|unique:users,email',
                'client_passport' => 'required|unique:users,passport_number',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                ], 422);
            }

            $role = Role::findByName('client', 'web');
            $client = User::create([
                'name' => $request->client_name,
                'email' => $request->client_email,
                'password' => \Hash::make('123456789'),
                'brand_id' => $lead->brand_id,
                'branch_id' => $lead->branch_id,
                'type' => 'client',
                'lang' => 'en',
                'created_by' => $user->creatorId(),
            ]);

            $client->passport_number = $request->client_passport;
            $client->region_id = $lead->region_id;
            $client->save();
            $client->assignRole($role);
        } else {
            // Check if passport is already used in this branch
            $passport_user = User::whereRaw('REPLACE(passport_number, " ", "") = ?', [str_replace(' ', '', $request->client_passport)])
                ->where('branch_id', $lead->branch_id)
                ->first();

            if ($passport_user) {
                $is_exist = Deal::join('client_deals', 'client_deals.deal_id', '=', 'deals.id')
                    ->where('client_deals.client_id', $passport_user->id)
                    ->where('deals.branch_id', $lead->branch_id)
                    ->first();

                if ($is_exist) {
                    $branchName = Branch::find($lead->branch_id)->name ?? '';

                    return response()->json([
                        'status' => 'error',
                        'message' => 'This passport is already used for the '.$branchName.' branch.',
                    ]);
                }
            }
        }

        // Get pipeline stage
        $stage = Stage::where('pipeline_id', $lead->pipeline_id)
            ->orderBy('id')
            ->first();

        if (empty($stage)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please Create Stage for This Pipeline.',
            ]);
        }

        // Create Deal
        $deal = new Deal;
        $deal->name = $request->name;
        $deal->price = 0;
        $deal->pipeline_id = $lead->pipeline_id;
        $deal->stage_id = $stage->id;
        $deal->sources = in_array('sources', $request->is_transfer ?? []) ? $lead->sources : '';
        $deal->products = in_array('products', $request->is_transfer ?? []) ? $lead->products : '';
        $deal->notes = in_array('notes', $request->is_transfer ?? []) ? $lead->notes : '';
        $deal->labels = $lead->labels;
        $deal->status = 'Active';
        $deal->created_by = $lead->created_by;
        $deal->branch_id = $lead->branch_id;
        $deal->region_id = $lead->region_id;
        $deal->drive_link = $request->drive_link ?? $lead->drive_link;
        $deal->university_id = $request->university_id;
        $deal->assigned_to = $lead->user_id;
        $deal->intake_month = $request->intake_month;
        $deal->intake_year = $request->intake_year;
        $deal->brand_id = $lead->brand_id;
        $deal->agent_id = $lead->agent_id;
        $deal->organization_id = is_string($lead->organization_id) ? 0 : $lead->organization_id;
        $deal->organization_link = $lead->organization_link;
        $deal->tag_ids = $lead->tag_ids;
        $deal->save();

        // Transfer tasks
        $tasksQuery = DealTask::query();
        $FiltersBrands = array_keys(FiltersBrands());

        if (\Auth::user()->type != 'HR') {
            if (\Auth::user()->type == 'super admin' || \Auth::user()->can('level 1')) {
                $FiltersBrands[] = '3751';
                $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
            } elseif (\Auth::user()->type == 'company') {
                $tasksQuery->where('deal_tasks.brand_id', \Auth::user()->id);
            } elseif (\Auth::user()->type == 'Project Director' || \Auth::user()->type == 'Project Manager' || \Auth::user()->can('level 2')) {
                $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
            } elseif (\Auth::user()->type == 'Region Manager' || (\Auth::user()->can('level 3') && ! empty(\Auth::user()->region_id))) {
                $tasksQuery->where('deal_tasks.region_id', \Auth::user()->region_id);
            } elseif (
                \Auth::user()->type == 'Branch Manager' || \Auth::user()->type == 'Admissions Officer' ||
                \Auth::user()->type == 'Careers Consultant' || \Auth::user()->type == 'Admissions Manager' ||
                \Auth::user()->type == 'Marketing Officer' || (\Auth::user()->can('level 4') && ! empty(\Auth::user()->branch_id))
            ) {
                $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            } elseif (\Auth::user()->type === 'Agent') {
                $tasksQuery->where(function ($query) {
                    $query->where('deal_tasks.assigned_to', \Auth::user()->id)
                        ->orWhere('deal_tasks.created_by', \Auth::user()->id);
                });
            } else {
                $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            }
        }

        $tasks = $tasksQuery->where('related_to', $lead->id)
            ->where('related_type', 'lead')
            ->orderBy('status')
            ->get();

        foreach ($tasks as $task) {
            $task->related_to = $deal->id;
            $task->related_type = 'deal';
            $task->save();
        }

        // Transfer notes
        $LeadNotes = LeadNote::where('lead_id', $lead->id)->get();
        foreach ($LeadNotes as $LeadNote) {
            DealNote::create([
                'description' => $LeadNote->description,
                'created_by' => $LeadNote->created_by,
                'deal_id' => $deal->id,
            ]);
        }

        // Create client deal relationship
        ClientDeal::create([
            'deal_id' => $deal->id,
            'client_id' => $client->id,
        ]);

        // Transfer user relationships
        $leadUsers = UserLead::where('lead_id', $lead->id)->get();
        foreach ($leadUsers as $leadUser) {
            UserDeal::create([
                'user_id' => $leadUser->user_id,
                'deal_id' => $deal->id,
            ]);
        }

        // Transfer discussions
        if (in_array('discussion', $request->is_transfer ?? [])) {
            $discussions = LeadDiscussion::where('lead_id', $lead->id)
                ->where('created_by', $usr->creatorId())
                ->get();
            foreach ($discussions as $discussion) {
                DealDiscussion::create([
                    'deal_id' => $deal->id,
                    'comment' => $discussion->comment,
                    'created_by' => $discussion->created_by,
                ]);
            }
        }

        // Transfer files
        if (in_array('files', $request->is_transfer ?? [])) {
            $files = LeadFile::where('lead_id', $lead->id)->get();
            foreach ($files as $file) {
                $location = base_path().'/storage/lead_files/'.$file->file_path;
                $new_location = base_path().'/storage/deal_files/'.$file->file_path;

                if (file_exists($location) && copy($location, $new_location)) {
                    DealFile::create([
                        'deal_id' => $deal->id,
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path,
                    ]);
                }
            }
        }

        // Transfer calls
        if (in_array('calls', $request->is_transfer ?? [])) {
            $calls = LeadCall::where('lead_id', $lead->id)->get();
            foreach ($calls as $call) {
                DealCall::create([
                    'deal_id' => $deal->id,
                    'subject' => $call->subject,
                    'call_type' => $call->call_type,
                    'duration' => $call->duration,
                    'user_id' => $call->user_id,
                    'description' => $call->description,
                    'call_result' => $call->call_result,
                ]);
            }
        }

        // Transfer emails
        if (in_array('emails', $request->is_transfer ?? [])) {
            $emails = LeadEmail::where('lead_id', $lead->id)->get();
            foreach ($emails as $email) {
                DealEmail::create([
                    'deal_id' => $deal->id,
                    'to' => $email->to,
                    'subject' => $email->subject,
                    'description' => $email->description,
                ]);
            }
        }

        // Update lead status
        $lead->is_converted = $deal->id;
        $lead->save();

        // Add logs
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Converted',
                'message' => 'Lead converted successfully.',
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Converted',
        ];
        addLogActivity($data);

        $data = [
            'type' => 'sucssess',
            'note' => json_encode([
                'title' => 'Admission  Created',
                'message' => 'Admission  created successfully.',
            ]),
            'module_id' => $deal->id,
            'module_type' => 'deal',
            'notification_type' => 'Admission  Created',
        ];
        addLogActivity($data);

        // Add stage history
        $data_for_stage_history = [
            'stage_id' => $stage->id,
            'type_id' => $deal->id,
            'type' => 'deal',
        ];
        addLeadHistory($data_for_stage_history);

        // Send email notification
        $pipeline = Pipeline::find($lead->pipeline_id);
        $dArr = [
            'deal_name' => $deal->name ?? '',
            'deal_pipeline' => $pipeline->name ?? '',
            'deal_stage' => $stage->name ?? '',
            'deal_status' => $deal->status ?? '',
            'deal_price' => $usr->$deal->price ?? '',
        ];
        Utility::sendEmailTemplate('Assign Deal', [$client->id => $client->email], $dArr);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead successfully converted',
        ]);
    }

    public function leadsLabels(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:leads,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check Permission
        if (! \Auth::user()->can('edit lead')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Fetch Lead and Labels
        $lead = Lead::find($request->id);
        $labels = Label::where('pipeline_id', '=', $lead->pipeline_id)->get();
        $selected = $lead->labels();

        $selectedLabels = $selected ? $selected->pluck('name', 'id')->toArray() : [];

        // Return JSON Response
        return response()->json([
            'status' => 'success',
            'data' => [
                'lead_id' => $lead->id,
                'labels' => $labels,
                'selected_labels' => $selectedLabels,
            ],
        ], 200);
    }

    public function leadLabelStore(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:leads,id',
                'labels' => 'required|array',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check Permission
        if (! \Auth::user()->can('edit lead')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find the Lead
        $lead = Lead::find($request->id);

        // Update Labels
        if ($request->has('labels') && is_array($request->labels)) {
            $lead->labels = implode(',', $request->labels);
        } else {
            $lead->labels = null;
        }

        $lead->save();

        // Return JSON Response
        return response()->json([
            'status' => 'success',
            'message' => __('Labels successfully updated!'),
            'data' => [
                'lead_id' => $lead->id,
                'labels' => $lead->labels,
            ],
        ], 200);
    }

    public function leadsDelete(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:leads,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check Permission
        if (! \Auth::user()->can('delete lead') && \Auth::user()->type != 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find the Lead
        $lead = Lead::find($request->id);

        // Delete related data
        LeadDiscussion::where('lead_id', '=', $lead->id)->delete();
        LeadFile::where('lead_id', '=', $lead->id)->delete();
        UserLead::where('lead_id', '=', $lead->id)->delete();
        LeadActivityLog::where('lead_id', '=', $lead->id)->delete();

        // Log the deletion
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Deleted',
                'message' => 'Lead deleted successfully',
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Deleted',
        ];
        addLogActivity($data);

        // Delete the Lead
        $lead->delete();

        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => __('Lead successfully deleted!'),
        ], 200);
    }

    public function updateLeadStage(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
                'stage_id' => 'required|exists:lead_stages,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $lead_id = $request->lead_id;
        $stage_id = $request->stage_id;

        // Get the current stage of the lead
        $from_stage = Lead::where('id', $lead_id)->first()->stage_id;
        $to_stage = $stage_id;
        $stages = LeadStage::pluck('name', 'id')->toArray();

        // Update the lead's stage
        Lead::where('id', $lead_id)->update(['stage_id' => $stage_id]);

        // Log the old stage history
        $data_for_stage_history_old = [
            'stage_id' => $from_stage,
            'type_id' => $lead_id,
            'type' => 'lead',
        ];
        addLeadHistory($data_for_stage_history_old);

        // Log the new stage history
        $data_for_stage_history = [
            'stage_id' => $stage_id,
            'type_id' => $lead_id,
            'type' => 'lead',
        ];
        addLeadHistory($data_for_stage_history);

        // Log the stage update action
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Stage Updated',
                'message' => 'Lead stage has been updated successfully from '.$stages[$from_stage].' to '.$stages[$to_stage].'.',
            ]),
            'module_id' => $lead_id,
            'module_type' => 'lead',
            'notification_type' => 'Stage Updated',
        ];
        addLogActivity($data);

        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => 'Lead stage updated successfully.',
        ], 200);
    }

    public function LeadOrgnizationUpdate(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
                'agency_id' => 'required|exists:agencies,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find the lead by ID
        $lead = Lead::find($request->lead_id);

        if ($lead) {
            // Update the organization link
            $lead->organization_link = $request->agency_id;
            $lead->save();

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead updated successfully.',
            ], 200);
        } else {
            // Return error response if the lead is not found
            return response()->json([
                'status' => 'error',
                'message' => 'Lead not found',
            ], 404);
        }
    }

    public function LeadDriveLinkUpdate(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
                'drive_link' => 'required|url',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find the lead by ID
        $lead = Lead::find($request->lead_id);

        if ($lead) {
            // Save the previous drive link for logging purposes
            $previous_drive_link = $lead->drive_link;

            // Update the drive link
            $lead->drive_link = $request->drive_link;
            $lead->save();

            // Log activity for the drive link update
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Lead Drive Link Updated',
                    'message' => 'Lead drive link has been updated from '.$previous_drive_link.' to '.$lead->drive_link,
                ]),
                'module_id' => $lead->id,
                'module_type' => 'lead',
                'notification_type' => 'Drive Link Updated',
            ];
            addLogActivity($data);

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead updated successfully.',
            ], 200);
        } else {
            // Return error response if the lead is not found
            return response()->json([
                'status' => 'error',
                'message' => 'Lead not found',
            ], 404);
        }
    }

    public function CreateOrUpdateLeadNotes(Request $request)
    {
        // Validate the input
        $validator = Validator::make(
            $request->all(),
            [
                'description' => 'required|string',
                'lead_id' => 'required|exists:leads,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        $lead_id = $request->lead_id;

        if ($request->note_id) {
            // Update existing note
            $note = LeadNote::find($request->note_id);

            if ($note) {
                $note->description = $request->description;
                $note->save();

                // Log activity
                $data = [
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Lead Notes Updated',
                        'message' => 'Lead notes updated successfully',
                    ]),
                    'module_id' => $lead_id,
                    'module_type' => 'lead',
                    'notification_type' => 'Lead Notes Updated',
                ];
                addLogActivity($data);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Notes updated successfully'),
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Note not found',
                ], 404);
            }
        } else {
            // Create new note
            $note = new LeadNote;
            $note->description = $request->description;
            $note->created_by = Session::get('auth_type_id') ?: \Auth::user()->id;
            $note->lead_id = $lead_id;
            $note->save();

            // Log activity
            $data = [
                'type' => 'success',
                'note' => json_encode([
                    'title' => 'Notes Created',
                    'message' => 'Notes created successfully',
                ]),
                'module_id' => $lead_id,
                'module_type' => 'lead',
                'notification_type' => 'Notes Created',
            ];
            addLogActivity($data);

            return response()->json([
                'status' => 'success',
                'message' => __('Notes added successfully'),
            ]);
        }
    }

    public function GetLeadNotes(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
            ]
        );
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }
        $lead_id = $request->lead_id;
        $notesQuery = \App\Models\LeadNote::with('author')->where('lead_id', $lead_id);
        $userType = \Auth::user()->type;
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // No additional filtering needed
        } elseif ($userType === 'company') {
            $notesQuery->whereIn('created_by', getAllEmployees()->keys()->toArray());
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $notesQuery->whereIn('created_by', getAllEmployees()->keys()->toArray());
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && ! empty(\Auth::user()->region_id)) {
            $notesQuery->whereIn('created_by', getAllEmployees()->keys()->toArray());
        } elseif ($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer', 'Careers Consultant']) || (\Auth::user()->can('level 4') && ! empty(\Auth::user()->branch_id))) {
            $notesQuery->whereIn('created_by', getAllEmployees()->keys()->toArray());
        } else {
            $notesQuery->where('created_by', \Auth::user()->id); // Updated 'user_id' to 'created_by'
        }

        $notes = $notesQuery->orderBy('created_at', 'DESC')
            ->get()->map(function ($discussion) {
                return [
                    'id' => $discussion->id,
                    'text' => htmlspecialchars_decode($discussion->description),
                    'author' => $discussion?->author?->name,
                    'time' => $discussion->created_at->diffForHumans(),
                    'pinned' => false, // Default value as per the requirement
                    'timestamp' => $discussion->created_at->toISOString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $notes,
        ], 201);
    }

    public function DeleteLeadNotes(Request $request)
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
        $discussions = \App\Models\LeadNote::find($request->id);

         // Log activity
                $data = [
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => 'Lead Notes deleted',
                        'message' => 'Lead notes deleted successfully',
                    ]),
                    'module_id' => $discussions->lead_id,
                    'module_type' => 'lead',
                    'notification_type' => 'Lead Notes Updated',
                ];
                addLogActivity($data);
        if (! empty($discussions)) {
            $discussions->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Lead Note deleted!'),
        ], 201);
    }

    public function StageHistory(Request $request)
    {
        $notes = StageHistory::where('type', $request->type)->where('type_id', $request->id)->pluck('stage_id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $notes,
        ], 201);
    }

    public function EmailMarketing(Request $request)
    {
        if (\Auth::user()->type == 'Agent') {
            return response()->json(['error' => 'Permission Denied.'], 403);
        }

        $usr = \Auth::user();
        $id = $request->templateID ?? 0;
        $lang = $request->lang ?? 'en';
        $executed_data = $this->EmailMarketingLeadQuery();

        // $leadEmails = $executed_data['leadEmails'];
        $leadIds = $executed_data['leadIds'];
        $total_records = $executed_data['total_records'];
        $total_pages = $executed_data['total_records'];
        $num_results_on_page = $executed_data['num_results_on_page'];

        $stages = LeadStage::get();
        $saved_filters = SavedFilter::where('created_by', \Auth::id())->where('module', 'leads')->get();
        $branch_query = Branch::select(['branches.*']);
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->type == 'HR') {
        } elseif (\Auth::user()->type == 'company') {
            $branch_query->where('brands', \Auth::user()->id);
        } else {
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);
            $branch_query->whereIn('brands', $brand_ids);
        }

        if (\Auth::user()->type == 'Region Manager') {
            $branch_query->where('region_id', \Auth::user()->region_id);
        }

        // Filters
        if (isset($_GET['brand_id']) && ! empty($_GET['brand_id'])) {
            $branch_query->where('brands', $_GET['brand_id']);
        }
        if (isset($_GET['region_id']) && ! empty($_GET['region_id'])) {
            $branch_query->where('region_id', $_GET['region_id']);
        }

        $initial_emails = [
            01 => \Auth::user()->email,
        ];
        $emails = $branch_query->whereNotNull('email')->pluck('email', 'id')->toArray();
        $Allemails = array_merge($initial_emails, $emails);

        // Email Templates
        $leads_query = EmailTemplate::query();
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team') {
            $leads_query->where('status', '1')->orWhere('status', '0');
        } else {
            $leads_query->where('status', '1');
        }

        if (
            $usr->can('view lead') ||
            $usr->can('manage lead') ||
            \Auth::user()->type == 'super admin' ||
            \Auth::user()->type == 'Admin Team'
        ) {
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);
            $userType = \Auth::user()->type;

            if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            } elseif ($userType === 'company') {
                $leads_query->where('brand_id', \Auth::user()->id);
            } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
                $leads_query->whereIn('brand_id', $brand_ids);
            } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && ! empty(\Auth::user()->region_id)) {
                $leads_query->where('region_id', \Auth::user()->region_id);
            } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || (\Auth::user()->can('level 4') && ! empty(\Auth::user()->branch_id))) {
                $leads_query->where('branch_id', \Auth::user()->branch_id);
            } else {
                $leads_query->where('created_by', \Auth::user()->id);
            }

            $leads_query->where('id', $id)->orderBy('name', 'asc');
        }

        $EmailTemplates = $leads_query->get();

        if ($id != 0 && $EmailTemplates->count() < 1) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        $languages = Utility::languages();
        $emailTemplate = EmailTemplate::first();
        $currEmailTempLang = EmailTemplateLang::where('parent_id', '=', $id)->where('lang', $lang)->first();

        if (! isset($currEmailTempLang) || empty($currEmailTempLang)) {
            $currEmailTempLang = EmailTemplateLang::where('lang', $lang)->first();
            if (empty($currEmailTempLang)) {
                return response()->json(['error' => 'This Email Template Lang Not Exist.'], 404);
            }
            $currEmailTempLang->lang = $lang;
        }

        $emailTemplate = EmailTemplate::where('id', '=', $id)->first();

        $leads_query = EmailTemplate::query();
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team') {
            $leads_query->where('status', '1')->orWhere('status', '0');
        } else {
            $leads_query->where('status', '1');
        }

        if (
            $usr->can('view lead') ||
            $usr->can('manage lead') ||
            \Auth::user()->type == 'super admin' ||
            \Auth::user()->type == 'Admin Team'
        ) {
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);
            $userType = \Auth::user()->type;

            if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            } elseif ($userType === 'company') {
                $leads_query->where('brand_id', \Auth::user()->id);
            } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
                $leads_query->whereIn('brand_id', $brand_ids);
            } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && ! empty(\Auth::user()->region_id)) {
                $leads_query->where('region_id', \Auth::user()->region_id);
            } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || (\Auth::user()->can('level 4') && ! empty(\Auth::user()->branch_id))) {
                $leads_query->where('branch_id', \Auth::user()->branch_id);
            } else {
                $leads_query->where('created_by', \Auth::user()->id);
            }

            $leads_query->orderBy('name', 'asc');
        }

        $EmailTemplates = $leads_query->where('type', 'lead')->get();

        // ✅ Return JSON for API
        return response()->json([
            'status' => 'success',
            // 'leadEmails' => $leadEmails,
            'emailTemplate' => $emailTemplate,
            'languages' => $languages,
            'currEmailTempLang' => $currEmailTempLang,
            'EmailTemplates' => $EmailTemplates,
            'saved_filters' => $saved_filters,
            'leadIds' => $leadIds,
            'total_records' => $total_records,
            'Allemails' => $Allemails,
        ]);
    }

    private function ApplicationFilters()
    {
        $filters = [];
        if (isset($_GET['applications']) && ! empty($_GET['applications'])) {
            $filters['name'] = $_GET['applications'];
        }

        if (isset($_GET['stages']) && ! empty($_GET['stages'])) {
            $filters['stage_id'] = $_GET['stages'];
        }

        if (isset($_GET['created_by']) && ! empty($_GET['created_by'])) {
            $filters['created_by'] = $_GET['created_by'];
        }

        if (isset($_GET['universities']) && ! empty($_GET['universities'])) {
            $filters['university_id'] = $_GET['universities'];
        }

        if (isset($_GET['brand']) && ! empty($_GET['brand'])) {
            $filters['brand'] = $_GET['brand'];
        }

        if (isset($_GET['region_id']) && ! empty($_GET['region_id'])) {
            $filters['region_id'] = $_GET['region_id'];
        }

        if (isset($_GET['branch_id']) && ! empty($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
        }

        if (isset($_GET['created_at_from']) && ! empty($_GET['created_at_from'])) {
            $filters['created_at_from'] = $_GET['created_at_from'];
        }

        if (isset($_GET['created_at_to']) && ! empty($_GET['created_at_to'])) {
            $filters['created_at_to'] = $_GET['created_at_to'];
        }

        if (isset($_GET['lead_assigned_user']) && ! empty($_GET['lead_assigned_user'])) {
            $filters['assigned_to'] = $_GET['lead_assigned_user'];
        }
        if (isset($_GET['tag']) && ! empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }

        return $filters;
    }

    private function EmailMarketingLeadQuery()
    {
        $usr = \Auth::user();
        $start = 0;
        $num_results_on_page = ! empty($_POST) ? 100000000000 : 0;
        $filters = $this->leadsFilter();
        $pipeline = Pipeline::first();
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);
        if (! empty($_POST['type'])) {

            if (empty($_POST['type']) || $_POST['type'] == 'file_import') {
                $EmailMarkittingFileEmail = EmailMarkittingFileEmail::where('created_by', \Auth::id())->pluck('email', 'id')->toArray();
                // $leadEmails = array_values($EmailMarkittingFileEmail);

                $leadIds = implode(',', array_keys($EmailMarkittingFileEmail));

                return [
                    'total_records' => count($EmailMarkittingFileEmail),
                    // 'leadEmails' => $leadEmails,
                    'leadIds' => $leadIds,
                    'companies' => $companies,
                    'pipeline' => $pipeline,
                    'num_results_on_page' => $num_results_on_page,
                ];

            } elseif (empty($_POST['type']) || $_POST['type'] == 'agency') {
                $Agency_Email = Agency::query();

                $filters = $this->AgencyFilter();
                // dd($filters);
                foreach ($filters as $column => $value) {
                    if ($column === 'name') {
                        $Agency_Email->where('agencies.organization_name', 'LIKE', '%'.$value.'%');
                    } elseif ($column === 'phone') {
                        $Agency_Email->where('agencies.phone', 'LIKE', '%'.$value.'%');
                    } elseif ($column === 'email') {
                        $Agency_Email->where('agencies.organization_email', 'LIKE', '%'.$value.'%');
                    } elseif ($column === 'country') {
                        $Agency_Email->where('agencies.billing_country', 'like', '%'.Country::where('country_code', $value)->first()?->name.'%');
                    } elseif ($column === 'city') {
                        $Agency_Email->where('agencies.city', 'like', '%'.$value.'%');
                    } elseif ($column === 'tag') {
                        if (is_array($value)) {
                            $Agency_Email->where(function ($query) use ($value) {
                                foreach ($value as $tag) {
                                    $query->orWhereRaw('FIND_IN_SET(?, agencies.tag_ids)', [$tag]);
                                }
                            });
                        } else {
                            $Agency_Email->whereRaw('FIND_IN_SET(?, agencies.tag_ids)', [$value]);
                        }
                    }
                }
                $EmailMarkittingFileEmail = $Agency_Email->pluck('agencies.organization_email', 'agencies.id')->toArray();
                // $leadEmails = array_values($EmailMarkittingFileEmail);

                $leadIds = implode(',', array_keys($EmailMarkittingFileEmail));

                return [
                    'total_records' => count($EmailMarkittingFileEmail),
                    // 'leadEmails' => $leadEmails,
                    'leadIds' => $leadIds,
                    'companies' => $companies,
                    'pipeline' => $pipeline,
                    'num_results_on_page' => $num_results_on_page,
                ];

            } elseif (empty($_POST['type']) || $_POST['type'] == 'organization') {
                $organization_Email = User::select(['users.*'])->join('organizations', 'organizations.user_id', '=', 'users.id')->where('users.type', 'organization');
                $filters = $this->organizationsFilter();
                foreach ($filters as $column => $value) {
                    if ($column === 'name') {
                        $organization_Email->where('name', 'LIKE', '%'.$value.'%');
                    } elseif ($column === 'phone') {
                        $organization_Email->where('organizations.phone', 'LIKE', '%'.$value.'%');
                    } elseif ($column === 'street') {
                        $organization_Email->where('organizations.billing_street', 'LIKE', '%'.$value.'%');
                    } elseif ($column == 'city') {
                        $organization_Email->where('organizations.billing_city', 'LIKE', '%'.$value.'%');
                    } elseif ($column == 'state') {
                        $organization_Email->where('organizations.billing_state', 'LIKE', '%'.$value.'%');
                    } elseif ($column === 'country') {
                        $organization_Email->where('organizations.billing_country', Country::where('country_code', $value)->first()?->name);
                    }
                }
                $EmailMarkittingFileEmail = $organization_Email->pluck('users.email', 'users.id')->toArray();
                // $leadEmails = array_values($EmailMarkittingFileEmail);

                $leadIds = implode(',', array_keys($EmailMarkittingFileEmail));

                return [
                    'total_records' => count($EmailMarkittingFileEmail),
                    // 'leadEmails' => $leadEmails,
                    'leadIds' => $leadIds,
                    'companies' => $companies,
                    'pipeline' => $pipeline,
                    'num_results_on_page' => $num_results_on_page,
                ];

            }if (empty($_POST['type']) || $_POST['type'] == 'applications') {

                $user = \Auth::user();
                $companies = FiltersBrands();
                $brand_ids = array_keys($companies);

                $where = [];
                $params = [];

                /* --------------------------------
                BASE RAW QUERY
                -----------------------------------*/
                $sql = '
                    SELECT deal_applications.*
                    FROM deal_applications
                    INNER JOIN deals ON deals.id = deal_applications.deal_id
                    LEFT JOIN leads ON leads.is_converted = deal_applications.deal_id
                    WHERE 1 = 1
                    AND deal_applications.contact_id IS NOT NULL
                ';

                /* --------------------------------
                USER PERMISSION SYSTEM
                -----------------------------------*/
                if (! (
                    $user->type == 'super admin' ||
                    $user->type == 'Admin Team' ||
                    $user->can('level 1')
                )) {

                    if ($user->type == 'company') {
                        $where[] = 'deals.brand_id = ?';
                        $params[] = $user->id;

                    } elseif ($user->type == 'Project Director' ||
                            $user->type == 'Project Manager' ||
                            $user->can('level 2')) {

                        if (! empty($brand_ids)) {
                            $where[] = 'deals.brand_id IN ('.implode(',', array_fill(0, count($brand_ids), '?')).')';
                            $params = array_merge($params, $brand_ids);
                        }

                    } elseif (($user->type == 'Region Manager' || $user->can('level 3')) &&
                            ! empty($user->region_id)) {

                        $where[] = 'deals.region_id = ?';
                        $params[] = $user->region_id;

                    } elseif (($user->type == 'Branch Manager' ||
                            $user->type == 'Admissions Officer' ||
                            $user->type == 'Careers Consultant' ||
                            $user->type == 'Admissions Manager' ||
                            $user->type == 'Marketing Officer' ||
                            $user->can('level 4')) &&
                            ! empty($user->branch_id)) {

                        $where[] = 'deals.branch_id = ?';
                        $params[] = $user->branch_id;

                    } elseif ($user->type === 'Agent') {

                        $where[] = '(deals.assigned_to = ? OR deals.created_by = ?)';
                        $params[] = $user->id;
                        $params[] = $user->id;

                    } else {
                        $where[] = 'deals.assigned_to = ?';
                        $params[] = $user->id;
                    }
                }

                /* --------------------------------
                    PAYLOAD FILTERS
                -----------------------------------*/

                if (! empty($_POST['brandId'])) {
                    $where[] = 'deals.brand_id = ?';
                    $params[] = $_POST['brandId'];
                }

                if (! empty($_POST['regionId'])) {
                    $where[] = 'deals.region_id = ?';
                    $params[] = $_POST['regionId'];
                }

                if (! empty($_POST['branchId'])) {
                    $where[] = 'deals.branch_id = ?';
                    $params[] = $_POST['branchId'];
                }

                if (! empty($_POST['employeesId'])) {
                    $where[] = 'deals.assigned_to = ?';
                    $params[] = $_POST['employeesId'];
                }

                if (! empty($_POST['title'])) {
                    $where[] = 'deal_applications.name LIKE ?';
                    $params[] = "%{$_POST['title']}%";
                }

                if (! empty($_POST['previousUniversityId'])) {
                    $where[] = 'deal_applications.university_id = ?';
                    $params[] = $_POST['previousUniversityId'];
                }

                if (! empty($_POST['taskType'])) {
                    $where[] = 'deal_applications.stage_id = ?';
                    $params[] = $_POST['taskType'];
                }

                if (! empty($_POST['createdAtFrom'])) {
                    $where[] = 'DATE(deal_applications.created_at) >= ?';
                    $params[] = $_POST['createdAtFrom'];
                }

                if (! empty($_POST['createdAtTo'])) {
                    $where[] = 'DATE(deal_applications.created_at) <= ?';
                    $params[] = $_POST['createdAtTo'];
                }

                // if (!empty($_POST['dueDate'])) {
                //     $where[] = "DATE(deal_applications.due_date) = ?";
                //     $params[] = $_POST['dueDate'];
                // }

                if (! empty($_POST['tag'])) {
                    $where[] = 'FIND_IN_SET(?, deal_applications.tag_ids)';
                    $params[] = $_POST['tag'];
                }

                /* --------------------------------
                ADD FILTERS TO SQL
                -----------------------------------*/
                if (! empty($where)) {
                    $sql .= ' AND '.implode(' AND ', $where);
                }

                /* --------------------------------
                SEARCH BAR
                -----------------------------------*/
                if (request()->ajax() && request()->filled('search')) {

                    $search = request()->get('search');

                    if (str_starts_with($search, 'APC')) {
                        $numericId = preg_replace('/^[A-Z]+/', '', $search);

                        $sql .= ' AND deal_applications.id = ?';
                        $params[] = $numericId;

                    } else {
                        $sql .= ' AND (
                            deal_applications.name LIKE ?
                            OR deal_applications.application_key LIKE ?
                            OR deal_applications.course LIKE ?
                        )';

                        $params[] = "%$search%";
                        $params[] = "%$search%";
                        $params[] = "%$search%";
                    }
                }

                /* --------------------------------
                ORDER & PAGINATION
                -----------------------------------*/
                $sql .= ' ORDER BY deal_applications.created_at DESC LIMIT ?, ?';

                $params[] = $start;
                $params[] = $num_results_on_page;

                /* --------------------------------
                EXECUTE SQL
                -----------------------------------*/
                $applications = DB::select($sql, $params);

                $appIds = array_column($applications, 'contact_id');
                $appIdsString = implode(',', $appIds);

                return [
                    'total_records' => count($appIds),
                    'leadIds' => $appIdsString,
                    'companies' => $companies,
                    'pipeline' => $stages ?? [],
                    'num_results_on_page' => $num_results_on_page,
                ];
            } elseif (empty($_POST['type']) || $_POST['type'] == 'lead') {
                if ($usr->can('view lead') || $usr->can('manage lead') ||
                \Auth::user()->type == 'super admin' ||
                \Auth::user()->type == 'Admin Team') {

                    // --- Base query ---
                    $sql = 'SELECT leads.id, leads.email FROM leads WHERE 1=1';
                    $bindings = [];

                    // --- Assigned / Unassigned ---
                    if (! empty($_POST['Assigned'])) {
                        $sql .= ' AND leads.user_id IS NOT NULL';
                    }
                    if (! empty($_POST['Unassigned'])) {
                        $sql .= ' AND leads.user_id IS NULL';
                    }

                    // --- User Type filters ---
                    $userType = \Auth::user()->type;
                    if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
                        // No additional filtering
                    } elseif ($userType === 'company') {
                        $sql .= ' AND leads.brand_id = ?';
                        $bindings[] = \Auth::user()->id;
                    } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
                        if (! empty($brand_ids)) {
                            $sql .= ' AND leads.brand_id IN ('.implode(',', array_fill(0, count($brand_ids), '?')).')';
                            $bindings = array_merge($bindings, $brand_ids);
                        }
                    } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && ! empty(\Auth::user()->region_id)) {
                        $sql .= ' AND leads.region_id = ?';
                        $bindings[] = \Auth::user()->region_id;
                    } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer']))
                        || \Auth::user()->can('level 4') && ! empty(\Auth::user()->branch_id)) {
                        $sql .= ' AND leads.branch_id = ?';
                        $bindings[] = \Auth::user()->branch_id;
                    } elseif ($userType === 'Agent') {
                        $Agency = DB::table('agency')->where('user_id', \Auth::user()->id)->first();
                        if ($Agency) {
                            $sql .= ' AND (organization_link = ? OR user_id = ? OR leads.created_by = ?)';
                            array_push($bindings, $Agency->id, \Auth::user()->id, \Auth::user()->id);
                        } else {
                            $sql .= ' AND (user_id = ? OR leads.created_by = ?)';
                            array_push($bindings, \Auth::user()->id, \Auth::user()->id);
                        }
                    } else {
                        $sql .= ' AND leads.user_id = ?';
                        $bindings[] = \Auth::user()->id;
                    }

                    // --- Dynamic Filters ---
                    // dd($filters);
                    foreach ($filters as $column => $value) {
                        switch ($column) {
                            case 'name':
                                $sql .= ' AND leads.id IN ('.implode(',', array_fill(0, count($value), '?')).')';
                                $bindings = array_merge($bindings, $value);
                                break;
                            case 'brand_id':
                                $sql .= ' AND leads.brand_id = ?';
                                $bindings[] = $value;
                                break;
                            case 'region_id':
                                $sql .= ' AND leads.region_id = ?';
                                $bindings[] = $value;
                                break;
                            case 'branch_id':
                                $sql .= ' AND leads.branch_id = ?';
                                $bindings[] = $value;
                                break;
                            case 'stage_id':
                                $sql .= ' AND leads.stage_id = ?';
                                $bindings[] = $value;
                                break;
                            case 'lead_assigned_user':
                                if ($value == null) {
                                    $sql .= ' AND leads.user_id IS NULL';
                                } else {
                                    $sql .= ' AND leads.user_id = ?';
                                    $bindings[] = $value;
                                }
                                break;
                            case 'users':
                                $sql .= ' AND leads.user_id IN ('.implode(',', array_fill(0, count($value), '?')).')';
                                $bindings = array_merge($bindings, $value);
                                break;
                            case 'created_at_from':
                                $sql .= ' AND DATE(leads.created_at) >= ?';
                                $bindings[] = $value;
                                break;
                            case 'created_at_to':
                                $sql .= ' AND DATE(leads.created_at) <= ?';
                                $bindings[] = $value;
                                break;
                            case 'tag':
                                $sql .= ' AND FIND_IN_SET(?, leads.tag_ids)';
                                $bindings[] = $value;
                                break;
                        }
                    }

                    // --- Default filters ---
                    if (empty($_POST['stages']) || (! in_array('6', $_POST['stages']) && ! in_array('7', $_POST['stages']))) {
                        $sql .= ' AND leads.stage_id NOT IN (6,7) AND leads.is_converted = 0';
                    }

                    // --- Execute fast raw SQL query ---
                    $leads = DB::select($sql, $bindings);

                    $leadIds = array_column($leads, 'id');
                    $leadIdsString = implode(',', $leadIds);

                    return [
                        'total_records' => count($leads),
                        'leadIds' => $leadIdsString,
                        'companies' => $companies ?? [],
                        'pipeline' => $pipeline ?? [],
                        'num_results_on_page' => $num_results_on_page ?? 0,
                    ];
                }

            } else {
                return [
                    'total_records' => 0,
                    'leadEmails' => [''],
                    'leadIds' => ',',
                    'companies' => $companies,
                    'pipeline' => $pipeline,
                    'num_results_on_page' => $num_results_on_page,
                ];
            }
        } else {
            return [
                'total_records' => 0,
                'leadEmails' => [''],
                'leadIds' => ',',
                'companies' => $companies,
                'pipeline' => $pipeline,
                'num_results_on_page' => $num_results_on_page,
            ];
        }

    }

    private function AgencyFilter()
    {
        $filters = [];
        if (isset($_POST['Name']) && ! empty($_POST['Name'])) {
            $filters['name'] = $_POST['Name'];
        }

        if (isset($_POST['sector']) && ! empty($_POST['sector'])) {
            $filters['email'] = $_POST['sector'];
        }

        if (isset($_POST['agencyphone']) && ! empty($_POST['agencyphone'])) {
            $filters['phone'] = $_POST['agencyphone'];
        }

        if (isset($_POST['countryId']) && ! empty($_POST['countryId'])) {
            $filters['country'] = $_POST['countryId'];
        }

        if (isset($_POST['cityId']) && ! empty($_POST['cityId'])) {
            $filters['city'] = $_POST['cityId'];
        }
        if (isset($_POST['brand_id']) && ! empty($_POST['brand_id'])) {
            $filters['brand_id'] = $_POST['brand_id'];
        }
        if (isset($_POST['tag']) && ! empty($_POST['tag'])) {
            $filters['tag'] = $_POST['tag'];
        }

        return $filters;
    }

    private function organizationsFilter()
    {
        $filters = [];
        if (isset($_POST['Name']) && ! empty($_POST['Name'])) {
            $filters['name'] = $_POST['Name'];
        }

        if (isset($_POST['Phone']) && ! empty($_POST['Phone'])) {
            $filters['phone'] = $_POST['Phone'];
        }

        if (isset($_POST['Street']) && ! empty($_POST['Street'])) {
            $filters['street'] = $_POST['Street'];
        }

        if (isset($_POST['State']) && ! empty($_POST['State'])) {
            $filters['state'] = $_POST['State'];
        }

        if (isset($_POST['cityId']) && ! empty($_POST['cityId'])) {
            $filters['city'] = $_POST['cityId'];
        }

        if (isset($_POST['countryId']) && ! empty($_POST['countryId'])) {
            $filters['country'] = $_POST['countryId'];
        }

        return $filters;
    }

    private function leadsFilter()
    {
        $filters = [];
        if (isset($_POST['name']) && ! empty($_POST['name'])) {
            $filters['name'] = $_POST['name'];
        }
        if (isset($_POST['CreatedById']) && ! empty($_POST['CreatedById'])) {
            $filters['created_by'] = $_POST['CreatedById'];
        }

        if (isset($_POST['StagesId']) && ! empty($_POST['StagesId'])) {
            $filters['stage_id'] = $_POST['StagesId'];
        }

        if (isset($_POST['users']) && ! empty($_POST['users'])) {
            $filters['users'] = $_POST['users'];
        }

        if (isset($_POST['EmployeesId']) && ! empty($_POST['EmployeesId'])) {
            $filters['lead_assigned_user'] = $_POST['EmployeesId'];
        }

        if (isset($_POST['subject']) && ! empty($_POST['subject'])) {
            $filters['subject'] = $_POST['subject'];
        }

        // if(isset($_POST['lead_assigned_user']) && !empty($_POST['lead_assigned_user']) && $_POST['lead_assigned_user'] != 'null'){
        if (isset($_POST['brandId']) && ! empty($_POST['brandId'])) {
            $filters['brand_id'] = $_POST['brandId'];
        }

        if (isset($_POST['regionId']) && ! empty($_POST['regionId'])) {
            $filters['region_id'] = $_POST['regionId'];
        }

        if (isset($_POST['branchId']) && ! empty($_POST['branchId'])) {
            $filters['branch_id'] = $_POST['branchId'];
        }
        if (isset($_POST['TagsId']) && ! empty($_POST['TagsId'])) {
            $filters['tag'] = $_POST['TagsId'];
        }
        // }
        if (isset($_POST['lead_assigned_user']) && $_POST['lead_assigned_user'] == 'null') {
            unset($filters['brand_id']);
            unset($filters['region_id']);
            unset($filters['branch_id']);
        }

        if (isset($_POST['StartDate']) && ! empty($_POST['StartDate'])) {
            $filters['created_at_from'] = $_POST['StartDate'];
        }

        if (isset($_POST['EndDate']) && ! empty($_POST['EndDate'])) {
            $filters['created_at_to'] = $_POST['EndDate'];
        }

        return $filters;
    }

    public function leadsKanban(Request $request)
    {
        $usr = auth()->user();

        /**
         * 1. GET STAGES (RAW QUERY)
         */
        $stages = DB::select('SELECT id, name FROM lead_stages');

        // Add fixed colors manually
        $colors = [
            1 => ['#4F46E5', '#eef2ff'], // New Leads
            2 => ['#F59E0B', '#fff7ed'], // Contacted
            3 => ['#22C55E', '#f0fdf4'], // Document Received
            4 => ['#EC928E', '#fef2f2'], // Advised
            5 => ['#0EA5E9', '#e0f2fe'], // Follow-up
            6 => ['#6B7280', '#f3f4f6'], // Unqualified
        ];

        foreach ($stages as $stage) {
            $stage->color = $colors[$stage->id][0] ?? '#000';
            $stage->bg_color = $colors[$stage->id][1] ?? '#fff';
        }

        /**
         * 2. BUILD RAW SQL FOR LEADS
         */
        $sql = '
        SELECT id, name, phone, city, stage_id, created_at
        FROM leads
        WHERE is_converted = 0
    ';

        $params = [];

        /** 3. PERMISSION LOGIC */
        if ($usr->type == 'company') {
            $sql .= ' AND brand_id = ? ';
            $params[] = $usr->id;
        } elseif ($usr->type == 'Region Manager') {
            $sql .= ' AND region_id = ? ';
            $params[] = $usr->region_id;
        } elseif ($usr->type == 'Branch Manager') {
            $sql .= ' AND branch_id = ? ';
            $params[] = $usr->branch_id;
        } elseif ($usr->type == 'Agent') {
            $sql .= ' AND user_id = ? ';
            $params[] = $usr->id;
        }

        /** 4. SEARCH FILTER */
        if ($request->filled('search')) {
            $sql .= ' AND (name LIKE ? OR phone LIKE ?) ';
            $params[] = "%{$request->search}%";
            $params[] = "%{$request->search}%";
        }

        /** 5. APPLY LIMIT (FIRST 50 RECORDS ONLY) */
        $sql .= ' LIMIT 50 ';

        /** 6. GET LEADS (RAW QUERY) */
        $leads = DB::select($sql, $params);

        /** 7. GROUP INTO KANBAN FORMAT */
        $kanban = [];

        foreach ($stages as $stage) {

            $stageLeads = array_values(array_filter($leads, function ($lead) use ($stage) {
                return $lead->stage_id == $stage->id;
            }));

            $formattedLeads = array_map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'city' => $lead->city,
                    'leadAge' => \Carbon\Carbon::parse($lead->created_at)->diffForHumans(),
                ];
            }, $stageLeads);

            $kanban[] = [
                'stage_id' => $stage->id,
                'title' => $stage->name,
                'count' => count($formattedLeads),
                'color' => $stage->color,
                'bgColor' => $stage->bg_color,
                'leads' => $formattedLeads,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $kanban,
        ]);
    }

        public function getHistoryStageDays(Request $request)
        {



                $validator = Validator::make($request->all(), [

                    'type_id' => 'required|integer|min:1',
                    'type' => 'required|string',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'errors' => $validator->errors(),
                    ], 422);
                }


            $leadId = $request->input('type_id');
            $type = $request->input('type');

            if ($type !== 'lead' && $type !== 'deal') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid type provided. Must be either "lead" or "deal".',
                ], 400);
            }

              if ($type === 'lead') {
                $stages = LeadStage::orderBy('id')->get();
            } else if ($type === 'deal') {

                $stages = Stage::orderBy('id')->get();
            }




            // 2. Get stage history for the lead
            $histories = StageHistory::where('type', $type)
                ->where('type_id', $leadId)
                ->orderBy('created_at')
                ->get()
                ->values(); // reset index

            $result = [];

            foreach ($stages as $stage) {

                // Find stage in history
                $index = $histories->search(fn ($h) => $h->stage_id == $stage->id);

                // Stage skipped
                if ($index === false) {
                    $result[] = [
                        'stage_id'   => $stage->id,
                        'stage_name' => $stage->name,
                        'days'       => 0,
                    ];
                    continue;
                }

                $current = $histories[$index];
                $next = $histories[$index + 1] ?? null;

                // Calculate days
                $days = $next
                    ? Carbon::parse($current->created_at)->diffInDays(Carbon::parse($next->created_at))
                    : 0; // last stage → keep 0 (or use now())

                $result[$stage->id] = [
                    'stage_id'   => $stage->id,
                    'stage_name' => $stage->name,
                    'days'       => $days,
                ];
            }

            return $result;
        }

}
