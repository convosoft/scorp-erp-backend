<?php

namespace App\Http\Controllers;

use App\Models\Permission; 
use Illuminate\Http\Request;
use App\Models\ModuleType;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function allPermissions()
{
    $modules = ModuleType::with([
        'permissions.permissionType'
    ])->get();

    $result = $modules->map(function ($module) {
        return [
            'moduleTypeName' => $module->name,
            'permissionTypes' => $module->permissions
                ->groupBy('permissionType.name')
                ->map(function ($permissions, $permissionTypeName) {
                    return [
                        'name' => $permissionTypeName,
                        'permissions' => $permissions->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                            ];
                        })->values()
                    ];
                })->values()
        ];
    });

    return response()->json($result);
}
    public function getPermissionPluck(Request $request)
    {
        $permissions = Permission::pluck('name', 'id')
                        ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ], 200);
    }

    public function getPermissions()
    {
        $permissions = Permission::with([ 
                            'moduleType:id,name',
                            'permissionType:id,name'
                        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ], 200);
    }

    public function addPermission(Request $request)
    {
      $validator = \Validator::make(
    $request->all(),
    [
        'name' => 'required|string|unique:permissions,name',
        'module_type_id' => 'required|exists:module_types,id',
        'permission_type_id' => 'required|exists:permission_types,id'
    ],
    [
        'name.unique' => 'The permission name already exists.',
        'module_type_id.exists' => 'The selected module type is invalid.',
        'permission_type_id.exists' => 'The selected permission type is invalid.'
    ]
);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $permission = Permission::create([
            'guard_name' => 'web',
            'name' => $request->name,
            'module_type_id' => $request->module_type_id,
            'permission_type_id' => $request->permission_type_id
        ]);

                $r          = Role::where('id', '=', 1)->firstOrFail();
                
                $r->givePermissionTo($permission);
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $permission->name . " permission created",
                'message' => $permission->name . " permission created",
            ]),
            'module_id' => $permission->id,
            'module_type' => 'permission',
            'notification_type' => 'Permission Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Permission successfully created.'),
            'data' => $permission->load(['moduleType', 'permissionType'])
        ], 201);
    }

    public function updatePermission(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:permissions,id',
              //  'name' => 'required|string',
                'module_type_id' => 'required|exists:module_types,id',
                'permission_type_id' => 'required|exists:permission_types,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $permission = Permission::where('id', $request->id)->first();

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission not found.')
            ], 404);
        }

        $originalData = $permission->toArray();

        $permission->update([
         //   'name' => $request->name,
           
            'module_type_id' => $request->module_type_id,
            'permission_type_id' => $request->permission_type_id
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($permission->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $permission->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $permission->name . " permission updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $permission->id,
                'module_type' => 'permission',
                'notification_type' => 'Permission Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Permission successfully updated.'),
            'data' => $permission->load(['moduleType', 'permissionType'])
        ], 200);
    }

    public function deletePermission(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:permissions,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $permission = Permission::where('id', $request->id)->first();

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission not found.')
            ], 404);
        }

        $permissionName = $permission->name;
        $permissionId = $permission->id;

        $permission->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $permissionName . " permission deleted",
                'message' => $permissionName . " permission deleted"
            ]),
            'module_id' => $permissionId,
            'module_type' => 'permission',
            'notification_type' => 'Permission Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Permission successfully deleted.')
        ], 200);
    }
}