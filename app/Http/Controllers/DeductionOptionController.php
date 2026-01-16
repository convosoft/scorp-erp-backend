<?php

namespace App\Http\Controllers;

use App\Models\DeductionOption;
use Illuminate\Http\Request;

class DeductionOptionController extends Controller
{
    public function getDeductionOptions()
    {
        if (!\Auth::user()->can('manage deduction option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $deductionOptions = DeductionOption::get();

        return response()->json([
            'status' => 'success',
            'data' => $deductionOptions
        ], 200);
    }


    public function pluckDeductionOption()
    {


        $DeductionOption =    DeductionOption::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        return response()->json(['status' => 'success', 'data' => $DeductionOption], 200);
    }

    public function addDeductionOption(Request $request)
    {
        if (!\Auth::user()->can('create deduction option')) {
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

        $deductionOption = DeductionOption::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Deduction option successfully created.'),
            'data' => $deductionOption
        ], 201);
    }

    public function updateDeductionOption(Request $request)
    {
        if (!\Auth::user()->can('edit deduction option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:deduction_options,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $deductionOption = DeductionOption::where('id', $request->id)->first();

        if (!$deductionOption) {
            return response()->json([
                'status' => 'error',
                'message' => __('Deduction option not found or unauthorized.')
            ], 404);
        }

        $deductionOption->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Deduction option successfully updated.'),
            'data' => $deductionOption
        ], 200);
    }

    public function deleteDeductionOption(Request $request)
    {
        if (!\Auth::user()->can('delete deduction option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:deduction_options,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $deductionOption = DeductionOption::where('id', $request->id)->first();

        if (!$deductionOption) {
            return response()->json([
                'status' => 'error',
                'message' => __('Deduction option not found or unauthorized.')
            ], 404);
        }

        $deductionOption->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Deduction option successfully deleted.')
        ], 200);
    }
}
