<?php

namespace App\Http\Controllers;

use App\Models\TypesDocument;
use Illuminate\Http\Request;

class TypesDocumentController extends Controller
{
    /**
     * Get pluck (name => id)
     */
    public function getTypesDocumentPluck()
    {
        $data = TypesDocument::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Get all records
     */
    public function getTypesDocuments()
    {
        $data = TypesDocument::get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Create
     */
    public function addTypesDocument(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:191',
                'type' => 'required|in:CRM,Product',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $doc = TypesDocument::create([
            'name' => $request->name,
            'type' => $request->type,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $doc->name . " document type created",
                'message' => $doc->name . " document type created",
            ]),
            'module_id' => $doc->id,
            'module_type' => 'types_document',
            'notification_type' => 'Document Type Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Document type successfully created.'),
            'data' => $doc
        ], 201);
    }

    /**
     * Update
     */
    public function updateTypesDocument(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:types_document,id',
                'name' => 'required|string|max:191',
                'type' => 'required|in:CRM,Product',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $doc = TypesDocument::find($request->id);

        if (!$doc) {
            return response()->json([
                'status' => 'error',
                'message' => __('Record not found.')
            ], 404);
        }

        $originalData = $doc->toArray();

        $doc->update([
            'name' => $request->name,
            'type' => $request->type,
            'created_by' => \Auth::id()
        ]);

        // Track changes
        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }

            if ($doc->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $doc->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $doc->name . " document type updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $doc->id,
                'module_type' => 'types_document',
                'notification_type' => 'Document Type Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Document type successfully updated.'),
            'data' => $doc
        ], 200);
    }

    /**
     * Delete
     */
public function deleteTypesDocument(Request $request)
{
    $validator = \Validator::make(
        $request->all(),
        [
            'id' => 'required|exists:types_document,id'
        ]
    );

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()->first()
        ], 422);
    }

    $doc = TypesDocument::find($request->id);

    if (!$doc) {
        return response()->json([
            'status' => 'error',
            'message' => __('Record not found.')
        ], 404);
    }

    // ✅ Check if any media document exists with this type
    $isUsed = \App\Models\MediaDocument::where('TypesDocumentID', $doc->id)->exists();

    if ($isUsed) {
        return response()->json([
            'status' => 'error',
            'message' => 'This document type is already in use and cannot be deleted.'
        ], 400);
    }

    $name = $doc->name;
    $id = $doc->id;

    // ✅ Safe to delete
    $doc->delete();

    // ✅ Log activity
    addLogActivity([
        'type' => 'warning',
        'note' => json_encode([
            'title' => $name . " document type deleted",
            'message' => $name . " document type deleted"
        ]),
        'module_id' => $id,
        'module_type' => 'types_document',
        'notification_type' => 'Document Type Deleted',
    ]);

    return response()->json([
        'status' => 'success',
        'message' => __('Document type successfully deleted.')
    ], 200);
}
}
