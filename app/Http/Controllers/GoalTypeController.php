<?php

namespace App\Http\Controllers;

use App\Models\GoalType;
use Illuminate\Http\Request;

class GoalTypeController extends Controller
{
    public function pluckGoalTypes()
    {
        $GoalType = GoalType::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        return response()->json(['status' => 'success', 'data' => $GoalType], 200);
    }

    public function getGoalTypes()
    {
        if (!\Auth::user()->can('manage goal type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $goalTypes = GoalType::get();

        return response()->json([
            'status' => 'success',
            'data' => $goalTypes
        ], 200);
    }

    public function addGoalType(Request $request)
    {
        if (!\Auth::user()->can('create goal type')) {
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

        $goalType = GoalType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Goal type successfully created.'),
            'data' => $goalType
        ], 201);
    }

    public function updateGoalType(Request $request)
    {
        if (!\Auth::user()->can('edit goal type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:goal_types,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $goalType = GoalType::where('id', $request->id)->first();

        if (!$goalType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Goal type not found or unauthorized.')
            ], 404);
        }

        $goalType->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Goal type successfully updated.'),
            'data' => $goalType
        ], 200);
    }

    public function deleteGoalType(Request $request)
    {
        if (!\Auth::user()->can('delete goal type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:goal_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $goalType = GoalType::where('id', $request->id)->first();

        if (!$goalType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Goal type not found or unauthorized.')
            ], 404);
        }

        $goalType->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Goal type successfully deleted.')
        ], 200);
    }
}
