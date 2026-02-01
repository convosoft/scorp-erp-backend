<?php


namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoanController extends Controller
{
    public function getLoans(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        // Fetch Loans
        $Loans = Loan::with('loan_option')->where('employee_id', $validated['employee_id'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Loans retrieved successfully.',
            'data'    => $Loans
        ], 200);
    }


    public function addEmployeeLoan(Request $request)
    {
        if (!Auth::user()->can('create loan')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'loan_option' => 'required|exists:loan_options,id',
            'title' => 'required',
            'type' => 'required',
            'amount' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $loan = new Loan();
        $loan->employee_id = $request->employee_id;
        $loan->loan_option = $request->loan_option;
        $loan->title = $request->title;
        $loan->amount = $request->amount;
        $loan->type = $request->type ?? null;
        $loan->start_date = $request->start_date;
        $loan->end_date = $request->end_date;
        $loan->reason = $request->reason;
        $loan->created_by = Auth::user()->creatorId();
        $loan->save();

       

             //  ========== add ============
                $user = User::find($loan->employee_id);
                $typeoflog = 'loan';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' =>  $loan->employee->name. ' '.$typeoflog.' created',
                        'message' =>  $loan->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $loan->employee_id,
                    'module_type' => 'setsalary',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' =>  $loan->employee->name. ' '.$typeoflog.'  created',
                        'message' =>  $loan->employee->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $loan->employee_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

        return response()->json(['success' => __('Loan successfully created.'), 'loan' => $loan], 201);
    }

    public function show($id)
    {
        $loan = Loan::where('id', $id)->first();

        if (!$loan) {
            return response()->json(['error' => __('Loan not found.')], 404);
        }

        return response()->json(['loan' => $loan], 200);
    }

    public function updateEmployeeLoan(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|exists:loans,id',
            'loan_option' => 'required|exists:loan_options,id',
            'title' => 'required',
            'amount' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $loan = Loan::where('id', $request->loan_id)->first();

        if (!$loan) {
            return response()->json(['error' => __('Loan not found.')], 404);
        }



        $loan->loan_option = $request->loan_option;
        $loan->title = $request->title;
        $loan->type = $request->type ?? null;
        $loan->amount = $request->amount;
        $loan->start_date = $request->start_date;
        $loan->end_date = $request->end_date;
        $loan->reason = $request->reason;
        $loan->type = $request->type;
        $loan->save();


             // Log Activity
             addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'loan Created',
                    'message' => 'Employee load record updated successfully'
                ]),
                'module_id' => $loan->id,
                'module_type' => 'loan',
                'notification_type' => 'loan updated'
            ]);

        return response()->json(['success' => __('Loan successfully updated.'), 'loan' => $loan], 200);
    }

    public function deleteEmployeeLoan(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:loans,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $loan = Loan::where('id', $request->id)->first();

        if (!$loan) {
            return response()->json(['error' => __('Loan not found.')], 404);
        }



        $loan->delete();


        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'Loan Deleted',
                'message' => 'An Loan record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'Loan',
            'notification_type' => 'Loan Deleted'
        ]);

        return response()->json(['success' => __('Loan successfully deleted.')], 200);
    }
}
