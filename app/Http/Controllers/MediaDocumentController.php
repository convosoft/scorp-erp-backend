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
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,mp4|max:256000',
            'TypesDocumentID' => 'required|exists:types_document,id',
            'type' => 'required|in:lead,admission,application,product',
            'type_id' => 'required|integer',
            'app_id' => 'required|integer',
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
        $application_id = $request->app_id;
        $type_id = null;

        switch ($request->type) {
            case 'admission':
                $admission_id = $request->type_id;
                break;

            case 'application':
                $admission_id = $request->type_id;
                break;

            default:
                $type_id = $request->type_id;
                break;
        }

        // ✅ File Upload to S3
        // ✅ File Upload to S3
        $file = $request->file('file');
        $path = 'media_documents/' . $request->type . '/' . date('Y/m');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        try {
            $s3Path = $file->storeAs($path, $filename, [
                'disk' => 's3',
            ]);

            if (!$s3Path) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File upload to S3 failed. Please try again.',
                    'debug' => [
                        'path' => $path,
                        'filename' => $filename,
                        's3_path' => $s3Path,
                        's3_driver' => config('filesystems.disks.s3'),
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'S3 Exception: ' . $e->getMessage(),
                'debug' => [
                    'path' => $path,
                    'filename' => $filename,
                    's3_key' => config('filesystems.disks.s3.key'),
                    's3_bucket' => config('filesystems.disks.s3.bucket'),
                    's3_region' => config('filesystems.disks.s3.region'),
                ]
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


public function getMediaDocument(Request $request)
    {
        // ✅ Validation
        $validator = \Validator::make($request->all(), [
            'type' => 'required|in:lead,admission,application,product',
            'type_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // ✅ Determine which column to filter based on type
        $typeColumn = match ($request->type) {
            'lead', 'product' => 'type_id',
            'admission' => 'admission_id',
            'application' => 'application_id',
            default => null,
        };

        if (!$typeColumn) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid type provided.',
            ], 400);
        }

        // ✅ Fetch documents
        $documents = MediaDocument::where('type', $request->type)
            ->where($typeColumn, $request->type_id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $documents,
        ], 200);
    }

public function deleteMediaDocument(Request $request)
    {
        // ✅ Check permission
        if (!\Auth::user()->can('delete document')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied',
            ], 403);
        }

        // ✅ Validate input
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:media_documents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // ✅ Find document
        $document = MediaDocument::find($request->id);

        if (!$document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document not found',
            ], 404);
        }



        // Delete file from S3 if exists
        if (!empty($document->document_link)) {
            $bucketUrl = rtrim(config('filesystems.disks.s3.url'), '/');
            $s3Key = str_replace($bucketUrl . '/', '', $document->document_link);

            if (!empty($s3Key) && Storage::disk('s3')->exists($s3Key)) {
                Storage::disk('s3')->delete($s3Key);
            }
        }

        // ✅ Save info for logging
        $typeId = $document->type_id ?? $document->admission_id ?? $document->application_id ?? null;
        $moduleMap = [
            'lead' => 'lead',
            'admission' => 'deal',
            'application' => 'application',
            'product' => 'toolkit',
        ];
        $moduleType = $moduleMap[$document->type] ?? null;

        // ✅ Delete record
        $document->delete();

        // ✅ Log activity
        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => 'Document deleted',
                'message' => 'Document deleted successfully',
            ]),
            'module_id' => $typeId,
            'module_type' => $moduleType,
            'notification_type' => 'Document Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully',
        ], 200);
    }



} // class end here
