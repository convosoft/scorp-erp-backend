<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Utility;
use App\Models\Trainer;
use App\Models\SavedFilter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class LeaveController extends Controller
{
    public function getLeaves_old(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage leave')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'perPage'    => 'nullable|integer|min:1',
            'page'       => 'nullable|integer|min:1',
            'search'     => 'nullable|string',
            'brand_id'   => 'nullable|integer|exists:users,id',
            'region_id'  => 'nullable|integer|exists:regions,id',
            'branch_id'  => 'nullable|integer|exists:branches,id',
            'created_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Base query with necessary joins
        $query = Leave::select(
            'leaves.*',
            'users.name as username',
            'branches.name as branch_name',
            'brands.name as brand_name',
            'regions.name as region_name'
        )
            ->with(['brand', 'branch', 'region', 'created_by', 'leaveType', 'employees','User'])
            ->leftJoin('employees', 'employees.id', '=', 'leaves.employee_id')
            ->leftJoin('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
            ->leftJoin('users as brands', 'brands.id', '=', 'leaves.brand_id')
            ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id');

        // Apply role-based filtering
        $query = RoleBaseTableGet($query, 'leaves.brand_id', 'leaves.region_id', 'leaves.branch_id', 'leaves.created_by');

        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('users.name', 'like', "%$search%")
                    ->orWhere('employees.name', 'like', "%$search%")
                    ->orWhere('brands.name', 'like', "%$search%")
                    ->orWhere('regions.name', 'like', "%$search%")
                    ->orWhere('branches.name', 'like', "%$search%");
            });
        }

        // Apply additional filters
        if ($request->filled('brand_id')) {
            $query->where('leaves.brand_id', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $query->where('leaves.region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('leaves.branch_id', $request->branch_id);
        }
        if (
            $request->filled('employee_id') &&
            $request->employee_id !== 'undefined'
        ) {
            $query->where('leaves.employee_id', $request->employee_id);

        }
        if ($request->filled('created_at')) {
            $query->whereDate('leaves.created_at', substr($request->created_at, 0, 10));
        }

        // Apply sorting and pagination
        $leaves = $query->orderBy('leaves.created_at', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $leaves->items(),
            'current_page' => $leaves->currentPage(),
            'last_page' => $leaves->lastPage(),
            'total_records' => $leaves->total(),
            'per_page' => $leaves->perPage(),
        ], 200);
    }

    public function getDashboardLeaves(Request $request)
{
    
 

    // Pagination settings
    $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 25));
    $page = $request->input('page', 1);
     $today = now()->toDateString();


    // Base query with necessary joins
    $query = Leave::select(
        'leaves.*',
        'users.name as employee_name',
        'users.avatar as avatar',
    )
         
        ->leftJoin('users', 'users.id', '=', 'leaves.employee_id') 
        
         // ✅ Only employees on leave today
        ->whereDate('leaves.start_date', '<=', $today)
        ->whereDate('leaves.end_date', '>=', $today);

    // Apply role-based filtering
    $query = RoleBaseTableGet($query, 'leaves.brand_id', 'leaves.region_id', 'leaves.branch_id', 'leaves.created_by');

    
  
        
    // Apply sorting and pagination to the original query
    $leaves = $query->orderBy('leaves.created_at', 'DESC')
        ->paginate($perPage, ['*'], 'page', $page);

    // Return the paginated data
    return response()->json([
        'status' => 'success',
        '' => '$today',
        'data' => $leaves->items(),
        'current_page' => $leaves->currentPage(),
        'last_page' => $leaves->lastPage(),
        'total_records' => $leaves->total(),
        'per_page' => $leaves->perPage()
    ], 200);
}
public function getLeaves(Request $request)
{
    // Permission check
    if (!Auth::user()->can('manage leave')) {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission denied.')
        ], 403);
    }

    // Validate request parameters
    $validator = Validator::make($request->all(), [
        'perPage'    => 'nullable|integer|min:1',
        'page'       => 'nullable|integer|min:1',
        'search'     => 'nullable|string',
        'status'     => 'nullable|string',
        'brand_id'   => 'nullable|integer|exists:users,id',
        'region_id'  => 'nullable|integer|exists:regions,id',
        'branch_id'  => 'nullable|integer|exists:branches,id',
        'created_at' => 'nullable|date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    // Pagination settings
    $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
    $page = $request->input('page', 1);

    // Base query with necessary joins
    $query = Leave::select(
        'leaves.*',
        'users.name as username',
        'branches.name as branch_name',
        'brands.name as brand_name',
        'regions.name as region_name'
    )
          ->with([
        'brand:id,name',
        'branch:id,name',
        'region:id,name',
        'created_by:id,name',
        'leaveType',
        'employees',
        'User'
    ]) 
        ->leftJoin('users', 'users.id', '=', 'leaves.employee_id')
        ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
        ->leftJoin('users as brands', 'brands.id', '=', 'leaves.brand_id')
        ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id');

    // Apply role-based filtering
    $query = RoleBaseTableGet($query, 'leaves.brand_id', 'leaves.region_id', 'leaves.branch_id', 'leaves.created_by');

    $CountyCount = $query->count();
    
    // Apply search filter if provided
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where(function ($subQuery) use ($search) {
            $subQuery->where('users.name', 'like', "%$search%") 
                ->orWhere('brands.name', 'like', "%$search%")
                ->orWhere('regions.name', 'like', "%$search%")
                ->orWhere('branches.name', 'like', "%$search%");
        });
    }

       if ($request->filled('tag_ids')) {
            $tagIds = explode(',', $request->input('tag_ids')); // e.g. [6,4]

            $query->where(function ($subQuery) use ($tagIds) {
                foreach ($tagIds as $tagId) {
                    $subQuery->orWhereRaw("FIND_IN_SET(?, users.tag_ids)", [$tagId]);
                }
            });
        }



    // Apply additional filters
    if ($request->filled('brand_id')) {
        $query->where('leaves.brand_id', $request->brand_id);
    }
    if ($request->filled('status')) {
        $query->where('leaves.status', $request->status);
    }
    if ($request->filled('region_id')) {
        $query->where('leaves.region_id', $request->region_id);
    }
    if ($request->filled('branch_id')) {
        $query->where('leaves.branch_id', $request->branch_id);
    }
    if ($request->filled('employee_id') && $request->employee_id !== 'undefined') {
        $query->where('leaves.employee_id', $request->employee_id);
    }
    if ($request->filled('created_at')) {
        $query->whereDate('leaves.created_at', substr($request->created_at, 0, 10));
    }

    // Get status counts - create a fresh query with only the aggregate functions
    $countQuery = Leave::query() 
        ->leftJoin('users', 'users.id', '=', 'leaves.employee_id')
        ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
        ->leftJoin('users as brands', 'brands.id', '=', 'leaves.brand_id')
        ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id');

    // Apply the same WHERE conditions as the main query
    $countQuery = RoleBaseTableGet($countQuery, 'leaves.brand_id', 'leaves.region_id', 'leaves.branch_id', 'leaves.created_by');

    if ($request->filled('search')) {
        $search = $request->input('search');
        $countQuery->where(function ($subQuery) use ($search) {
            $subQuery->where('users.name', 'like', "%$search%") 
                ->orWhere('brands.name', 'like', "%$search%")
                ->orWhere('regions.name', 'like', "%$search%")
                ->orWhere('branches.name', 'like', "%$search%");
        });
    }

    if ($request->filled('brand_id')) {
        $countQuery->where('leaves.brand_id', $request->brand_id);
    }
    // if ($request->filled('status')) {
    //     $countQuery->where('leaves.status', $request->status);
    // }
    if ($request->filled('region_id')) {
        $countQuery->where('leaves.region_id', $request->region_id);
    }
    if ($request->filled('branch_id')) {
        $countQuery->where('leaves.branch_id', $request->branch_id);
    }
    if ($request->filled('employee_id') && $request->employee_id !== 'undefined') {
        $countQuery->where('leaves.employee_id', $request->employee_id);
    }
    if ($request->filled('created_at')) {
        $countQuery->whereDate('leaves.created_at', substr($request->created_at, 0, 10));
    }

    // Get status counts with only aggregate functions
    $statusCounts = $countQuery->selectRaw("
        SUM(CASE WHEN leaves.status = 'Pending' THEN 1 ELSE 0 END) as Pending,
        SUM(CASE WHEN leaves.status = 'Approved' THEN 1 ELSE 0 END) as Approved,
        SUM(CASE WHEN leaves.status = 'Rejected' THEN 1 ELSE 0 END) as Rejected
    ")->first();

    // Apply sorting and pagination to the original query
    $leaves = $query->orderBy('leaves.created_at', 'DESC')
        ->paginate($perPage, ['*'], 'page', $page);

    // Return the paginated data
    return response()->json([
        'status' => 'success',
        'data' => $leaves->items(),
        'current_page' => $leaves->currentPage(),
        'last_page' => $leaves->lastPage(),
        'total_records' => $leaves->total(),
        'CountyCount' => $CountyCount,
        'per_page' => $leaves->perPage(),
        'count_summary' => $statusCounts
    ], 200);
}


    public function addLeave(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create leave')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'brand_id'           => 'required|integer|exists:users,id',
            'region_id'          => 'required|integer|exists:regions,id',
            'lead_branch'        => 'required|integer|exists:branches,id',
            'lead_assigned_user' => 'required|integer|exists:users,id',
            'leave_type_id'      => 'required|integer|exists:leave_types,id',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'leave_reason'       => 'required|string',
            'remark'             => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Create and save leave record
        $leave = new Leave();
        $leave->employee_id = $request->lead_assigned_user;
        $leave->brand_id = $request->brand_id;
        $leave->region_id = $request->region_id;
        $leave->branch_id = $request->lead_branch;
        $leave->leave_type_id = $request->leave_type_id;
        $leave->applied_on = now()->format('Y-m-d');
        $leave->start_date = $request->start_date;
        $leave->end_date = $request->end_date;
        $leave->total_leave_days = 0;
        $leave->leave_reason = $request->leave_reason;
        $leave->remark = $request->remark;
        $leave->status = 'Pending';
        $leave->created_by = Auth::id();
        $leave->save();

        // Log Activity
        // addLogActivity([
        //     'type' => 'info',
        //     'note' => json_encode([
        //         'title' => 'Leave Created',
        //         'message' => 'Leave record created successfully'
        //     ]),
        //     'module_id' => $leave->id,
        //     'module_type' => 'leave',
        //     'notification_type' => 'Leave Created'
        // ]);

            // Send email to queue start 

        

        $additionalTags = [
            'leave_start_date' => $leave->start_date ?? '-',
            'leave_end_date'   => $leave->end_date ?? '-',
            'leave_status'     => $leave->status ?? '-',
            'leave_reason'     => $leave->remark ?? 'Not provided',
        ];

        $templateId = null;
        $ccchecklist = [
                    'is_branch_manager'   => 'yes',
                    'is_region_manager'   => 'yes',
                    'is_project_manager'  => 'yes',
                    'is_scrop_attendance' => 'yes'
                ];

        addToEmailQueue(
            $leave->employee_id,
                'apply_leave_email_template',
                $templateId,
                $ccchecklist,
                array() ,
                $additionalTags 
            );

             // Send email to queue end 

          addLogActivity([
            'type' => 'success',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  created',
                'message' => $leave->user->name. ' leave  created'
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'leave created',
        ]);
        addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => $leave->user->name. ' leave  created',
                    'message' => $leave->user->name. ' leave  created'
                ]),
                'module_id' => $leave->employee_id,
                'module_type' => 'employeeprofile',
                'notification_type' => 'leave created',
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave successfully created.',
            'data' => $leave
        ], 201);
    }

    public function show(Leave $leave)
    {
        return redirect()->route('leave.index');
    }

    public function updateLeave(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit leave')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'id'           => 'required|exists:leaves,id',
            'brand_id'           => 'required|integer|exists:users,id',
            'region_id'          => 'required|integer|exists:regions,id',
            'lead_branch'        => 'required|integer|exists:branches,id',
            'lead_assigned_user' => 'required|integer|exists:users,id',
            'leave_type_id'      => 'required|integer|exists:leave_types,id',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'leave_reason'       => 'required|string',
            'remark'             => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Leave Record
        $leave = Leave::where('id', $request->id)->first();

        if (!$leave) {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave record not found.'
            ], 404);
        }

         $originalData = $leave->toArray();

        // Update Leave Record
        $leave->employee_id = $request->lead_assigned_user ?? $leave->employee_id;
        $leave->brand_id = $request->brand_id ?? $leave->brand_id;
        $leave->region_id = $request->region_id ?? $leave->region_id;
        $leave->branch_id = $request->lead_branch ?? $leave->branch_id;
        $leave->leave_type_id = $request->leave_type_id ?? $leave->leave_type_id;
        $leave->start_date = $request->start_date ?? $leave->start_date;
        $leave->end_date = $request->end_date ?? $leave->end_date;
        $leave->leave_reason = $request->leave_reason ?? $leave->leave_reason;
        $leave->remark = $request->remark ?? $leave->remark;

        $leave->save();

        // Log Activity

        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($leave->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $leave->$field
                ];
                $updatedFields[] = $field;
            }
        }
      
          addLogActivity([
            'type' => 'info',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  updated',
                 'message' => 'Fields updated: ' . implode(', ', $updatedFields)
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'leave updated',
        ]);
          addLogActivity([
            'type' => 'info',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  updated',
                 'message' => 'Fields updated: ' . implode(', ', $updatedFields)
            ]),
            'module_id' => $leave->employee_id,
            'module_type' => 'employeeprofile',
            'notification_type' => 'leave updated',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave successfully updated.',
            'data' => $leave
        ], 200);
    }


    public function deleteLeave(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:leaves,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if the user has permission
        if (!\Auth::user()->can('delete leave')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Find the Leave record
        $leave = Leave::with('User')->find($request->id);


        \App\Models\AttendanceEmployee::where('employee_id', $leave->employee_id)
            ->whereBetween('date', [$leave->start_date, $leave->end_date])
            ->where('status', 'Leave')
            ->delete();

        // Log the deletion activity
        $logData = [
            'type' => 'warning',
            'note' => json_encode([
               'title' => $leave->user->name. ' leave  deleted',
                'message' => $leave->user->name. ' leave  deleted'
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'Leave deleted'
        ];
        addLogActivity($logData);
  // Log the deletion activity
        $logData = [
            'type' => 'warning',
            'note' => json_encode([
               'title' => $leave->user->name. ' leave  deleted',
                'message' => $leave->user->name. ' leave  deleted'
            ]),
              'module_id' => $leave->employee_id,
            'module_type' => 'employeeprofile',
            'notification_type' => 'Leave Deleted'
        ];
        addLogActivity($logData);

        // Delete the record
        $leave->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('Leave successfully deleted.')
        ], 200);
    }


    public function action($id)
    {
        $leave     = Leave::find($id);
        $employee  = Employee::find($leave->employee_id);
        $leavetype = LeaveType::find($leave->leave_type_id);

        return view('leave.action', compact('employee', 'leavetype', 'leave'));
    }

    public function changeLeaveStatus(Request $request)
    {
       $validator = Validator::make($request->all(), [
            'leave_id' => 'required|exists:leaves,id',
            'status'   => 'required|string|in:Pending,Approved,Rejected,Approval',
            'reason'   => 'required_if:status,Rejected|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }


        // Fetch Leave Record
        $leave = Leave::where('id', $request->leave_id)->first();

        if (!$leave) {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave record not found.'
            ], 404);
        }

        // Update Status
        $leave->status = $request->status;
        if ($request->status === 'Rejected') {
                $leave->remark = $request->reason; // Assuming 'remark' stores rejection reason
            }
        $leave->updated_by = \Auth::user()->id;

        // If status is 'Approval', calculate total leave days and update status to 'Approved'
        if ($request->status === 'Approved') {
            $startDate = new \DateTime($leave->start_date);
            $endDate = new \DateTime($leave->end_date);
            $endDate->modify('+1 day');
            $interval = new \DateInterval('P1D');
            $dateRange = new \DatePeriod($startDate, $interval, $endDate);
           foreach ($dateRange as $date) {
                $formattedDate = $date->format('Y-m-d');

                \App\Models\AttendanceEmployee::updateOrCreate(
                    [
                        'employee_id' => $leave->employee_id,
                        'date'        => $formattedDate,
                    ],
                    [
                        'clock_in'      => '00:00:00',
                        'clock_out'     => '00:00:00',
                        'early_leaving' => '00:00:00',
                        'overtime'      => '00:00:00',
                        'total_rest'    => '00:00:00',
                        'status'        => 'Leave',
                        'created_by'    => \Auth::id(),
                        'updated_at'    => now(),
                    ]
                );
            }
            // Calculate total leave days
            $leave->total_leave_days = (new \DateTime($leave->start_date))->diff(new \DateTime($leave->end_date))->days + 1;
            $leave->status = 'Approved';
            $leave->save();
        } else if ($request->status === 'Rejected') {

            \App\Models\AttendanceEmployee::where('employee_id', $leave->employee_id)
                ->whereBetween('date', [$leave->start_date, $leave->end_date])
                ->where('status', 'Leave')
                ->delete();

            $leave->status = 'Rejected';
            $leave->save();
        }

        $leave->save();

        // // Log Activity
        // addLogActivity([
        //     'type' => 'info',
        //     'note' => json_encode([
        //         'title' => 'Leave Status Updated',
        //         'message' => "Leave status changed to {$leave->status}"
        //     ]),
        //     'module_id' => $leave->id,
        //     'module_type' => 'leave',
        //     'notification_type' => 'Leave Status Updated'
        // ]);

            addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $leave->user->name. ' leave  status changed to '.$leave->status,
                        'message' => $leave->user->name. ' leave status changed to '.$leave->status,
                    ]),
                    'module_id' => $leave->id,
                    'module_type' => 'leave',
                    'notification_type' => 'leave created',
                ]);
            addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $leave->user->name. ' leave  status changed to '.$leave->status,
                        'message' => $leave->user->name. ' leave status changed to '.$leave->status,
                    ]),
                    'module_id' => $leave->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => 'leave created',
                ]);


        // Send Email if enabled
 
    $employee = User::where('id', $leave->employee_id)->first();

    $leave = Leave::with(['leaveType','User','branch','brand','region'])->where('id', $request->leave_id)->first();

    if ($employee) {
        // Get related details
        $project_manager_detail = User::where('id', $leave->brand->project_manager_id)->first();
        $branch_manager_detail = User::where('id', $leave->branch->branch_manager_id)->first();
        $brand_detail = User::where('id', $leave->brand_id)->first();
        
        // Prepare CC list
        $ccList = [];
        if (!empty($project_manager_detail?->email)) {
            $ccList[] = $project_manager_detail->email;
        }
        if (!empty($branch_manager_detail?->email)) {
            $ccList[] = $branch_manager_detail->email;
        }
        $ccList[] = 'scorp-erp_attendance@convosoft.com'; // Mandatory CC

       $leaveDaysCount = $leave->getApprovedLeaveDaysCount();
        $approvedDays = isset($leaveDaysCount[$leave->leaveType->id]) 
            ? $leaveDaysCount[$leave->leaveType->id] 
            : 0;
        $remainingLeaveBalance = $leave->leaveType->days - $approvedDays;

        if ($leave->status === 'Approved') {
            // Approved leave template - exactly matching the PDF
            $content = "<h1>Leave Request Approved for {$employee->name} on {$leave->start_date} to {$leave->end_date}</h1>
            <p>Dear {$employee->name},</p>
            <p>We are pleased to inform you that your leave request for the following date(s) has been approved:</p>
            <ul>
                <li>Leave Date(s): {$leave->start_date} to {$leave->end_date}</li>
                <li>Leave Type: {$leave->leaveType->title}</li>
                <li>Total Days: {$leave->total_leave_days}</li>
            </ul>
            <p>Please ensure that you complete any handover of ongoing tasks to your project team before your leave begins. If there are any last-minute updates or urgent matters, kindly coordinate with your Project Manager, {$project_manager_detail?->name} , to ensure continuity.</p>
            <p>Your leave balance after this approval is: {$remainingLeaveBalance} ( {$leave->leaveType->title}) days.</p>
            <p>Enjoy your well-deserved time off. If your plans change or you need to extend your leave, please submit a new leave request at least 3 days in advance.</p>
            <p>Regards,</p>
            <p>{$brand_detail->name}  – HR Department, SCORP</p>
            <p>hr@scorp.co</p>";

            $subject = "Leave Request Approved for {$employee->name} on {$leave->start_date} to {$leave->end_date}";

        } else if ($leave->status === 'Rejected') {
            // Rejected leave template - exactly matching the PDF
            $content = "<h1>Leave Request Declined for {$employee->name} on {$leave->start_date} to {$leave->end_date}</h1>
            <p>Dear {$employee->name},</p>
            <p>We have reviewed your leave request for the following date(s):</p>
            <ul>
                <li>Requested Date(s): {$leave->start_date} to {$leave->end_date}</li>
                <li>Leave Type:  {$leave->leaveType->title}</li>
                <li>Comments:  {$request->reason}</li>
            </ul>
            <p>Unfortunately, we are unable to approve your request due to some reason. You currently have {$remainingLeaveBalance}  ( {$leave->leaveType->title})  days of leave available.</p>
            <p>Please coordinate with your Project Manager, {$project_manager_detail?->name}, to discuss alternative dates or solutions. You may submit a revised leave request once the matter is resolved.</p>
            <p>If you have any questions or need further clarification, feel free to reach out to the HR Department.</p>
            <p>Regards,</p>
            <p>{$brand_detail->name } – HR Department, SCORP</p>
            <p>hr@scorp.co</p>";

            $subject = "Leave Request Declined for {$employee->name} on {$leave->start_date} to {$leave->end_date}";
        }

        // Insert email into queue
       // Insert email into queue
                if (isset($content)) {
                    $emailQueue = new \App\Models\EmailSendingQueue();
                    $emailQueue->to = $employee->email;
                    $emailQueue->cc = implode(',', $ccList);
                    $emailQueue->subject = $subject;
                    $emailQueue->brand_id = $employee->brand_id;
                    $emailQueue->from_email = 'hr@scorp.co';
                    $emailQueue->branch_id = $employee->branch_id;
                    $emailQueue->region_id = $employee->region_id;
                    $emailQueue->is_send = '0';
                    $emailQueue->sender_id = auth()->id(); // Current user ID
                    $emailQueue->created_by = auth()->id(); // Current user ID
                    $emailQueue->priority = 1;
                    $emailQueue->content = $content;
                    $emailQueue->related_type = 'employee';
                    $emailQueue->related_id = $employee->id;
                    $emailQueue->created_at = now();
                    $emailQueue->updated_at = now();
                    $emailQueue->save();
                }
    }
 
        
        return response()->json([
            'status' => 'success',
            'message' => 'Leave status successfully updated.',
            'data' => $leave
        ], 200);
    }



    public function jsoncount(Request $request)
    {

        // $leave_counts = LeaveType::select(\DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave, leave_types.title, leave_types.days,leave_types.id'))
        //                          ->leftjoin('leaves', function ($join) use ($request){
        //     $join->on('leaves.leave_type_id', '=', 'leave_types.id');
        //     $join->where('leaves.employee_id', '=', $request->employee_id);
        // }
        // )->groupBy('leaves.leave_type_id')->get();

        $leave_counts=[];
        $leave_types = LeaveType::where('created_by',\Auth::id())->get();
        foreach ($leave_types as  $type) {
            $counts=Leave::select(\DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave'))->where('leave_type_id',$type->id)->groupBy('leaves.leave_type_id')->where('employee_id',$request->employee_id)->first();

            $leave_count['total_leave']=!empty($counts)?$counts['total_leave']:0;
            $leave_count['title']=$type->title;
            $leave_count['days']=$type->days;
            $leave_count['id']=$type->id;
            $leave_counts[]=$leave_count;
        }


        return $leave_counts;

    }

    public function Hrmleave()
    {
        $user = \Auth::user();
        if ($user->type!='HR' && $user->type!='super admin' && $user->type!='Project Manager') {
            echo 'access Denied';
                exit();
                die();
        }
        // Build the leads query
        if(isset($_GET['emp_id'])){
            $userId = $_GET['emp_id'];
         }else{
             $userId = \Auth::id();
         }
        $Employee = Employee::where('user_id',$userId)->first();
        $AuthUser = User::find($userId);
        if(empty($Employee)){
            $leaves=[];
            $leaveFrequency=[];
            $leaveDetails=[];
            return view('hrmhome.leave', compact('leaves','AuthUser','leaveFrequency','leaveDetails'));
        }
        $Leave_query = Leave::select(
                'regions.name as region',
                'branches.name as branch',
                'users.name as brand',
                'leaves.id',
                'leaves.brand_id',
                'leaves.branch_id',
                'leaves.created_by',
                'leaves.start_date',
                'leaves.created_at',
                'leaves.leave_reason',
                'leaves.end_date',
                'leaves.applied_on',
                'leaves.leave_type_id',
                'leaves.total_leave_days',
                'leaves.status',
                )
                ->leftJoin('users', 'users.id', '=', 'leaves.brand_id')
                ->leftJoin('users as leavedPerson', 'users.id', '=', 'leaves.employee_id')
                ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
                ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id')
                ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'leaves.created_by')
                ->leftJoin('lead_tags as tag', 'tag.lead_id', '=', 'leaves.id');
        $Leave_query->where('leaves.employee_id',$Employee->id);
        $leaves=$Leave_query->get();
        $leaves = Leave::where('start_date', '>=', Carbon::now()->subMonths(12))
        ->select('start_date', 'end_date')->where('leaves.employee_id',$Employee->id);
         $leaves=$Leave_query->get();
         $leaveFrequency = [
            'Sun' => 0,
            'Mon' => 0,
            'Tue' => 0,
            'Wed' => 0,
            'Thu' => 0,
            'Fri' => 0,
            'Sat' => 0,
        ];
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date)->addDay();
            foreach ($startDate->toPeriod($endDate, '1 day') as $date) {
                $weekday = $date->format('D'); // Get weekday abbreviation (Sun, Mon, etc.)
                $leaveFrequency[$weekday]++;
            }
        }
        $leaveTypes = LeaveType::all();
        $leaveDetails = [];
        foreach ($leaveTypes as $type) {
            $allowance = $type->days;
            $leave_balance_check = Leave::where('leave_type_id', $type->id)->where('start_date', '>=', Carbon::now()->subMonths(12))
                        ->select('start_date', 'end_date')->where('leaves.employee_id',$Employee->id)->get();
            $usedLeaves = $leave_balance_check->where('status', 'Approved')->sum('total_leave_days');
            $plannedLeaves = $leave_balance_check->where('status', 'Pending')->sum('total_leave_days');
            $balance = $allowance - $usedLeaves;
            $available = $balance - $plannedLeaves;
            $leaveDetails[] = [
                'leave_type' => $type->title, // Title from LeaveType table
                'allowance' => $allowance,
                'balance' => $balance,
                'planned' => $plannedLeaves,
                'available' => $available,
                'units' => 'Days',
                    ];
            }
        return view('hrmhome.leave', compact('leaves','AuthUser','leaveFrequency','leaveDetails'));

    }

    public function HrmEmployee1()
    {
        return view('hrmhome.employee1');

    }


    public function Balance(Request $request)
    {
        $user = \Auth::user();

        // Authorization check
        // if (!in_array($user->type, ['HR', 'super admin', 'Project Manager'])) {
        //     return response()->json(['error' => 'Access Denied'], 403);
        // }

        // Fetch the employee ID or use authenticated user ID
        if(isset($request->emp_id)){
            $userId = $request->emp_id;
         }else{
             $userId = \Auth::id();
         }
         $employee = Employee::where('user_id',$userId)->first();
         $authUser = User::find($userId);

        if (empty($employee)) {
            return response()->json([
                'leaves' => [],
                'authUser' => $authUser,
                'leaveFrequency' => [],
                'leaveDetails' => []
            ]);
        }

        // Build the leaves query
        $leaveQuery = Leave::select(
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'leaves.id',
            'leaves.brand_id',
            'leaves.branch_id',
            'leaves.created_by',
            'leaves.start_date',
            'leaves.created_at',
            'leaves.leave_reason',
            'leaves.end_date',
            'leaves.applied_on',
            'leaves.leave_type_id',
            'leaves.total_leave_days',
            'leaves.status'
        )
            ->leftJoin('users', 'users.id', '=', 'leaves.brand_id')
            ->leftJoin('users as leavedPerson', 'users.id', '=', 'leaves.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
            ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'leaves.created_by')
            ->where('leaves.employee_id', $userId);

        $leaves = $leaveQuery->get();

        // Calculate leave frequency
        $leaveFrequency = [
            'Sun' => 0,
            'Mon' => 0,
            'Tue' => 0,
            'Wed' => 0,
            'Thu' => 0,
            'Fri' => 0,
            'Sat' => 0,
        ];

        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date)->addDay();
            foreach ($startDate->toPeriod($endDate, '1 day') as $date) {
                $weekday = $date->format('D'); // Get weekday abbreviation
                $leaveFrequency[$weekday]++;
            }
        }

        // Fetch leave details
        $leaveTypes = LeaveType::all();
        $leaveDetails = [];
        foreach ($leaveTypes as $type) {
            $allowance = $type->days;

            $leaveBalanceCheck = Leave::where('leave_type_id', $type->id)
                ->where('start_date', '>=', Carbon::now()->subMonths(12))
                ->where('leaves.employee_id', $userId)
                ->get();

            // $usedLeaves = $leaveBalanceCheck->where('status', 'Approved')->sum('total_leave_days');
            $usedLeaves = $leaveBalanceCheck
                ->filter(fn($leave) => $leave->status === 'Approved' && Carbon::parse($leave->start_date)->lte(Carbon::today()))
                ->sum('total_leave_days');


            $plannedLeaves = $leaveBalanceCheck->where('status', 'Approved')
            ->filter(function ($leave) {
                return Carbon::parse($leave->start_date)->gt(Carbon::today());
            })
            ->sum('total_leave_days');





            $balance = $usedLeaves;

            $available = ($allowance - $usedLeaves) - $plannedLeaves;

            $leaveDetails[] = [
                'leave_type' => $type->title,
                'allowance' => $allowance,
                'balance' => $balance,
                'planned' => $plannedLeaves,
                'available' => $available,
                'units' => 'Days',
            ];
        }
     $data=['leaves' => $leaves,
            'authUser' => $authUser,
            'leaveFrequency' => $leaveFrequency,
            'leaveDetails' => $leaveDetails,
           ];
        // Return JSON responsest
        return response()->json([
            'status' => "success",
            'data' => $leaveDetails
        ]);
    }

}
