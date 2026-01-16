<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{

    public function departmentsPluck()
    {

        $Departments = Department::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $Departments
        ], 200);
    }
    public function getDepartments()
    {
        if (!\Auth::user()->can('manage department')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $Departments = Department::get();

        return response()->json([
            'status' => 'success',
            'data' => $Departments
        ], 200);
    }

    public function addDepartment(Request $request)
    {
        if (!\Auth::user()->can('create department')) {
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

        $Department = Department::create([
            'name' => $request->name,
            'branch_id' => 0,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('department successfully created.'),
            'data' => $Department
        ], 201);
    }

    public function updateDepartment(Request $request)
    {
        if (!\Auth::user()->can('edit department')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:departments,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $Department = Department::where('id', $request->id)
          //  ->where('created_by', \Auth::id())
            ->first();

        if (!$Department) {
            return response()->json([
                'status' => 'error',
                'message' => __('department not found or unauthorized.')
            ], 404);
        }

        $Department->update(['name' => $request->name,'created_by'=>\Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('department successfully updated.'),
            'data' => $Department
        ], 200);
    }

    public function deleteDepartment(Request $request)
    {
        if (!\Auth::user()->can('delete department')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:departments,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $Department = Department::where('id', $request->id)
           // ->where('created_by', \Auth::id())
            ->first();

        if (!$Department) {
            return response()->json([
                'status' => 'error',
                'message' => __('department not found or unauthorized.')
            ], 404);
        }

        $Department->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('department successfully deleted.')
        ], 200);
    }
}
