<?php

namespace App\Http\Controllers;

use App\Models\InstituteCategory;
use Illuminate\Http\Request;

class InstituteCategoryController extends Controller
{
    public function getInstituteCategoryPluck()
    {
        if (!\Auth::user()->can('manage institute category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $categories = InstituteCategory::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ], 200);
    }

    public function getInstituteCategories()
    {
        if (!\Auth::user()->can('manage institute category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $categories = InstituteCategory::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ], 200);
    }

    public function addInstituteCategory(Request $request)
    {
        if (!\Auth::user()->can('create institute category')) {
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

        $category = InstituteCategory::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Institute Category Created',
                'message' => "A new category '{$category->name}' has been created successfully.",
            ]),
            'module_id' => $category->id,
            'module_type' => 'institute_category',
            'notification_type' => 'Category Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Institute Category successfully created.'),
            'data' => $category
        ], 201);
    }

    public function updateInstituteCategory(Request $request)
    {
        if (!\Auth::user()->can('edit institute category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:institute_categories,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $category = InstituteCategory::where('id', $request->id)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => __('Institute Category not found or unauthorized.')
            ], 404);
        }

        $category->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Institute Category Updated',
                'message' => "Category '{$category->name}' has been updated successfully.",
            ]),
            'module_id' => $category->id,
            'module_type' => 'institute_category',
            'notification_type' => 'Category Updated',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Institute Category successfully updated.'),
            'data' => $category
        ], 200);
    }

    public function deleteInstituteCategory(Request $request)
    {
        if (!\Auth::user()->can('delete institute category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:institute_categories,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $category = InstituteCategory::where('id', $request->id)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => __('Institute Category not found or unauthorized.')
            ], 404);
        }

        $categoryName = $category->name;
        $categoryId = $category->id;

        $category->delete();

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Institute Category Deleted',
                'message' => "Category '{$categoryName}' has been deleted successfully.",
            ]),
            'module_id' => $categoryId,
            'module_type' => 'institute_category',
            'notification_type' => 'Category Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Institute Category successfully deleted.')
        ], 200);
    }
}
