<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function getDocumentTypePluck(Request $request)
    {
        $documentTypes = DocumentType::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $documentTypes
        ], 200);
    }

    public function getDocumentTypes()
    {
        $documentTypes = DocumentType::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $documentTypes
        ], 200);
    }

    public function addDocumentType(Request $request)
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
            ], 200);
        }

        $documentType = DocumentType::create([
            'name' => $request->name,
            'is_required' => 1,
            'created_by' => \Auth::id()
        ]);

        $typeoflog = 'document type';

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $documentType->name . " $typeoflog created",
                'message' => $documentType->name . " $typeoflog created",
            ]),
            'module_id' => $documentType->id,
            'module_type' => $typeoflog,
            'notification_type' => ucfirst($typeoflog) . ' Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Document type successfully created.'),
            'data' => $documentType
        ], 201);
    }

    public function updateDocumentType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:document_types,id',
                'name' => 'required|string',
                'is_required' => 'required|boolean',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $documentType = DocumentType::where('id', $request->id)->first();

        if (!$documentType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Document type not found.')
            ], 404);
        }

        $originalData = $documentType->toArray();

        $documentType->update([
            'name' => $request->name,
            'is_required' => 1,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($documentType->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $documentType->$field
                ];
                $updatedFields[] = $field;
            }
        }

        $typeoflog = 'document type';

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $documentType->name . " $typeoflog updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $documentType->id,
                'module_type' => $typeoflog,
                'notification_type' => ucfirst($typeoflog) . ' Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Document type successfully updated.'),
            'data' => $documentType
        ], 200);
    }

    public function deleteDocumentType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:document_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $documentType = DocumentType::where('id', $request->id)->first();

        if (!$documentType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Document type not found.')
            ], 404);
        }

        $typeoflog = 'document type';
        $docName = $documentType->name;
        $docId = $documentType->id;

        $documentType->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $docName . " $typeoflog deleted",
                'message' => $docName . " $typeoflog deleted"
            ]),
            'module_id' => $docId,
            'module_type' => $typeoflog,
            'notification_type' => ucfirst($typeoflog) . ' Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Document type successfully deleted.')
        ], 200);
    }
}
