<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function getRolePluck(Request $request)
    {
        if(!\Auth::user()->can('manage role')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $roles = Role::pluck('name', 'id')
                    ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $roles
        ], 200);
    }

    public function getRoles()
    {
        if(!\Auth::user()->can('manage role')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $roles = Role::all();

        return response()->json([
            'status' => 'success',
            'data' => $roles
        ], 200);
    }

    
    public function getRoleDetail(Request $request)
    {
        if(!\Auth::user()->can('edit role')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:roles,id'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $role = Role::find($request->id);
        
 

        return response()->json([
            'status' => 'success',
            'message' => __('Role successfully updated.'), 
            'permission' => $role->load('permissions')
        ], 200);
    }


    public function addRole(Request $request)
    {
        if(!\Auth::user()->can('create role')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|max:100|unique:roles,name',
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id'
            ],
            [
                'name.unique' => 'The role name already exists.',
                'permissions.required' => 'At least one permission is required.'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'created_by' => \Auth::user()->creatorId()
        ]);

        $permissions = Permission::whereIn('id', $request->permissions)->get();
        $role->syncPermissions($permissions);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $role->name . " role created",
                'message' => $role->name . " role created with " . count($permissions) . " permissions",
            ]),
            'module_id' => $role->id,
            'module_type' => 'role',
            'notification_type' => 'Role Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Role successfully created.'),
            'data' => $role->load('permissions')
        ], 201);
    }

    public function updateRole(Request $request)
    {
        if(!\Auth::user()->can('edit role')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:roles,id',
                'name' => 'required|max:100|unique:roles,name,'.$request->id,
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id'
            ],
            [
                'name.unique' => 'The role name already exists.',
                'permissions.required' => 'At least one permission is required.'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $role = Role::find($request->id);
        $originalData = $role->toArray();

        $role->update([
            'name' => $request->name,
            'created_by' => \Auth::user()->creatorId()
        ]);

        $permissions = Permission::whereIn('id', $request->permissions)->get();
        $role->syncPermissions($permissions);

        $changes = [];
        foreach ($originalData as $key => $value) {
            if ($role->$key != $value && !in_array($key, ['created_at', 'updated_at'])) {
                $changes[$key] = [
                    'old' => $value,
                    'new' => $role->$key
                ];
            }
        }

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => $role->name . " role updated",
                'message' => 'Permissions updated: ' . $permissions->pluck('name')->implode(', '),
                'changes' => $changes
            ]),
            'module_id' => $role->id,
            'module_type' => 'role',
            'notification_type' => 'Role Updated',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Role successfully updated.'),
            'data' => $role->load('permissions')
        ], 200);
    }

    public function deleteRole(Request $request)
    {
        if(!\Auth::user()->can('delete role')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:roles,id'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $role = Role::find($request->id);
        $roleName = $role->name;
        $roleId = $role->id;

        $role->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $roleName . " role deleted",
                'message' => $roleName . " role deleted"
            ]),
            'module_id' => $roleId,
            'module_type' => 'role',
            'notification_type' => 'Role Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Role successfully deleted.')
        ], 200);
    }
}