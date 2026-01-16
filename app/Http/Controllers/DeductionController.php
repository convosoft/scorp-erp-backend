<?php

namespace App\Http\Controllers;

use App\Models\Deduction;
use App\Models\DeductionOption;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DeductionController extends Controller
{
    public function getDeductions(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        // Fetch deductions
        $deductions = Deduction::with('deduction_option')->where('employee_id', $validated['employee_id'])->get();

        // Return response
        return response()->json([
            'success' => true,
            'message' => 'Deductions retrieved successfully.',
            'data'    => $deductions
        ], 200);
    }

    public function deductionCreate($id)
    {
        $deduction_options = DeductionOption::where('created_by', Auth::user()->creatorId())->get()->pluck('name', 'id');
        $employee          = Employee::find($id);
        $Deductiontypes    = Deduction::$Deductiontype;

        return view('deduction.create', compact('employee', 'deduction_options', 'Deductiontypes'));
    }

    public function addEmployeeDeduction(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create saturation deduction')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'employee_id'      => 'required|integer|exists:employees,id',
            'deduction_option' => 'required|integer|exists:deduction_options,id',
            'title'            => 'required|string|max:255',
            'type'             => 'nullable|string',
            'amount'           => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Create and save deduction record
        $deduction = new Deduction();
        $deduction->employee_id = $request->employee_id;
        $deduction->deduction_option = $request->deduction_option;
        $deduction->title = $request->title;
        $deduction->type = $request->type;
        $deduction->amount = $request->amount;
        $deduction->created_by = Auth::user()->creatorId();
        $deduction->save();
        

        // Log Activity
       //  ========== add ============
                $user = User::find($deduction->employee_id);
                $typeoflog = 'deduction';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' =>  $deduction->employee->name. ' '.$typeoflog.' created',
                        'message' =>  $deduction->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $deduction->employee_id,
                    'module_type' => 'setsalary',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' =>  $deduction->employee->name. ' '.$typeoflog.'  created',
                        'message' =>  $deduction->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $deduction->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Deduction successfully created.',
            'data' => $deduction
        ], 201);
    }

    public function show(Deduction $deduction)
    {
        return redirect()->route('deduction.index');
    }

    public function updateEmployeeDeduction(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit saturation deduction')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'deduction_id'     => 'required|exists:saturation_deductions,id',
            'employee_id'      => 'nullable|integer|exists:employees,id',
            'deduction_option' => 'nullable|integer|exists:deduction_options,id',
            'title'            => 'nullable|string|max:255',
            'type'             => 'nullable|string',
            'amount'           => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Deduction Record
        $deduction = Deduction::where('id', $request->deduction_id)->first();

        if (!$deduction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deduction record not found.'
            ], 404);
        }

        // Preserve existing values if not provided
        $deduction->employee_id = $request->employee_id ?? $deduction->employee_id;
        $deduction->deduction_option = $request->deduction_option ?? $deduction->deduction_option;
        $deduction->title = $request->title ?? $deduction->title;
        $deduction->type = $request->type ?? $deduction->type;
        $deduction->amount = $request->amount ?? $deduction->amount;

        $deduction->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Deduction Updated',
                'message' => 'Employee deduction record updated successfully'
            ]),
            'module_id' => $deduction->id,
            'module_type' => 'deduction',
            'notification_type' => 'Deduction Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Deduction successfully updated.',
            'data' => $deduction
        ], 200);
    }

    public function deleteEmployeeDeduction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:saturation_deductions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $deduction = Deduction::where('id', $request->id)->first();
        $deduction->delete();

        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'Deduction Deleted',
                'message' => 'A deduction record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'deduction',
            'notification_type' => 'Deduction Deleted'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Deduction successfully deleted.',
        ], 200);
    }
}
