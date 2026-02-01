<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementCategory;
use Illuminate\Http\Request;

class AnnouncementCategoryController extends Controller
{
    public function getAnnouncementCategoryPluck()
    {
       

        $categories = AnnouncementCategory::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ], 200);
    }

    public function getAnnouncementCategories()
    {
        if (!\Auth::user()->can('manage Announcement category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $categories = AnnouncementCategory::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ], 200);
    }

    public function addAnnouncementCategory(Request $request)
    {
        if (!\Auth::user()->can('create Announcement category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied. create Announcement category')
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

        $category = AnnouncementCategory::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Announcement Category Created',
                'message' => "A new category '{$category->name}' has been created successfully.",
            ]),
            'module_id' => $category->id,
            'module_type' => 'Announcement_category',
            'notification_type' => 'Category Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Announcement Category successfully created.'),
            'data' => $category
        ], 201);
    }

    public function updateAnnouncementCategory(Request $request)
    {
        if (!\Auth::user()->can('edit Announcement category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:Announcement_categories,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $category = AnnouncementCategory::where('id', $request->id)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => __('Announcement Category not found or unauthorized.')
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
                'title' => 'Announcement Category Updated',
                'message' => "Category '{$category->name}' has been updated successfully.",
            ]),
            'module_id' => $category->id,
            'module_type' => 'Announcement_category',
            'notification_type' => 'Category Updated',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Announcement Category successfully updated.'),
            'data' => $category
        ], 200);
    }

    public function deleteAnnouncementCategory(Request $request)
    {
        if (!\Auth::user()->can('delete Announcement category')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:Announcement_categories,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $category = AnnouncementCategory::where('id', $request->id)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => __('Announcement Category not found or unauthorized.')
            ], 404);
        }

        $categoryName = $category->name;
        $categoryId = $category->id;

        $category->delete();

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Announcement Category Deleted',
                'message' => "Category '{$categoryName}' has been deleted successfully.",
            ]),
            'module_id' => $categoryId,
            'module_type' => 'Announcement_category',
            'notification_type' => 'Category Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Announcement Category successfully deleted.')
        ], 200);
    }
}
