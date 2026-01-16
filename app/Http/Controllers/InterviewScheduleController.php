<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InterviewSchedule;
use App\Models\JobApplication;
use App\Models\JobStage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class InterviewScheduleController extends Controller
{
    public function getInterviews(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'startDate' => 'nullable|date',
            'startTime' => 'nullable|date_format:H:i:s',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Default pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Build the query
        $query = InterviewSchedule::with('applications.jobs', 'users', 'scheduled_by')
        ->whereHas('applications.jobs') // Ensures related jobs exist
        ->where('created_by', Auth::id());

        // Apply date filter
        if ($request->filled('startDate')) {
            $query->whereDate('date', $request->startDate);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee', $request->employee_id);
        }

        // Apply time filter
        if ($request->filled('startTime')) {
            $query->whereTime('time', $request->startTime);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('users', function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%$search%");
                })->orWhereHas('applications.jobs', function ($subQuery) use ($search) {
                    $subQuery->where('title', 'like', "%$search%");
                })->orWhereHas('scheduled_by', function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%$search%");
                });
            });
        }

        // Fetch paginated interview schedules
        $schedules = $query->orderBy('date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return JSON response
        return response()->json([
            'success' => true,
            'message' => 'Interviews fetched successfully.',
            'data' => [
                'interviews' => $schedules->items(),
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'total_records' => $schedules->total(),
                'per_page' => $schedules->perPage(),
            ],
        ]);
    }



    public function addInterveiw(Request $request)
    {
        if (!Auth::user()->can('create interview schedule')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'candidate' => 'required|exists:job_applications,id',
            'employee'  => 'required|exists:users,id',
            'date'      => 'required|date',
            'time'      => 'required',
            'comment'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $schedule = InterviewSchedule::create([
            'candidate'  => $request->candidate,
            'employee'   => $request->employee,
            'date'       => $request->date,
            'time'       => $request->time,
            'comment'    => $request->comment,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Interview schedule successfully created.'),
            'data'    => $schedule
        ], 201);
    }

    public function show(Request $request)
    {
        $schedule = InterviewSchedule::with('applications.jobs', 'users', 'scheduled_by')->find($request->id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => __('Schedule not found.')
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $schedule
        ]);
    }

    public function update(Request $request)
    {
        if (!Auth::user()->can('edit interview schedule')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission denied.')
            ], 403);
        }

        $schedule = InterviewSchedule::find($request->id);
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => __('Schedule not found.')
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'candidate' => 'required|exists:job_applications,id',
            'employee'  => 'required|exists:users,id',
            'date'      => 'required|date',
            'time'      => 'required',
            'comment'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $schedule->update([
            'candidate' => $request->candidate,
            'employee'  => $request->employee,
            'date'      => $request->date,
            'time'      => $request->time,
            'comment'   => $request->comment,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Interview schedule successfully updated.'),
            'data'    => $schedule
        ]);
    }

    public function destroy(Request $request)
    {
        $schedule = InterviewSchedule::find($request->id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => __('Schedule not found.')
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => __('Interview schedule successfully deleted.')
        ]);
    }

    public function hrmInterviewSchedule(Request $request)
    {
        $userId = $request->query('emp_id', Auth::id());
        $AuthUser = User::find($userId);

        $schedules = InterviewSchedule::where('created_by', Auth::id())->get();
        $arrSchedule = $schedules->map(function ($schedule) {
            return [
                'id'       => $schedule->id,
                'title'    => optional(optional($schedule->applications)->jobs)->title ?? '',
                'start'    => $schedule->date,
                'className'=> 'event-primary',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $arrSchedule,
            'user'    => $AuthUser
        ]);
    }
}
