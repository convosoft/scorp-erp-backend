<?php

namespace App\Http\Controllers;

use App\Models\EducationDepartment;
use Illuminate\Http\Request;

class EducationDepartmentsController extends Controller
{
    public function getEducationDepartmentsPluck()
    {
        $data = EducationDepartment::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function getEducationDepartments()
    {
        $data = EducationDepartment::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function addEducationDepartment(Request $request)
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

        $item = EducationDepartment::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $item->name . " education department created",
                'message' => $item->name . " education department created",
            ]),
            'module_id' => $item->id,
            'module_type' => 'education_department',
            'notification_type' => 'education department Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('education department created.'),
            'data' => $item
        ], 201);
    }

    public function updateEducationDepartment(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:education_departments,id',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $item = EducationDepartment::find($request->id);

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
                    'title' => $item->name . " education department updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $item->id,
                'module_type' => 'education_department',
                'notification_type' => 'education department Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Updated'),
            'data' => $item
        ], 200);
    }

    public function deleteEducationDepartment(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:education_departments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $item = EducationDepartment::find($request->id);

        $name = $item->name;
        $id = $item->id;

        $item->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $name . " education department deleted",
                'message' => $name . " education department deleted"
            ]),
            'module_id' => $id,
            'module_type' => 'education_department',
            'notification_type' => 'education department Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Deleted')
        ], 200);
    }
}
