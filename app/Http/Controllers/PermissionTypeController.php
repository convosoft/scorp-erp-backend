<?php

namespace App\Http\Controllers;

use App\Models\PermissionType;
use Illuminate\Http\Request;

class PermissionTypeController extends Controller
{
    public function getPermissionTypePluck(Request $request)
    {
        $permissionTypes = PermissionType::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $permissionTypes
        ], 200);
    }

    public function getPermissionTypes()
    {
        $permissionTypes = PermissionType::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $permissionTypes
        ], 200);
    }

    public function addPermissionType(Request $request)
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

        $permissionType = PermissionType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $permissionType->name . " permission type created",
                'message' => $permissionType->name . " permission type created",
            ]),
            'module_id' => $permissionType->id,
            'module_type' => 'permission_type',
            'notification_type' => 'Permission Type Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Permission type successfully created.'),
            'data' => $permissionType
        ], 201);
    }

    public function updatePermissionType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:permission_types,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $permissionType = PermissionType::where('id', $request->id)->first();

        if (!$permissionType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission type not found.')
            ], 404);
        }

        $originalData = $permissionType->toArray();

        $permissionType->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($permissionType->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $permissionType->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $permissionType->name . " permission type updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $permissionType->id,
                'module_type' => 'permission_type',
                'notification_type' => 'Permission Type Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Permission type successfully updated.'),
            'data' => $permissionType
        ], 200);
    }

    public function deletePermissionType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:permission_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $permissionType = PermissionType::where('id', $request->id)->first();

        if (!$permissionType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission type not found.')
            ], 404);
        }

        $permissionTypeName = $permissionType->name;
        $permissionTypeId = $permissionType->id;

        $permissionType->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $permissionTypeName . " permission type deleted",
                'message' => $permissionTypeName . " permission type deleted"
            ]),
            'module_id' => $permissionTypeId,
            'module_type' => 'permission_type',
            'notification_type' => 'Permission Type Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Permission type successfully deleted.')
        ], 200);
    }
}