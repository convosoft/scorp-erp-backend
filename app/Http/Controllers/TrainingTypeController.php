<?php

namespace App\Http\Controllers;

use App\Models\TrainingType;
use Illuminate\Http\Request;

class TrainingTypeController extends Controller
{
    public function TrainingTypes()
    {
        if (!\Auth::user()->can('manage training type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $trainingTypes = TrainingType::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $trainingTypes
        ], 200);
    }
    public function getTrainingTypes()
    {
        if (!\Auth::user()->can('manage training type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $trainingTypes = TrainingType::get();

        return response()->json([
            'status' => 'success',
            'data' => $trainingTypes
        ], 200);
    }

    public function addTrainingType(Request $request)
    {
        if (!\Auth::user()->can('create training type')) {
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

        $trainingType = TrainingType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Training Type successfully created.'),
            'data' => $trainingType
        ], 201);
    }

    public function updateTrainType(Request $request)
    {
        if (!\Auth::user()->can('edit training type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:training_types,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $trainingType = TrainingType::where('id', $request->id)
          //  ->where('created_by', \Auth::id())
            ->first();

        if (!$trainingType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Training Type not found or unauthorized.')
            ], 404);
        }

        $trainingType->update(['name' => $request->name,'created_by'=>\Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Training Type successfully updated.'),
            'data' => $trainingType
        ], 200);
    }

    public function deleteTrainingType(Request $request)
    {
        if (!\Auth::user()->can('delete training type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:training_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $trainingType = TrainingType::where('id', $request->id)
           // ->where('created_by', \Auth::id())
            ->first();

        if (!$trainingType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Training Type not found or unauthorized.')
            ], 404);
        }

        $trainingType->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Training Type successfully deleted.')
        ], 200);
    }
}
