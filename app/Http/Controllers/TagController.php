<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function getTagPluck(Request $request)
    {

            $validator = \Validator::make(
            $request->all(),
            [ 
                'type' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $tags = Tag::where('type', $request->type)->pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $tags
        ], 200);
    }

    public function getTags()
    {
        $tags = Tag::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $tags
        ], 200);
    }

    public function addTag(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string',
                'type' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $tag = Tag::create([
            'name' => $request->name,
            'type' => $request->type,
            'created_by' => \Auth::id()
        ]);

        $typeoflog = 'tag';

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $tag->name . " $typeoflog created",
                'message' => $tag->name . " $typeoflog created",
            ]),
            'module_id' => $tag->id,
            'module_type' => $typeoflog,
            'notification_type' => ucfirst($typeoflog) . ' Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Tag successfully created.'),
            'data' => $tag
        ], 201);
    }

    public function updateTag(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:tags,id',
                'name' => 'required|string',
                'type' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $tag = Tag::where('id', $request->id)->first();

        if (!$tag) {
            return response()->json([
                'status' => 'error',
                'message' => __('Tag not found.')
            ], 404);
        }

        $originalData = $tag->toArray();

        $tag->update([
            'name' => $request->name,
            'type' => $request->type,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($tag->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $tag->$field
                ];
                $updatedFields[] = $field;
            }
        }

        $typeoflog = 'tag';

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $tag->name . " $typeoflog updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $tag->id,
                'module_type' => $typeoflog,
                'notification_type' => ucfirst($typeoflog) . ' Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Tag successfully updated.'),
            'data' => $tag
        ], 200);
    }

    public function deleteTag(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:tags,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $tag = Tag::where('id', $request->id)->first();

        if (!$tag) {
            return response()->json([
                'status' => 'error',
                'message' => __('Tag not found.')
            ], 404);
        }

        $typeoflog = 'tag';
        $tagName = $tag->name;
        $tagId = $tag->id;

        $tag->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $tagName . " $typeoflog deleted",
                'message' => $tagName . " $typeoflog deleted"
            ]),
            'module_id' => $tagId,
            'module_type' => $typeoflog,
            'notification_type' => ucfirst($typeoflog) . ' Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Tag successfully deleted.')
        ], 200);
    }
}
