<?php

namespace App\Http\Controllers;

use Session;
use Illuminate\Support\Facades\Auth;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Models\Label;
use App\Models\Stage;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Region;
use App\Models\Source;
use App\Models\Country;
use App\Models\Utility;
use App\Models\DealCall;
use App\Models\DealFile;
use App\Models\DealNote;
use App\Models\DealTask;
use App\Models\Pipeline;
use App\Models\UserDeal;
use App\Models\DealEmail;
use App\Models\ClientDeal;
use App\Models\University;
use App\Mail\SendDealEmail;
use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\SavedFilter;
use App\Models\Notification;
use App\Models\StageHistory;
use Illuminate\Http\Request;
use App\Models\DealDiscussion;
use App\Models\ProductService;
use App\Models\TaskDiscussion;
use App\Events\NewNotification;
use App\Models\Agency;
use App\Models\DealApplication;
use App\Models\ApplicationStage;
use App\Models\ClientPermission;
use App\Models\CompanyPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\ApplicationNote;
use App\Models\AdmissionView;
use App\Models\City;
use App\Models\instalment;
use App\Models\Institute;
use App\Models\LeadTag;
use App\Models\Meta;
use App\Models\TaskTag;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Validator;

class DealController extends Controller
{

    private function dealFilters()
    {
        $filters = [];
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $filters['name'] = $_POST['name'];
        }

        if (isset($_POST['brand_id']) && !empty($_POST['brand_id'])) {
            $filters['brand_id'] = $_POST['brand_id'];
        }

        if (isset($_POST['region_id']) && !empty($_POST['region_id'])) {
            $filters['region_id'] = $_POST['region_id'];
        }

        if (isset($_POST['branch_id']) && !empty($_POST['branch_id'])) {
            $filters['branch_id'] = $_POST['branch_id'];
        }

        if (isset($_POST['lead_assigned_user']) && !empty($_POST['lead_assigned_user'])) {
            $filters['deal_assigned_user'] = $_POST['lead_assigned_user'];
        }


        if (isset($_POST['stages']) && !empty($_POST['stages'])) {
            $filters['stage_id'] = $_POST['stages'];
        }

        if (isset($_POST['users']) && !empty($_POST['users'])) {
            $filters['users'] = $_POST['users'];
        }

        if (isset($_POST['created_at_from']) && !empty($_POST['created_at_from'])) {
            $filters['created_at_from'] = $_POST['created_at_from'];
        }

        if (isset($_POST['created_at_to']) && !empty($_POST['created_at_to'])) {
            $filters['created_at_to'] = $_POST['created_at_to'];
        }
        if (isset($_POST['tag']) && !empty($_POST['tag'])) {
            $filters['tag'] = $_POST['tag'];
        }
        return $filters;
    }

    public function getAdmission(Request $request)
    {
        $user = Auth::user();

        if (!($user->can('view deal') || $user->can('manage deal') || in_array($user->type, ['super admin', 'company', 'Admin Team']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

        $query = Deal::select(
            'deals.id',
            'deals.name',
            'deals.created_by',
            'deals.stage_id',
            'deals.tag_ids',
            'deals.assigned_to',
            'deals.intake_month',
            'deals.intake_year',
            'sources.name as sources',
            'assignedUser.name as assigName',
            'clientUser.passport_number as passport',
        )->distinct()
            ->leftJoin('user_deals', 'user_deals.deal_id', '=', 'deals.id')
            ->leftJoin('sources', 'sources.id', '=', 'deals.sources')
            ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deals.assigned_to')
            ->leftJoin('client_deals', 'client_deals.deal_id', '=', 'deals.id')
            ->leftJoin('users as clientUser', 'clientUser.id', '=', 'client_deals.client_id')
            ->leftJoin('leads', 'leads.is_converted', '=', 'deals.id');

        // Permissions logic
        if (in_array($user->type, ['super admin', 'Admin Team']) || $user->can('level 1')) {
            // No filters applied
        } elseif ($user->type == 'company') {
            $query->where('brand_id', $user->id);
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $query->whereIn('brand_id', array_keys(FiltersBrands()));
        } elseif ($user->type == 'Region Manager' || ($user->can('level 3') && $user->region_id)) {
            $query->where('region_id', $user->region_id);
        } elseif (in_array($user->type, ['Branch Manager', 'Admissions Officer', 'Career Consultant', 'Admissions Manager', 'Marketing Officer']) || ($user->can('level 4') && $user->branch_id)) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->type == 'Agent') {
            $query->where('agent_id', $user->agent_id);
        } else {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        // Filters
        $filters = $this->dealFilters();
        foreach ($filters as $column => $value) {
            if ($column === 'name') {
                $query->where('deals.name', 'like', "%{$value}%");
            } elseif ($column === 'stage_id') {
                $query->where('deals.stage_id', $value);
            } elseif ($column == 'users') {
                $query->whereIn('deals.created_by', $value);
            } elseif ($column == 'created_at') {
                $query->whereDate('deals.created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
            } elseif ($column == 'brand') {
                $query->where('deals.brand_id', $value);
            } elseif ($column == 'region_id') {
                $query->where('deals.region_id', $value);
            } elseif ($column == 'branch_id') {
                $query->where('deals.branch_id', $value);
            } elseif ($column == 'deal_assigned_user') {
                $query->where('deals.assigned_to', $value);
            } else if ($column == 'created_at_from') {
                $query->whereDate('deals.created_at', '>=', $value);
            } else if ($column == 'created_at_to') {
                $query->whereDate('deals.created_at', '<=', $value);
            } else if ($column == 'tag') {
                $query->whereRaw('FIND_IN_SET(?, deals.tag_ids)', [$value]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('deal_title', 'like', "%{$search}%")
                    ->orWhere('deal_value', 'like', "%{$search}%")
                    ->orWhereHas('lead', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);



        $deals = $query
            ->orderByDesc('deals.id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $deals->items(),
            'current_page' => $deals->currentPage(),
            'last_page' => $deals->lastPage(),
            'total_records' => $deals->total(),
            'per_page' => $deals->perPage()
        ]);
    }
    public function getAdmissionByView(Request $request)
{
    $user = Auth::user();

    if (!($user->can('view deal') || $user->can('manage deal') || in_array($user->type, ['super admin', 'company', 'Admin Team']))) {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission Denied.',
        ], 403);
    }

    $view = $request->input('view', 'list');
    $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
    $page = $request->input('page', 1);

    $query = AdmissionView::query();

    // Permissions logic
    if (!in_array($user->type, ['super admin', 'Admin Team']) && !$user->can('level 1')) {
        if ($user->type == 'company') {
            $query->where('brand_id', $user->id);
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $query->whereIn('brand_id', array_keys(FiltersBrands()));
        } elseif ($user->type == 'Region Manager' && $user->region_id) {
            $query->where('region_id', $user->region_id);
        } elseif (in_array($user->type, ['Branch Manager', 'Admissions Officer', 'Career Consultant', 'Admissions Manager', 'Marketing Officer']) && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->type == 'Agent') {
            $query->where('agent_id', $user->agent_id);
        } else {
            $query->where('assigned_to', $user->id); // fallback
        }
    }

    // Filters from request
    $filters = $this->dealFilters();
    foreach ($filters as $column => $value) {
        if ($column === 'name') $query->where('name', 'like', "%{$value}%");
        elseif ($column === 'stage_id') $query->where('stage_id', $value);
        elseif ($column === 'users') $query->whereIn('created_by', $value);
        elseif ($column === 'created_at') $query->whereDate('created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
        elseif ($column === 'brand') $query->where('brand_id', $value);
        elseif ($column === 'region_id') $query->where('region_id', $value);
        elseif ($column === 'branch_id') $query->where('branch_id', $value);
        elseif ($column === 'deal_assigned_user') $query->where('assigned_to', $value);
        elseif ($column === 'created_at_from') $query->whereDate('created_at', '>=', $value);
        elseif ($column === 'created_at_to') $query->whereDate('created_at', '<=', $value);
        elseif ($column === 'tag') $query->whereRaw('FIND_IN_SET(?, tag_ids)', [$value]);
        elseif ($column === 'days_at_stage') {
            if ($value === '30+') $query->where('days_at_stage', '>=', 30);
            else $query->where('days_at_stage', '=', (int)$value);
        }
    }

    // fetcttype filter
    if ($request->filled('fetcttype')) {
        $type = $request->fetcttype;
        if ($type === 'youradmissions') $query->where('created_by', $user->id);
        if ($type === 'assigntome') $query->where('assigned_to', $user->id);
        if ($type === 'agentadmissions') $query->whereNotNull('agent_id');
        else $query->whereNull('agent_id');
    }

    // Search filter
    if ($request->filled('search')) {
        $search = $request->get('search');
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('price', 'like', "%{$search}%")
              ->orWhere('passport_number', 'like', "%{$search}%")
              ->orWhere('lead_name', 'like', "%{$search}%")
              ->orWhere('lead_email', 'like', "%{$search}%")
              ->orWhere('lead_phone', 'like', "%{$search}%");
        });
    }

    // CSV Export
    if ($request->input('download_csv')) {
        $dealsCsv = $query->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="admissions_'.time().'.csv"',
        ];
        $callback = function () use ($dealsCsv) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID','Name','Stage','Brand','Agent','Assigned','Client Passport','Lead Name','Lead Email','University','Price','Created At']);
            foreach ($dealsCsv as $deal) {
                fputcsv($file, [
                    $deal->id,
                    $deal->name,
                    $deal->stage_name,
                    $deal->brand_name,
                    $deal->agent_name,
                    $deal->assigned_user_name,
                    $deal->passport_number,
                    $deal->lead_name,
                    $deal->lead_email,
                    $deal->university_name,
                    $deal->price,
                    $deal->created_at,
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    // Kanban view
    if ($view === 'kanban') {
        $KANBAN_PER_PAGE = 1000;
        $deals = $query->orderBy('created_at', 'desc')->limit($KANBAN_PER_PAGE)->get();

        $stages = DB::table('stages')->select('id', 'name')->get();
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
            $stageDeals = $deals->where('stage_id', $stage->id)->values();
            $kanban[] = [
                'stage_id' => $stage->id,
                'title' => $stage->name,
                'count' => $stageDeals->count(),
                'color' => $colors[$stage->id][0] ?? '#000',
                'bgColor' => $colors[$stage->id][1] ?? '#fff',
                'deals' => $stageDeals->map(fn($deal) => [
                    'id' => $deal->id,
                    'name' => $deal->name,
                    'phone' => $deal->phone,
                    'price' => $deal->price,
                    'assigned_to' => $deal->assigned_user_name,
                    'brand' => $deal->brand_name,
                    'agent' => $deal->agent_name,
                    'client_passport' => $deal->passport_number,
                    'lead_name' => $deal->lead_name,
                ]),
            ];
        }

        return response()->json([
            'status' => 'success',
            'view' => 'kanban',
            'data' => $kanban,
            'total_records' => $deals->count(),
        ]);
    }

    // List view
    $deals = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

    return response()->json([
        'status' => 'success',
        'data' => $deals->items(),
        'current_page' => $deals->currentPage(),
        'last_page' => $deals->lastPage(),
        'total_records' => $deals->total(),
        'per_page' => $deals->perPage()
    ]);
}

    public function getAdmissionDetails(Request $request)
    {

        $user = Auth::user();

        if (!($user->can('view deal') || $user->can('manage deal') || in_array($user->type, ['super admin', 'company', 'Admin Team']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'deal_id' => 'required|exists:deals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $deal = Deal::with('clients')->where('id', $request->deal_id)->first();

         // Calculate Lead Stage Progress
            $stages  = Stage::orderBy('id')->get()->pluck('name', 'id')->toArray();


            // Fetch Related Data
            $tasks = DealTask::where([
                'related_to' => $deal->id,
                'related_type' => 'deal',
            ])->orderBy('status')->get();

            // $branches = Branch::pluck('name', 'id');
            // $users = allUsers();
            $logActivities = getLogActivity($deal->id, 'deal');

            // Lead Stage History
            $stageHistories = StageHistory::where('type', 'deal')
                ->where('type_id', $deal->id)
                ->pluck('stage_id')
                ->toArray();

            $applications = DealApplication::where('deal_id', $deal->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $deal,
            'stageHistories' => $stageHistories,
            'logActivities' => $logActivities,
            'tasks' => $tasks,
            'stages' => $stages,
            'applications' => $applications,
        ],200);

        if (!$deal->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Permission Denied.'], 403);
        }
    }
    public function getMoveApplicationPluck(Request $request)
    {

        $validator = \Validator::make($request->all(), [
            'passport_number' => 'required|string',
            'id' => 'required|integer|exists:deal_applications,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ]);
        }

        if (auth()->user()->type === 'super admin' || auth()->user()->type === 'Admin Team') {

            $admissions = \DB::table('deals')
                ->leftJoin('client_deals', 'client_deals.deal_id', '=', 'deals.id')
                ->leftJoin('users as clientUser', 'clientUser.id', '=', 'client_deals.client_id')
                ->leftJoin('users as brandUser', 'brandUser.id', '=', 'deals.brand_id')
                ->leftJoin('regions', 'regions.id', '=', 'deals.region_id')
                ->leftJoin('branches', 'branches.id', '=', 'deals.branch_id')
                ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deals.assigned_to')
                ->where('clientUser.passport_number', $request->passport_number)
                ->select(
                    'deals.id',
                    'deals.name',
                    'brandUser.name as brandName',
                    'regions.name as RegionName',
                    'branches.name as branchName',
                    'assignedUser.name as assignedName'
                )
                ->get();

            $pluckFormatted = $admissions->mapWithKeys(function ($admission) {
                $label = $admission->name . '-' . $admission->brandName . '-' . $admission->RegionName . '-' . $admission->branchName . '-' . $admission->assignedName;
                return [$admission->id => $label];
            });

            return response()->json([
                'status' => true,
                'data' => $pluckFormatted
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => __('Permission Denied.')
        ], 403);
    }

    public function moveApplicationsave(Request $request)
    {
        if (!in_array(\Auth::user()->type, ['super admin', 'Admin Team'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ]);
        }

        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:deal_applications,id',
            'deal_id' => 'required|exists:deals,id',
            'old_deal_id' => 'required|exists:deals,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ]);
        }

        if ($request->deal_id == $request->old_deal_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected deal already contains this application.',
            ]);
        }

        $oldApplication = DealApplication::where('id', $request->id)->first();

        if (!$oldApplication) {
            return response()->json([
                'status' => 'error',
                'message' => 'Original application not found.',
            ]);
        }

        // Duplicate Application
        $newApplication = new DealApplication();
        $newApplication->application_key = $oldApplication->application_key;
        $newApplication->university_id = $oldApplication->university_id;
        $newApplication->deal_id = $request->deal_id;
        $newApplication->course = $oldApplication->course;
        $newApplication->stage_id = $oldApplication->stage_id;
        $newApplication->name = $oldApplication->name;
        $newApplication->intake = $oldApplication->intake;
        $newApplication->external_app_id = $oldApplication->external_app_id;
        $newApplication->status = $oldApplication->status;
        $newApplication->created_by = $oldApplication->created_by;
        $newApplication->brand_id = $oldApplication->brand_id;
        $newApplication->created_at = $oldApplication->created_at;
        $newApplication->updated_at = $oldApplication->updated_at;
        $newApplication->save();

        // Clone notes
        $notes = ApplicationNote::where('application_id', $request->id)->get();
        foreach ($notes as $note) {
            $newNote = new ApplicationNote();
            $newNote->title = $note->title;
            $newNote->description = $note->description;
            $newNote->application_id = $newApplication->id;
            $newNote->created_by = $note->created_by;
            $newNote->created_at = $note->created_at;
            $newNote->updated_at = $note->updated_at;
            $newNote->save();
        }

        // Clone tasks
        $tasks = DealTask::where(['related_to' => $request->id, 'related_type' => 'application'])->get();
        foreach ($tasks as $task) {
            $newTask = new DealTask();
            $newTask->deal_id = $newApplication->id;
            $newTask->name = $task->name;
            $newTask->date = $task->date;
            $newTask->time = $task->time;
            $newTask->priority = $task->priority;
            $newTask->status = 1;
            $newTask->organization_id = $task->organization_id;
            $newTask->assigned_to = $task->assigned_to;
            $newTask->assigned_type = $task->assigned_type;
            $newTask->related_type = $task->related_type;
            $newTask->related_to = $newApplication->id;
            $newTask->branch_id = $task->branch_id;
            $newTask->due_date = $task->due_date;
            $newTask->start_date = $task->start_date;
            $newTask->remainder_date = $task->remainder_date;
            $newTask->description = $task->description;
            $newTask->visibility = $task->visibility;
            $newTask->deal_stage_id = $task->deal_stage_id;
            $newTask->created_by = $task->created_by;
            $newTask->brand_id = $task->brand_id;
            $newTask->region_id = $task->region_id;
            $newTask->created_at = $task->created_at;
            $newTask->updated_at = $task->updated_at;
            $newTask->save();
        }

        // Update stages
        $this->updateDealStageByDealId($request->deal_id);
        $this->updateDealStageByDealId($request->old_deal_id);

        // Delete old application
        $oldApplication->delete();

        // Compare old and new application fields
        $differences = [];
        $fieldsToCheck = [
            'application_key',
            'university_id',
            'deal_id',
            'course',
            'stage_id',
            'name',
            'intake',
            'external_app_id',
            'status',
            'created_by',
            'brand_id',
            'created_at',
            'updated_at'
        ];

        foreach ($fieldsToCheck as $field) {
            $oldValue = $oldApplication->$field;
            $newValue = $newApplication->$field;

            if ($oldValue != $newValue) {
                $differences[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Activity Log
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Application Moved',
                'message' => 'Application moved to another deal successfully.',
                'differences' => $differences,
            ]),
            'module_id' => $newApplication->id,
            'module_type' => 'application',
            'notification_type' => 'Application Moved',
        ]);

        return response()->json([
            'status' => 'success',
            'app_id' => $newApplication->id,
            'message' => __('Application moved successfully.'),
        ]);
    }

    private function updateDealStageByDealId(int $dealId): void
    {
        // Get the latest application based on the highest stage_id
        $latestApplication = DealApplication::where('deal_id', $dealId)
            ->orderByDesc('stage_id')
            ->first();

        // Retrieve the deal
        $deal = Deal::find($dealId);

        // If deal or application doesn't exist, stop
        if (!$deal || !$latestApplication) {
            return;
        }

        // Map application stage_id to deal stage_id
        $stageMap = [
            0 => 0,
            1 => 1,
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 3,
            6 => 3,
            7 => 4,
            8 => 4,
            9 => 5,
            10 => 5,
            11 => 6,
            12 => 7,
        ];

        $applicationStageId = $latestApplication->stage_id;
        $dealStageId = $stageMap[$applicationStageId] ?? 0;

        // Update the deal's stage_id
        $deal->stage_id = $dealStageId;
        $deal->save();
    }

        public function dealStageHistory(Request $request)
    {
        // Validate Input
        $validator = \Validator::make($request->all(), [
            'type' => 'required|string',
            'id'   => 'required|exists:deals,id',
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

   public function updateAdmission(Request $request)
{
    $user = Auth::user();

    // Permission check
    if (!$user->can('edit deal') && $user->type != 'super admin') {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission Denied.')
        ], 200);
    }

    // Validation
    $validator = Validator::make($request->all(), [
        'id' => 'required|exists:deals,id',
        'name' => 'required',
        'intake_month' => 'required',
        'intake_year' => 'required',
        'brand_id' => 'required|gt:0',
        'region_id' => 'required|gt:0',
        'lead_branch' => 'required|gt:0',
        'assigned_to' => 'required|exists:users,id',
        'pipeline_id' => 'required',
        'gender' => 'required',
        'nationality' => 'required',
        'date_of_birth' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    // Get Deal
    $deal = Deal::findOrFail($request->id);
    $originalData = $deal->toArray(); // before update

    // Check ownership permission (same logic preserved)
    if (!$user->can('edit deal') && $deal->created_by != $user->ownerId() && $user->type != 'super admin') {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission Denied.')
        ], 200);
    }

    // Get related user
    $user_who_have_password = User::whereIn('id', function ($query) use ($request) {
        $query->select('client_id')
            ->from('client_deals')
            ->where('deal_id', $request->id);
    })->first();

    // Passport validation (same logic preserved)
    if ($user_who_have_password) {
        $passportValidator = Validator::make($request->all(), [
            'passport_number' => 'required|unique:users,passport_number,' . $user_who_have_password->id,
        ]);

        if ($passportValidator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $passportValidator->errors()->first()
            ], 422);
        }
    }

    // Update Deal
    $deal->name  = $request->name;
    $deal->category = $request->input('category');
    $deal->university_id = $request->input('university_id');
    $deal->organization_id = $request->input('organization_id');
    $deal->phone = $request->input('lead_phone');
    $deal->brand_id = $request->input('brand_id');
    $deal->region_id = $request->input('region_id');
    $deal->branch_id = $request->input('lead_branch');
    $deal->assigned_to = $request->input('assigned_to');
    $deal->intake_month = $request->input('intake_month');
    $deal->intake_year = $request->input('intake_year');
    $deal->price = 0;
    $deal->pipeline_id = $request->input('pipeline_id');
    $deal->description = $request->input('deal_description');
    $deal->status = 'Active';
    $deal->created_by = $deal->created_by;
    $deal->save();

    // Update User
    if ($user_who_have_password) {
        $user_who_have_password->passport_number = $request->passport_number;
        $user_who_have_password->gender = $request->gender;
        $user_who_have_password->nationality = $request->nationality;
        $user_who_have_password->date_of_birth = $request->date_of_birth;
        $user_who_have_password->save();
    }

    // Update or Create Lead
    $lead = Lead::where('is_converted', $request->id)->first();

    if (!empty($lead)) {

        if (!empty($request->lead_email)) {
            $lead->email = $request->lead_email;
        }

        if (!empty($request->lead_phone)) {
            $lead->phone = $request->full_number;
        }

        $lead->save();

    } else {

        $lead = new Lead();
        $lead->title = $request->name;
        $lead->name = $request->name;
        $lead->email = $request->lead_email;
        $lead->phone = $request->full_number;
        $lead->mobile_phone = $request->full_number;
        $lead->branch_id = $request->lead_branch;
        $lead->brand_id = $request->brand_id;
        $lead->region_id = $request->region_id;
        $lead->organization_id = "--";
        $lead->organization_link = "--";
        $lead->sources = "--";
        $lead->referrer_email = $request->lead_email;
        $lead->street = "--";
        $lead->city = "--";
        $lead->state = "--";
        $lead->postal_code = "--";
        $lead->country = "--";
        $lead->keynotes = "--";
        $lead->tags = "--";
        $lead->stage_id = "1";
        $lead->subject = $request->name;
        $lead->user_id = $deal->assigned_to;
        $lead->tag_ids = "";
        $lead->pipeline_id = "1";
        $lead->created_by = $deal->created_by;
        $lead->date = date('Y-m-d');
        $lead->drive_link = "";
        $lead->is_converted = $deal->id;
        $lead->save();
    }

    // change tracking here
$changes = [];
$updatedFields = [];

foreach ($originalData as $field => $oldValue) {

    if (in_array($field, ['created_at', 'updated_at'])) {
        continue;
    }

    if ($deal->$field != $oldValue) {

        $changes[$field] = [
            'old' => $oldValue,
            'new' => $deal->$field
        ];

        $updatedFields[] = $field;
    }
}

if (!empty($changes)) {

    addLogActivity([
        'type' => 'info',
        'note' => json_encode([
            'title' => 'Deal updated: ' . $deal->name,
            'message' => 'Fields updated: ' . implode(', ', $updatedFields),
            'changes' => $changes
        ]),
        'module_id' => $deal->id,
        'module_type' => 'deal',
        'notification_type' => 'Deal Updated'
    ]);

}

    return response()->json([
        'status' => 'success',
        'deal' => $deal,
        'message' => __('Deal successfully updated!')
    ]);
}

 public function GetadmissionNotes(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'deal_id' => 'required|exists:deals,id',
            ]
        );
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }
        $deal_id = $request->deal_id;
        $notesQuery = \App\Models\DealNote::with('author')->where('deal_id', $deal_id);
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

       public function deleteAdmission(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:deals,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check Permission
        if (! \Auth::user()->can('delete deal') && \Auth::user()->type != 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find the Lead
        $deal = Deal::find($request->id);


        $applications = DealApplication::where('deal_id', $request->id)
                ->orderBy('stage_id', 'desc')
                ->get();


         if (count($applications) > 0) {
            return response()->json([
                'status' => 'error',
                'message' => __('The application created for this admission cannot be deleted.'),
            ], 403);
        }


        // Log the deletion
        $data = [
            'type' => 'warning',
            'note' => json_encode([
                'title' => $deal->name .' admission Deleted',
                'message' => $deal->name .' admission deleted successfully',
            ]),
            'module_id' => $deal->id,
            'module_type' => 'deal',
            'notification_type' => 'deal Deleted',
        ];
        addLogActivity($data);

        // Delete the deal
        $deal->delete();

        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => __('Admission successfully deleted!'),
        ], 200);
    }

}
