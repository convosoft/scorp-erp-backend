<?php

namespace App\Http\Controllers;

use App\Models\StudentAdvice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class StudentAdviceController extends Controller
{
    /**
     * Upload a student advice document.
     * Limit: 3 files per admission_id.
     */
    public function uploadAdvice(Request $request)
    {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,docx,doc|max:25600', // 25MB limit
            'admission_id' => 'required|exists:deals,id',
            'comments' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // ✅ Check Limit (Max 3 files per admission_id)
        $existingCount = StudentAdvice::where('admission_id', $request->admission_id)->count();
        if ($existingCount >= 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload limit reached. You can only save up to 3 advice files against an admission ID.'
            ], 400);
        }

        // ✅ File Upload to S3
        $file = $request->file('file');
        $path = 'student_advice/' . $request->admission_id;
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        try {
            $s3Path = $file->storeAs($path, $filename, [
                'disk' => 's3',
            ]);

            if (!$s3Path) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File upload to S3 failed.'
                ], 500);
            }

            $fileUrl = Storage::disk('s3')->url($s3Path);

            // ✅ Save to DB
            $advice = StudentAdvice::create([
                'admission_id' => $request->admission_id,
                'document_link' => $fileUrl,
                'comments' => $request->comments,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Advice file uploaded successfully.',
                'data' => $advice
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAdvice(Request $request)
    {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'admission_id' => 'required|exists:deals,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $advice = StudentAdvice::where('admission_id', $request->admission_id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $advice
        ], 200);
    }

    /**
     * Delete an advice file.
     */
    public function deleteAdvice($id)
    {
        $advice = StudentAdvice::find($id);

        if (!$advice) {
            return response()->json([
                'status' => 'error',
                'message' => 'Advice record not found.'
            ], 404);
        }

        // ✅ Delete file from S3
        try {
            $bucketUrl = rtrim(config('filesystems.disks.s3.url'), '/');
            $s3Key = str_replace($bucketUrl . '/', '', $advice->document_link);

            if (!empty($s3Key) && Storage::disk('s3')->exists($s3Key)) {
                Storage::disk('s3')->delete($s3Key);
            }
        } catch (\Exception $e) {
            \Log::warning("Could not delete S3 file for StudentAdvice ID: $id. Error: " . $e->getMessage());
        }

        // ✅ Delete DB Record
        $advice->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Advice file deleted successfully.'
        ], 200);
    }
}
