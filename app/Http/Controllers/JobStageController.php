<?php

namespace App\Http\Controllers;

use App\Models\JobStage;
use Illuminate\Http\Request;

class JobStageController extends Controller
{

    
    public function PluckJobStages()
    {
        if (!\Auth::user()->can('manage job stage')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $jobStages = JobStage::orderBy('title', 'ASC')->pluck('title', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $jobStages
        ], 200);
    }
    public function getJobStages()
    {
        if (!\Auth::user()->can('manage job stage')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $jobStages = JobStage::get();

        return response()->json([
            'status' => 'success',
            'data' => $jobStages
        ], 200);
    }

    public function addJobStage(Request $request)
    {
        if (!\Auth::user()->can('create job stage')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['title' => 'required|string']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $jobStage = JobStage::create([
            'title' => $request->title,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Job stage successfully created.'),
            'data' => $jobStage
        ], 201);
    }

    public function updateJobStage(Request $request)
    {
        if (!\Auth::user()->can('edit job stage')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:job_stages,id',
                'title' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $jobStage = JobStage::where('id', $request->id)->first();

        if (!$jobStage) {
            return response()->json([
                'status' => 'error',
                'message' => __('Job stage not found or unauthorized.')
            ], 404);
        }

        $jobStage->update(['title' => $request->title, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Job stage successfully updated.'),
            'data' => $jobStage
        ], 200);
    }

    public function deleteJobStage(Request $request)
    {
        if (!\Auth::user()->can('delete job stage')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:job_stages,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $jobStage = JobStage::where('id', $request->id)->first();

        if (!$jobStage) {
            return response()->json([
                'status' => 'error',
                'message' => __('Job stage not found or unauthorized.')
            ], 404);
        }

        $jobStage->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Job stage successfully deleted.')
        ], 200);
    }
}
