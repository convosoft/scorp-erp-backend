<?php

namespace App\Http\Controllers;


use App\Models\MediaDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaDocumentController extends Controller
{



public function uploadMediaDocument(Request $request)
    {
        if (!\Auth::user()->can('create document')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied',
            ], 403);
        }

        // ✅ Validation
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,mp4,mov,avi,wmv,mkv|max:20480',
            'TypesDocumentID' => 'required|exists:types_document,id',
            'type' => 'required|in:lead,admission,application,product',
            'type_id' => 'required|integer',
            'comments' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // ✅ Auto Mapping
        $admission_id = null;
        $application_id = null;
        $type_id = null;

        switch ($request->type) {
            case 'admission':
                $admission_id = $request->type_id;
                break;

            case 'application':
                $application_id = $request->type_id;
                break;

            default:
                $type_id = $request->type_id;
                break;
        }

        $file = $request->file('file');
        $path = 'media_documents/' . $request->type . '/' . date('Y/m');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $s3Path = Storage::disk('s3')->putFileAs(
            $path,
            $file,
            $filename,
            ['visibility' => 'public'] // ✅ must be array
        );

        $fileUrl = Storage::disk('s3')->url($s3Path);

        $document = MediaDocument::create([
            'TypesDocumentID' => $request->TypesDocumentID,
            'type_id' => $type_id,
            'admission_id' => $admission_id,
            'application_id' => $application_id,
            'type' => $request->type,
            'document_link' => $fileUrl,  // ✅ now it will have full URL
            'comments' => $request->comments,
            'created_by' => \Auth::id(),
        ]);

        // ✅ Logging
        // ✅ Module mapping

            $moduleMap = [
                'lead' => 'lead',
                'admission' => 'deal',
                'application' => 'application',
                'product' => 'toolkit',
            ];

            $moduleType = $moduleMap[$request->type] ?? null;


            // ✅ Activity Log
            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => 'Document uploaded',
                    'message' => 'Document uploaded successfully',
                ]),
                'module_id' => $request->type_id, // always type_id
                'module_type' => $moduleType,
                'notification_type' => 'Document Uploaded',
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document uploaded successfully',
            'data' => $document
        ], 201);
    }


} // class end here
