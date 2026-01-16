<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class CommissionsController extends Controller
{

    public function getCommissions(Request $request)
{
    // Validate input
    $validated = $request->validate([
        'employee_id' => 'required|exists:employees,id',
    ]);

    // Fetch Commissions
    $Commissions = Commission::where('employee_id', $validated['employee_id'])->get();

    // Return response
    return response()->json([
        'success' => true,
        'message' => 'Commissions retrieved successfully.',
        'data'    => $Commissions
    ], 200);
}

    public function CommissionsCreate($id)
    {

        $Commissions_options = CommissionsOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $employee          = Employee::find($id);
        $Commissionstypes = Commission::$Commissionstype;

        return view('Commissions.create', compact('employee', 'Commissions_options', 'Commissionstypes'));
    }

    public function addEmpoyeeCommissions(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create commission')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'employee_id'      => 'required|integer|exists:employees,id',
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

        // Create and save Commissions record
        $Commissions = new Commission();
        $Commissions->employee_id = $request->employee_id;
        $Commissions->title = $request->title;
        $Commissions->type = $request->type;
        $Commissions->amount = $request->amount;
        $Commissions->created_by = Auth::user()->creatorId();
        $Commissions->save();

         

       //  ========== add ============
                $user = User::find($Commissions->employee_id);
                $typeoflog = 'commissions';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $Commissions->employee->name. ' '.$typeoflog.' created',
                        'message' => $Commissions->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $Commissions->employee_id,
                    'module_type' => 'setsalary',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $Commissions->employee->name. ' '.$typeoflog.'  created',
                        'message' => $Commissions->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $Commissions->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Commissions successfully created.',
            'data' => $Commissions
        ], 201);
    }

    public function show(Commissions $Commissions)
    {
        return redirect()->route('Commissions.index');
    }



    public function updateEmployeeCommissions(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit commission')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'commissions_id'     => 'required|exists:commissions,id',
            'employee_id'      => 'nullable|integer|exists:employees,id',
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

        // Fetch Commissions Record
        $Commissions = Commission::where('id', $request->commissions_id)->first();

        if (!$Commissions) {
            return response()->json([
                'status' => 'error',
                'message' => 'Commissions record not found.'
            ], 404);
        }

        // Preserve existing values if not provided
        $Commissions->employee_id = $request->employee_id ?? $Commissions->employee_id;
        $Commissions->title = $request->title ?? $Commissions->title;
        $Commissions->type = $request->type ?? $Commissions->type;
        $Commissions->amount = $request->amount ?? $Commissions->amount;

        $Commissions->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Commissions Updated',
                'message' => 'Employee Commissions record updated successfully'
            ]),
            'module_id' => $Commissions->id,
            'module_type' => 'Commissions',
            'notification_type' => 'Commissions Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Commissions successfully updated.',
            'data' => $Commissions
        ], 200);
    }


    public function deleteEmployeeCommissions(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:commissions,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $Commissions = Commission::where('id', $request->id)->first();


        $Commissions->delete();

        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'Commissions Deleted',
                'message' => 'An Commissions record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'Commissions',
            'notification_type' => 'Commissions Deleted'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Commissions successfully deleted.',
        ], 200);
    }

}
