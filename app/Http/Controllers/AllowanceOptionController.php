<?php

namespace App\Http\Controllers;

use App\Models\AllowanceOption;
use Illuminate\Http\Request;

class AllowanceOptionController extends Controller
{
    public function getAllowanceOptions()
    {
        if (!\Auth::user()->can('manage allowance option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $allowanceOptions = AllowanceOption::get();

        return response()->json([
            'status' => 'success',
            'data' => $allowanceOptions
        ], 200);
    }

    public function pluckAllowanceOptions()
    {
        if (!\Auth::user()->can('manage payslip type')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $AllowanceOption =    AllowanceOption::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        return response()->json(['status' => 'success', 'data' => $AllowanceOption], 200);
    }


    public function addAllowanceOption(Request $request)
    {
        if (!\Auth::user()->can('create allowance option')) {
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

        $allowanceOption = AllowanceOption::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Allowance option successfully created.'),
            'data' => $allowanceOption
        ], 201);
    }

    public function updateAllowanceOption(Request $request)
    {
        if (!\Auth::user()->can('edit allowance option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:allowance_options,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $allowanceOption = AllowanceOption::where('id', $request->id)->first();

        if (!$allowanceOption) {
            return response()->json([
                'status' => 'error',
                'message' => __('Allowance option not found or unauthorized.')
            ], 404);
        }

        $allowanceOption->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Allowance option successfully updated.'),
            'data' => $allowanceOption
        ], 200);
    }

    public function deleteAllowanceOption(Request $request)
    {
        if (!\Auth::user()->can('delete allowance option')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:allowance_options,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $allowanceOption = AllowanceOption::where('id', $request->id)->first();

        if (!$allowanceOption) {
            return response()->json([
                'status' => 'error',
                'message' => __('Allowance option not found or unauthorized.')
            ], 404);
        }

        $allowanceOption->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Allowance option successfully deleted.')
        ], 200);
    }
}
