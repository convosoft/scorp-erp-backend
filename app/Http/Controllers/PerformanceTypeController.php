<?php

namespace App\Http\Controllers;

use App\Models\PerformanceType;
use Illuminate\Http\Request;

class PerformanceTypeController extends Controller
{
    public function getPerformanceTypes()
    {
        $performanceTypes = PerformanceType::get();

        return response()->json([
            'status' => 'success',
            'data' => $performanceTypes
        ], 200);
    }

    public function addPerformanceType(Request $request)
    {
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

        $performanceType = PerformanceType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Performance type successfully created.'),
            'data' => $performanceType
        ], 201);
    }

    public function updatePerformanceType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:performance_types,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $performanceType = PerformanceType::where('id', $request->id)->first();

        if (!$performanceType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Performance type not found or unauthorized.')
            ], 404);
        }

        $performanceType->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Performance type successfully updated.'),
            'data' => $performanceType
        ], 200);
    }

    public function deletePerformanceType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:performance_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $performanceType = PerformanceType::where('id', $request->id)->first();

        if (!$performanceType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Performance type not found or unauthorized.')
            ], 404);
        }

        $performanceType->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Performance type successfully deleted.')
        ], 200);
    }
}
