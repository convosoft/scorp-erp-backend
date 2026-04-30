<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use Illuminate\Http\Request;

class EducationLevelsController extends Controller
{
    public function getEducationLevelsPluck()
    {
        $data = EducationLevel::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function getEducationLevels()
    {
        $data = EducationLevel::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function addEducationLevel(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $item = EducationLevel::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $item->name . " education level created",
                'message' => $item->name . " education level created",
            ]),
            'module_id' => $item->id,
            'module_type' => 'education_level',
            'notification_type' => 'Education Level Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Education level created.'),
            'data' => $item
        ], 201);
    }

    public function updateEducationLevel(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:education_levels,id',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $item = EducationLevel::find($request->id);

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not found'
            ], 404);
        }

        $original = $item->toArray();

        $item->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($original as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) continue;

            if ($item->$field != $oldValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $item->$field];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $item->name . " education level updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $item->id,
                'module_type' => 'education_level',
                'notification_type' => 'Education Level Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Updated'),
            'data' => $item
        ], 200);
    }

    public function deleteEducationLevel(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:education_levels,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $item = EducationLevel::find($request->id);

        $name = $item->name;
        $id = $item->id;

        $item->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $name . " education level deleted",
                'message' => $name . " education level deleted"
            ]),
            'module_id' => $id,
            'module_type' => 'education_level',
            'notification_type' => 'Education Level Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Deleted')
        ], 200);
    }
}
