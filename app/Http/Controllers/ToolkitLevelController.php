<?php

namespace App\Http\Controllers;

use App\Models\ToolkitLevel;
use Illuminate\Http\Request;

class ToolkitLevelController extends Controller
{
    public function getToolkitLevelPluck(Request $request)
    {
        $toolkitLevels = ToolkitLevel::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $toolkitLevels
        ], 200);
    }

    public function getToolkitLevels()
    {
        $toolkitLevels = ToolkitLevel::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $toolkitLevels
        ], 200);
    }

    public function addToolkitLevel(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $toolkitLevel = ToolkitLevel::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $toolkitLevel->name . " toolkit level created",
                'message' => $toolkitLevel->name . " toolkit level created",
            ]),
            'module_id' => $toolkitLevel->id,
            'module_type' => 'toolkit_level',
            'notification_type' => 'Toolkit Level Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit level successfully created.'),
            'data' => $toolkitLevel
        ], 201);
    }

    public function updateToolkitLevel(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:toolkit_levels,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $toolkitLevel = ToolkitLevel::where('id', $request->id)->first();

        if (!$toolkitLevel) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit level not found.')
            ], 404);
        }

        $originalData = $toolkitLevel->toArray();

        $toolkitLevel->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($toolkitLevel->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $toolkitLevel->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $toolkitLevel->name . " toolkit level updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $toolkitLevel->id,
                'module_type' => 'toolkit_level',
                'notification_type' => 'Toolkit Level Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit level successfully updated.'),
            'data' => $toolkitLevel
        ], 200);
    }

    public function deleteToolkitLevel(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:toolkit_levels,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $toolkitLevel = ToolkitLevel::where('id', $request->id)->first();

        if (!$toolkitLevel) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit level not found.')
            ], 404);
        }

        $toolkitLevelName = $toolkitLevel->name;
        $toolkitLevelId = $toolkitLevel->id;

        $toolkitLevel->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $toolkitLevelName . " toolkit level deleted",
                'message' => $toolkitLevelName . " toolkit level deleted"
            ]),
            'module_id' => $toolkitLevelId,
            'module_type' => 'toolkit_level',
            'notification_type' => 'Toolkit Level Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit level successfully deleted.')
        ], 200);
    }
}
