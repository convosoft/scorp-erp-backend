<?php

namespace App\Http\Controllers;

use App\Models\LoanOption;
use Illuminate\Http\Request;

class LoanOptionController extends Controller
{
    public function getLoanOptions()
    {
        if (!\Auth::user()->can('manage loan option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $loanOptions = LoanOption::get();

        return response()->json([
            'status' => 'success',
            'data' => $loanOptions
        ], 200);
    }


    public function pluckLoanOption()
    {
        if (!\Auth::user()->can('manage payslip type')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $LoanOption =    LoanOption::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        return response()->json(['status' => 'success', 'data' => $LoanOption], 200);
    }

    public function addLoanOption(Request $request)
    {
        if (!\Auth::user()->can('create loan option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['name' => 'required|string']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $loanOption = LoanOption::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Loan option successfully created.'),
            'data' => $loanOption
        ], 201);
    }

    public function updateLoanOption(Request $request)
    {
        if (!\Auth::user()->can('edit loan option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:loan_options,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $loanOption = LoanOption::where('id', $request->id)->first();

        if (!$loanOption) {
            return response()->json([
                'status' => 'error',
                'message' => __('Loan option not found or unauthorized.')
            ], 404);
        }

        $loanOption->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Loan option successfully updated.'),
            'data' => $loanOption
        ], 200);
    }

    public function deleteLoanOption(Request $request)
    {
        if (!\Auth::user()->can('delete loan option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:loan_options,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $loanOption = LoanOption::where('id', $request->id)->first();

        if (!$loanOption) {
            return response()->json([
                'status' => 'error',
                'message' => __('Loan option not found or unauthorized.')
            ], 404);
        }

        $loanOption->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Loan option successfully deleted.')
        ], 200);
    }
}
