<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\GoalTracking;
use App\Models\GoalType;
use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GoalTrackingController extends Controller
{

    public function getGoalTrackings(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage goal tracking')) {
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
        $query = GoalTracking::with(['created_by', 'brand', 'branch', 'region','goalType']);

        // Apply role-based filtering
        $query = RoleBaseTableGet($query, 'goal_trackings.brand_id', 'goal_trackings.region_id', 'goal_trackings.branch', 'goal_trackings.created_by');

        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('goal_trackings.goal_type', 'like', "%$search%")
                    ->orWhere('goal_trackings.target_achievement', 'like', "%$search%")
                    ->orWhere('goal_trackings.description', 'like', "%$search%");
            });
        }

        // Apply user-specific filters
        $user = Auth::user();
        $brand_ids = array_keys(FiltersBrands());

        if ($user->type === 'company') {
            $query->where('goal_trackings.brand_id', $user->id);
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $query->whereIn('goal_trackings.brand_id', $brand_ids);
        } elseif ($user->type === 'Region Manager' && !empty($user->region_id)) {
            $query->where('goal_trackings.region_id', $user->region_id);
        } elseif (in_array($user->type, ['Branch Manager', 'Admissions Officer', 'Admissions Manager', 'Marketing Officer']) && !empty($user->branch_id)) {
            $query->where('goal_trackings.branch', $user->branch_id);
        } else {
            $query->where('goal_trackings.created_by', $user->id);
        }

        // Apply additional filters
        if ($request->filled('brand_id')) {
            $query->where('goal_trackings.brand_id', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $query->where('goal_trackings.region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('goal_trackings.branch', $request->branch_id);
        }
        if ($request->filled('created_at')) {
            $query->whereDate('goal_trackings.created_at', substr($request->created_at, 0, 10));
        }

        // Apply sorting and pagination
        $goalTrackings = $query->orderBy('goal_trackings.created_at', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $goalTrackings->items(),
            'current_page' => $goalTrackings->currentPage(),
            'last_page' => $goalTrackings->lastPage(),
            'total_records' => $goalTrackings->total(),
            'per_page' => $goalTrackings->perPage(),
        ], 200);
    }




    public function addGoalTracking(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create goal tracking')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'brand_id'   => 'required|integer|exists:users,id',
            'region_id'  => 'required|integer|exists:regions,id',
            'lead_branch' => 'required|integer|exists:branches,id',
            'goal_type'  => 'required|string',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'subject'    => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Create and save goal tracking
        $goalTracking = new GoalTracking();
        $goalTracking->brand_id = $request->brand_id;
        $goalTracking->region_id = $request->region_id;
        $goalTracking->branch = $request->lead_branch;
        $goalTracking->goal_type = $request->goal_type;
        $goalTracking->start_date = $request->start_date;
        $goalTracking->end_date = $request->end_date;
        $goalTracking->subject = $request->subject;
        $goalTracking->rating = $request->ratings;
        $goalTracking->target_achievement = $request->target_achievement;
        $goalTracking->description = $request->description;
        $goalTracking->status = $request->status;
        $goalTracking->created_by = Auth::user()->creatorId();
        $goalTracking->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Goal Tracking Created',
                'message' => 'Goal tracking record created successfully'
            ]),
            'module_id' => $goalTracking->id,
            'module_type' => 'goal_tracking',
            'notification_type' => 'Goal Tracking Created'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Goal tracking successfully created.',
            'data' => $goalTracking
        ], 201);
    }


    public function goalTrackingDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:goal_trackings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $goalTracking = GoalTracking::where('id', $request->id)
            ->with(['brand:id,name', 'branch:id,name', 'region:id,name','goalType'])
            ->first();

        if (!$goalTracking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goal tracking data not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $goalTracking
        ]);
    }



    public function updateGoalTracking(Request $request)
    {
        $user = Auth::user();

        // Check Permissions
        if (!$user->can('edit goal tracking') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = Validator::make($request->all(), [
            'id'           => 'required|exists:goal_trackings,id',
            'brand_id'     => 'sometimes|required|exists:users,id',
            'region_id'    => 'sometimes|required|exists:regions,id',
            'lead_branch'  => 'sometimes|required|exists:branches,id',
            'goal_type'    => 'sometimes|required|string',
            'start_date'   => 'sometimes|required|date',
            'end_date'     => 'sometimes|required|date|after_or_equal:start_date',
            'subject'      => 'sometimes|required|string',
            'target_achievement' => 'sometimes|nullable|string',
            'description'  => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Fetch Goal Tracking Record
        $goalTracking = GoalTracking::find($request->id);
        if (!$goalTracking) {
            return response()->json([
                'status' => 'error',
                'message' => __('Goal tracking record not found.')
            ], 404);
        }

        // Update Goal Tracking Data
        $goalTracking->brand_id = $request->brand_id ?? $goalTracking->brand_id;
        $goalTracking->region_id = $request->region_id ?? $goalTracking->region_id;
        $goalTracking->branch = $request->lead_branch ?? $goalTracking->branch;
        $goalTracking->goal_type = $request->goal_type ?? $goalTracking->goal_type;
        $goalTracking->rating = $request->ratings;
        $goalTracking->start_date = $request->start_date ?? $goalTracking->start_date;
        $goalTracking->end_date = $request->end_date ?? $goalTracking->end_date;
        $goalTracking->subject = $request->subject ?? $goalTracking->subject;
        $goalTracking->target_achievement = $request->target_achievement ?? $goalTracking->target_achievement;
        $goalTracking->description = $request->description ?? $goalTracking->description;
        $goalTracking->status = $request->status ?? $goalTracking->status;


        $goalTracking->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Goal Tracking Updated',
                'message' => 'Goal tracking record updated successfully'
            ]),
            'module_id' => $goalTracking->id,
            'module_type' => 'goal_tracking',
            'notification_type' => 'Goal Tracking Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'goal_tracking_id' => $goalTracking->id,
            'message' => __('Goal tracking successfully updated!')
        ], 200);
    }



    public function deleteGoalTracking(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:goal_trackings,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if the user has permission
        if (!\Auth::user()->can('delete goal tracking')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Find the Goal Tracking record
        $goalTracking = GoalTracking::find($request->id);

        // Verify if the authenticated user is the creator
        if ($goalTracking->created_by != \Auth::user()->creatorId()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Log the deletion activity
        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Goal Tracking Deleted',
                'message' => 'A goal tracking record was deleted successfully.'
            ]),
            'module_id' => $goalTracking->id,
            'module_type' => 'goal_tracking',
            'notification_type' => 'Goal Tracking Deleted'
        ];
        addLogActivity($logData);

        // Delete the record
        $goalTracking->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('Goal Tracking successfully deleted.')
        ], 200);
    }
}
