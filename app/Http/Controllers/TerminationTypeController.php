<?php

namespace App\Http\Controllers;

use App\Models\TerminationType;
use Illuminate\Http\Request;

class TerminationTypeController extends Controller
{
    public function getTerminationTypes()
    {
        if (!\Auth::user()->can('manage termination type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $terminationTypes = TerminationType::get();

        return response()->json([
            'status' => 'success',
            'data' => $terminationTypes
        ], 200);
    }

    public function addTerminationType(Request $request)
    {
        if (!\Auth::user()->can('create termination type')) {
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

        $terminationType = TerminationType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Termination type successfully created.'),
            'data' => $terminationType
        ], 201);
    }

    public function updateTerminationType(Request $request)
    {
        if (!\Auth::user()->can('edit termination type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:termination_types,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $terminationType = TerminationType::where('id', $request->id)->first();

        if (!$terminationType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Termination type not found or unauthorized.')
            ], 404);
        }

        $terminationType->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Termination type successfully updated.'),
            'data' => $terminationType
        ], 200);
    }

    public function deleteTerminationType(Request $request)
    {
        if (!\Auth::user()->can('delete termination type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:termination_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $terminationType = TerminationType::where('id', $request->id)->first();

        if (!$terminationType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Termination type not found or unauthorized.')
            ], 404);
        }

        $terminationType->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Termination type successfully deleted.')
        ], 200);
    }
}
