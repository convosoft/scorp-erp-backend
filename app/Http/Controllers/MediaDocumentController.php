<?php

namespace App\Http\Controllers;


use App\Models\MediaDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaDocumentController extends Controller
{



public function uploadMediaDocument(Request $request)
{


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

    // ✅ File Upload to S3
    $file = $request->file('file');
    $path = 'media_documents/' . $request->type . '/' . date('Y/m');
    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

    // ✅ Use storeAs instead of putFileAs (supports visibility correctly)
    $s3Path = $file->storeAs($path, $filename, [
        'disk' => 's3',
        'visibility' => 'public',
    ]);

    // ✅ Guard against upload failure
    if (!$s3Path) {
        return response()->json([
            'status' => 'error',
            'message' => 'File upload to S3 failed. Please try again.',
        ], 500);
    }

    $fileUrl = Storage::disk('s3')->url($s3Path);

    // ✅ Save to DB
    $document = MediaDocument::create([
        'TypesDocumentID' => $request->TypesDocumentID,
        'type_id' => $type_id,
        'admission_id' => $admission_id,
        'application_id' => $application_id,
        'type' => $request->type,
        'document_link' => $fileUrl,
        'comments' => $request->comments,
        'created_by' => \Auth::id(),
    ]);

    // ✅ Module Mapping
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
        'module_id' => $request->type_id,
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
