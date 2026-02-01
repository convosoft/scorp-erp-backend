<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function getDesignationPluck(Request $request)
    {
        $designations = Designation::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $designations
        ], 200);
    }

    public function getDesignations()
    {
        $designations = Designation::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $designations
        ], 200);
    }

    public function addDesignation(Request $request)
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

        $designation = Designation::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $designation->name . " designation created",
                'message' => $designation->name . " designation created",
            ]),
            'module_id' => $designation->id,
            'module_type' => 'designation',
            'notification_type' => 'Designation Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Designation successfully created.'),
            'data' => $designation
        ], 201);
    }

    public function updateDesignation(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:designations,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $designation = Designation::where('id', $request->id)->first();

        if (!$designation) {
            return response()->json([
                'status' => 'error',
                'message' => __('Designation not found.')
            ], 404);
        }

        $originalData = $designation->toArray();

        $designation->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($designation->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $designation->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $designation->name . " designation updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $designation->id,
                'module_type' => 'designation',
                'notification_type' => 'Designation Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Designation successfully updated.'),
            'data' => $designation
        ], 200);
    }

    public function deleteDesignation(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:designations,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $designation = Designation::where('id', $request->id)->first();

        if (!$designation) {
            return response()->json([
                'status' => 'error',
                'message' => __('Designation not found.')
            ], 404);
        }

        $designationName = $designation->name;
        $designationId = $designation->id;

        $designation->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $designationName . " designation deleted",
                'message' => $designationName . " designation deleted"
            ]),
            'module_id' => $designationId,
            'module_type' => 'designation',
            'notification_type' => 'Designation Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Designation successfully deleted.')
        ], 200);
    }
}
