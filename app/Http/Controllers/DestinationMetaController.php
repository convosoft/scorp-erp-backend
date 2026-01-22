<?php

namespace App\Http\Controllers;

use Auth;

use Illuminate\Http\Request;
use App\Models\DestinationMeta;
use App\Models\Destination;
use Illuminate\Support\Facades\Validator;

class DestinationMetaController extends Controller
{
    public function __storeOrUpdateMetas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'destination_id' => 'required|integer|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $destinationId = $request->destination_id;
        $userId = Auth::id();
        $metaData = $request->except('destination_id');
        $changes = [];

        foreach ($metaData as $key => $newValue) {
            $existingMeta = DestinationMeta::where([
                'destination_id' => $destinationId,
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
            DestinationMeta::updateOrCreate(
                [
                    'destination_id' => $destinationId,
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
                'title' => 'destination Metadata Updated',
                'message' => 'Metadata fields were modified',
                'changes' => $changes
            ];

            addLogActivity([
                'type' => 'info',
                'note' => json_encode($logDetails),
                'module_id' => $destinationId,
                'module_type' => 'destination',
                'created_by' => $userId,
                'notification_type' => 'destination Metadata Updated'
            ]);
        }

        $metadata = DestinationMeta::where('destination_id', $request->destination_id)
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
            'message' => 'destination  processed successfully',
            'data' => $metas // Returns as object
        ]);
    }

    public function storeOrUpdateMetas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'destination_id' => 'required|integer|exists:universities,id',
             'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $destinationId = $request->destination_id;
        $type = $request->type;
        $user = Auth::user();
        $metaData = $request->except(['destination_id', 'type']);

        $changes = [];

        foreach ($metaData as $key => $newValue) {
            $existingMeta = DestinationMeta::where([
                'destination_id' => $destinationId,
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
            DestinationMeta::updateOrCreate(
                [
                    'destination_id' => $destinationId,
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
            $destinationName = Destination::where('id', $destinationId)->value('name');
            $fieldList = implode(', ', array_map('ucwords', array_keys($changes)));

             $typetext = $request->type == 1 ? 'international' : 'home';

            $logDetails = [
                'title' => "{$typetext} {$destinationName} updated",
                'message' => "Fields updated: {$fieldList}",
                'changes' => $changes
            ];

            addLogActivity([
                'type' => 'info',
                'note' => json_encode($logDetails),
                'module_id' => $destinationId,
                'module_type' => 'destination',
                'created_by' => $user->id,
                'notification_type' => 'destination Metadata Updated'
            ]);
        }

        $metadata = DestinationMeta::where('destination_id', $destinationId)->where('type',$type)->get();

        $metas = new \stdClass();
        foreach ($metadata as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;

            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return response()->json([
            'status' => true,
            'message' => 'destination processed successfully',
            'data' => $metas
        ]);
    }


    protected function logMetaChanges($destinationId, $changes, $userId)
    {
        $logDetails = [
            'title' => 'destination Metadata Updated',
            'message' => 'Metadata fields were modified',
            'changes' => $changes
        ];

        addLogActivity([
            'type' => 'info',
            'note' => json_encode($logDetails),
            'module_id' => $destinationId,
            'module_type' => 'destination',
            'created_by' => $userId,
            'notification_type' => 'destination Metadata Updated'
        ]);
    }

    public function getdestinationMeta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'destination_id' => 'required|integer|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $metadata = DestinationMeta::where('destination_id', $request->destination_id)
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
            'message' => 'destination meta list retrieved successfully.',
            'data' => $metas // Returns as object
        ]);
    }
}
