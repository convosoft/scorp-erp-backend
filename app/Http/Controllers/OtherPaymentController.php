<?php

namespace App\Http\Controllers;

use App\Models\OtherPayment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class OtherPaymentController extends Controller
{

    public function getOtherPayments(Request $request)
{
    // Validate input
    $validated = $request->validate([
        'employee_id' => 'required|exists:employees,id',
    ]);

    // Fetch OtherPayment
    $OtherPayment = OtherPayment::where('employee_id', $validated['employee_id'])->get();

    // Return response
    return response()->json([
        'success' => true,
        'message' => 'OtherPayment retrieved successfully.',
        'data'    => $OtherPayment
    ], 200);
}


    public function addEmpoyeeOtherPayment(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create other payment')) {
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

        // Create and save OtherPayment record
        $OtherPayment = new OtherPayment();
        $OtherPayment->employee_id = $request->employee_id;
        $OtherPayment->title = $request->title;
        $OtherPayment->type = $request->type;
        $OtherPayment->amount = $request->amount;
        $OtherPayment->created_by = Auth::user()->creatorId();
        $OtherPayment->save();
         

         //  ========== add ============
                $user = User::find($OtherPayment->employee_id);
                $typeoflog = 'Other Payment';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $OtherPayment->employee->name. ' '.$typeoflog.' created',
                        'message' => $OtherPayment->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $OtherPayment->employee_id,
                    'module_type' => 'setsalary',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $OtherPayment->employee->name. ' '.$typeoflog.'  created',
                        'message' => $OtherPayment->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $OtherPayment->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

        return response()->json([
            'status' => 'success',
            'message' => 'OtherPayment successfully created.',
            'data' => $OtherPayment
        ], 201);
    }

    public function show(OtherPayment $OtherPayment)
    {
        return redirect()->route('OtherPayment.index');
    }



    public function updateEmployeeOtherPayment(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit other payment')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'OtherPayment_id'     => 'required|exists:other_payments,id',
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

        // Fetch OtherPayment Record
        $OtherPayment = OtherPayment::where('id', $request->OtherPayment_id)->first();

        if (!$OtherPayment) {
            return response()->json([
                'status' => 'error',
                'message' => 'OtherPayment record not found.'
            ], 404);
        }

        // Preserve existing values if not provided
        $OtherPayment->employee_id = $request->employee_id ?? $OtherPayment->employee_id;
        $OtherPayment->title = $request->title ?? $OtherPayment->title;
        $OtherPayment->type = $request->type ?? $OtherPayment->type;
        $OtherPayment->amount = $request->amount ?? $OtherPayment->amount;

        $OtherPayment->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'OtherPayment Updated',
                'message' => 'Employee OtherPayment record updated successfully'
            ]),
            'module_id' => $OtherPayment->id,
            'module_type' => 'OtherPayment',
            'notification_type' => 'OtherPayment Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'OtherPayment successfully updated.',
            'data' => $OtherPayment
        ], 200);
    }


    public function deleteEmployeeOtherPayment(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:other_payments,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $OtherPayment = OtherPayment::where('id', $request->id)->first();


        $OtherPayment->delete();

        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'OtherPayment Deleted',
                'message' => 'An OtherPayment record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'OtherPayment',
            'notification_type' => 'OtherPayment Deleted'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'OtherPayment successfully deleted.',
        ], 200);
    }

}
