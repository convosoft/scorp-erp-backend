<?php

namespace App\Http\Controllers;

use App\Models\JobCategory;
use Illuminate\Http\Request;

class JobCategoryController extends Controller
{
    public function getJobCategories()
    {
        if (!\Auth::user()->can('manage job category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $jobCategories = JobCategory::get();



        return response()->json([
            'status' => 'success',
            'data' => $jobCategories
        ], 200);
    }

    public function addJobCategory(Request $request)
    {
        if (!\Auth::user()->can('create job category')) {
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

        $jobCategory = JobCategory::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Job category successfully created.'),
            'data' => $jobCategory
        ], 201);
    }

    public function updateJobCategory(Request $request)
    {
        if (!\Auth::user()->can('edit job category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:job_categories,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $jobCategory = JobCategory::where('id', $request->id)->first();

        if (!$jobCategory) {
            return response()->json([
                'status' => 'error',
                'message' => __('Job category not found or unauthorized.')
            ], 404);
        }

        $jobCategory->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Job category successfully updated.'),
            'data' => $jobCategory
        ], 200);
    }

    public function deleteJobCategory(Request $request)
    {
        if (!\Auth::user()->can('delete job category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:job_categories,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $jobCategory = JobCategory::where('id', $request->id)->first();

        if (!$jobCategory) {
            return response()->json([
                'status' => 'error',
                'message' => __('Job category not found or unauthorized.')
            ], 404);
        }

        $jobCategory->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Job category successfully deleted.')
        ], 200);
    }
}
