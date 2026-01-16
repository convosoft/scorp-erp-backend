<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OvertimeController extends Controller
{
    public function getOvertimes(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        // Fetch overtimes
        $overtimes = Overtime::where('employee_id', $validated['employee_id'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Overtimes retrieved successfully.',
            'data'    => $overtimes
        ], 200);
    }

    public function addEmployeeOvertime(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create overtime')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'employee_id'    => 'required|integer|exists:employees,id',
            'title'          => 'required|string|max:255',
            'number_of_days' => 'required|integer|min:1',
            'hours'          => 'required|numeric|min:0',
            'rate'           => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Create and save overtime record
        $overtime = new Overtime();
        $overtime->employee_id = $request->employee_id;
        $overtime->title = $request->title;
        $overtime->number_of_days = $request->number_of_days;
        $overtime->hours = $request->hours;
        $overtime->rate = $request->rate;
        $overtime->created_by = Auth::id();
        $overtime->save();
        
       //  ========== add ============
                $user = User::find($overtime->employee_id);
                $typeoflog = 'over time';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $overtime->employee->name. ' '.$typeoflog.' created',
                        'message' => $overtime->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $overtime->employee_id,
                    'module_type' => 'setsalary',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $overtime->employee->name. ' '.$typeoflog.'  created',
                        'message' => $overtime->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $overtime->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Overtime successfully created.',
            'data' => $overtime
        ], 201);
    }

    public function updateEmployeeOvertime(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit overtime')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'overtime_id'    => 'required|exists:overtimes,id',
            'title'          => 'nullable|string|max:255',
            'number_of_days' => 'nullable|integer|min:1',
            'hours'          => 'nullable|numeric|min:0',
            'rate'           => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Overtime Record
        $overtime = Overtime::where('id', $request->overtime_id)->first();

        if (!$overtime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Overtime record not found.'
            ], 404);
        }

        // Preserve existing values if not provided
        $overtime->title = $request->title ?? $overtime->title;
        $overtime->number_of_days = $request->number_of_days ?? $overtime->number_of_days;
        $overtime->hours = $request->hours ?? $overtime->hours;
        $overtime->rate = $request->rate ?? $overtime->rate;

        $overtime->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Overtime Updated',
                'message' => 'Employee overtime record updated successfully'
            ]),
            'module_id' => $overtime->id,
            'module_type' => 'overtime',
            'notification_type' => 'Overtime Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Overtime successfully updated.',
            'data' => $overtime
        ], 200);
    }

    public function deleteEmployeeOvertime(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:overtimes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        // Fetch Overtime Record
        $overtime = Overtime::where('id', $request->id)->first();
        $overtime->delete();

        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'Overtime Deleted',
                'message' => 'An overtime record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'overtime',
            'notification_type' => 'Overtime Deleted'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Overtime successfully deleted.',
        ], 200);
    }
}
