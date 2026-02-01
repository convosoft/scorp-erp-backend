<?php

namespace App\Http\Controllers;

use App\Models\ModuleType;
use Illuminate\Http\Request;

class ModuleTypeController extends Controller
{
    public function getModuleTypePluck(Request $request)
    {
        $moduleTypes = ModuleType::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $moduleTypes
        ], 200);
    }

    public function getModuleTypes()
    {
        $moduleTypes = ModuleType::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $moduleTypes
        ], 200);
    }

    public function addModuleType(Request $request)
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

        $moduleType = ModuleType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $moduleType->name . " module type created",
                'message' => $moduleType->name . " module type created",
            ]),
            'module_id' => $moduleType->id,
            'module_type' => 'module_type',
            'notification_type' => 'Module Type Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Module type successfully created.'),
            'data' => $moduleType
        ], 201);
    }

    public function updateModuleType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:module_types,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $moduleType = ModuleType::where('id', $request->id)->first();

        if (!$moduleType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Module type not found.')
            ], 404);
        }

        $originalData = $moduleType->toArray();

        $moduleType->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($moduleType->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $moduleType->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $moduleType->name . " module type updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $moduleType->id,
                'module_type' => 'module_type',
                'notification_type' => 'Module Type Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Module type successfully updated.'),
            'data' => $moduleType
        ], 200);
    }

    public function deleteModuleType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:module_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $moduleType = ModuleType::where('id', $request->id)->first();

        if (!$moduleType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Module type not found.')
            ], 404);
        }

        $moduleTypeName = $moduleType->name;
        $moduleTypeId = $moduleType->id;

        $moduleType->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $moduleTypeName . " module type deleted",
                'message' => $moduleTypeName . " module type deleted"
            ]),
            'module_id' => $moduleTypeId,
            'module_type' => 'module_type',
            'notification_type' => 'Module Type Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Module type successfully deleted.')
        ], 200);
    }
}