<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use App\Models\User;
use App\Models\AllowanceOption;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class AllowanceController extends Controller
{

    public function getAllowances(Request $request)
{
    // Validate input
    $validated = $request->validate([
        'employee_id' => 'required|exists:employees,id',
    ]);

    // Fetch allowances
    $allowances = Allowance::with('allowance_option')->where('employee_id', $validated['employee_id'])->get();

    // Return response
    return response()->json([
        'success' => true,
        'message' => 'Allowances retrieved successfully.',
        'data'    => $allowances
    ], 200);
}

    public function allowanceCreate($id)
    {

        $allowance_options = AllowanceOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $employee          = Employee::find($id);
        $Allowancetypes = Allowance::$Allowancetype;

        return view('allowance.create', compact('employee', 'allowance_options', 'Allowancetypes'));
    }

    public function addEmpoyeeAllowance(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create allowance')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'employee_id'      => 'required|integer|exists:employees,id',
            'allowance_option' => 'required|integer|exists:allowance_options,id',
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

        // Create and save allowance record
        $allowance = new Allowance();
        $allowance->employee_id = $request->employee_id;
        $allowance->allowance_option = $request->allowance_option;
        $allowance->title = $request->title;
        $allowance->type = $request->type;
        $allowance->amount = $request->amount;
        $allowance->created_by = Auth::user()->creatorId();
        $allowance->save();

        

               //  ========== add ============
                $user = User::find($allowance->employee_id);
                $typeoflog = 'allowance';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $allowance->employee->name. ' '.$typeoflog.' created',
                        'message' => $allowance->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $allowance->id,
                    'module_type' => 'setsalary',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $allowance->employee->name. ' '.$typeoflog.'  created',
                        'message' => $allowance->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $allowance->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);


        return response()->json([
            'status' => 'success',
            'message' => 'Allowance successfully created.',
            'data' => $allowance
        ], 201);
    }

    public function show(Allowance $allowance)
    {
        return redirect()->route('allowance.index');
    }



    public function updateEmployeeAllowance(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit allowance')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'allowance_id'     => 'required',
            'employee_id'      => 'nullable|integer|exists:employees,id',
            'allowance_option' => 'nullable|integer|exists:allowance_options,id',
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

        // Fetch Allowance Record
        $allowance = Allowance::where('id', $request->allowance_id)->first();

        if (!$allowance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Allowance record not found.'
            ], 404);
        }

        // Preserve existing values if not provided
        $allowance->employee_id = $request->employee_id ?? $allowance->employee_id;
        $allowance->allowance_option = $request->allowance_option ?? $allowance->allowance_option;
        $allowance->title = $request->title ?? $allowance->title;
        $allowance->type = $request->type ?? $allowance->type;
        $allowance->amount = $request->amount ?? $allowance->amount;

        $allowance->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Allowance Updated',
                'message' => 'Employee allowance record updated successfully'
            ]),
            'module_id' => $allowance->id,
            'module_type' => 'allowance',
            'notification_type' => 'Allowance Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Allowance successfully updated.',
            'data' => $allowance
        ], 200);
    }


    public function deleteEmployeeAllownce(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:allowances,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $allowance = Allowance::where('id', $request->id)->first();


        $allowance->delete();

        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'Allowance Deleted',
                'message' => 'An allowance record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'allowance',
            'notification_type' => 'Allowance Deleted'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Allowance successfully deleted.',
        ], 200);
    }

}
