<?php

namespace App\Http\Controllers;

use Auth;

use Illuminate\Http\Request;
use App\Models\UniversityMeta;
use App\Models\University;
use Illuminate\Support\Facades\Validator;

class UniversityMetaController extends Controller
{
    public function __storeOrUpdateMetas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $universityId = $request->university_id;
        $userId = Auth::id();
        $metaData = $request->except('university_id');
        $changes = [];

        foreach ($metaData as $key => $newValue) {
            $existingMeta = UniversityMeta::where([
                'university_id' => $universityId,
                'meta_key' => $key
            ])->first();

            if ($existingMeta) {
                // Meta exists - check if value changed
                if ($existingMeta->meta_value != $newValue) {
                    $changes[$key] = [
                        'old' => $existingMeta->meta_value,
                        'new' => $newValue
                    ];
                }
            } else {
                // New meta field being added
                $changes[$key] = [
                    'old' => null,
                    'new' => $newValue
                ];
            }

            // Update or create the meta record
            UniversityMeta::updateOrCreate(
                [
                    'university_id' => $universityId,
                    'meta_key' => $key,
                ],
                [
                    'meta_value' => $newValue,
                    'created_by' => $userId,
                ]
            );
        }

        // Log changes if any
        if (!empty($changes)) {
            $logDetails = [
                'title' => 'University Metadata Updated',
                'message' => 'Metadata fields were modified',
                'changes' => $changes
            ];

            addLogActivity([
                'type' => 'info',
                'note' => json_encode($logDetails),
                'module_id' => $universityId,
                'module_type' => 'university',
                'created_by' => $userId,
                'notification_type' => 'University Metadata Updated'
            ]);
        }

        $metadata = UniversityMeta::where('university_id', $request->university_id)
            ->get();

        $metas = new \stdClass(); // Create empty object

        foreach ($metadata as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;

            // Handle JSON values if stored as JSON strings
            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return response()->json([
            'status' => true,
            'message' => 'University  processed successfully',
            'data' => $metas // Returns as object
        ]);
    }

    public function storeOrUpdateMetas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
             'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $universityId = $request->university_id;
        $type = $request->type;
        $user = Auth::user();
        $metaData = $request->except('university_id');
        $changes = [];

        foreach ($metaData as $key => $newValue) {
            $existingMeta = UniversityMeta::where([
                'university_id' => $universityId,
                'type' => $type,
                'meta_key' => $key
            ])->first();

            if ($existingMeta) {
                // Check for changes
                if ($existingMeta->meta_value != $newValue) {
                    $changes[$key] = [
                        'old' => $existingMeta->meta_value,
                        'new' => $newValue
                    ];
                }
            } else {
                $changes[$key] = [
                    'old' => null,
                    'new' => $newValue
                ];
            }

            // Store/update the meta
            UniversityMeta::updateOrCreate(
                [
                    'university_id' => $universityId,
                    'type' => $type,
                    'meta_key' => $key,
                ],
                [
                    'meta_value' => $newValue,
                    'type' => $type,
                    'created_by' => $user->id,
                ]
            );
        }

        // Log only if there are changes
        if (!empty($changes)) {
            $universityName = University::where('id', $universityId)->value('name');
            $fieldList = implode(', ', array_map('ucwords', array_keys($changes)));

             $typetext = $request->type == 1 ? 'international' : 'home';

            $logDetails = [
                'title' => "{$typetext} {$universityName} updated",
                'message' => "Fields updated: {$fieldList}",
                'changes' => $changes
            ];

            addLogActivity([
                'type' => 'info',
                'note' => json_encode($logDetails),
                'module_id' => $universityId,
                'module_type' => 'university',
                'created_by' => $user->id,
                'notification_type' => 'University Metadata Updated'
            ]);
        }

        $metadata = UniversityMeta::where('university_id', $universityId)->where('type',$type)->get();

        $metas = new \stdClass();
        foreach ($metadata as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;

            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return response()->json([
            'status' => true,
            'message' => 'University processed successfully',
            'data' => $metas
        ]);
    }


    protected function logMetaChanges($universityId, $changes, $userId)
    {
        $logDetails = [
            'title' => 'University Metadata Updated',
            'message' => 'Metadata fields were modified',
            'changes' => $changes
        ];

        addLogActivity([
            'type' => 'info',
            'note' => json_encode($logDetails),
            'module_id' => $universityId,
            'module_type' => 'university',
            'created_by' => $userId,
            'notification_type' => 'University Metadata Updated'
        ]);
    }

    public function getUniversityMeta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $metadata = UniversityMeta::where('university_id', $request->university_id)
            ->get();

        $metas = new \stdClass(); // Create empty object

        foreach ($metadata as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;

            // Handle JSON values if stored as JSON strings
            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return response()->json([
            'status' => true,
            'message' => 'University meta list retrieved successfully.',
            'data' => $metas // Returns as object
        ]);
    }
}
