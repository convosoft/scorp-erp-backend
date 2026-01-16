<?php

namespace App\Http\Controllers;

use App\Models\ToolkitChannel;
use Illuminate\Http\Request;

class ToolkitChannelController extends Controller
{
    public function getToolkitChannelPluck(Request $request)
    {
        $toolkitChannels = ToolkitChannel::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $toolkitChannels
        ], 200);
    }

    public function getToolkitChannels()
    {
        $toolkitChannels = ToolkitChannel::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $toolkitChannels
        ], 200);
    }

    public function addToolkitChannel(Request $request)
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
            ], 422);
        }

        $toolkitChannel = ToolkitChannel::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $toolkitChannel->name . " toolkit channel created",
                'message' => $toolkitChannel->name . " toolkit channel created",
            ]),
            'module_id' => $toolkitChannel->id,
            'module_type' => 'toolkit_channel',
            'notification_type' => 'Toolkit Channel Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit channel successfully created.'),
            'data' => $toolkitChannel
        ], 201);
    }

    public function updateToolkitChannel(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:toolkit_channels,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $toolkitChannel = ToolkitChannel::where('id', $request->id)->first();

        if (!$toolkitChannel) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit channel not found.')
            ], 404);
        }

        $originalData = $toolkitChannel->toArray();

        $toolkitChannel->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($toolkitChannel->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $toolkitChannel->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $toolkitChannel->name . " toolkit channel updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $toolkitChannel->id,
                'module_type' => 'toolkit_channel',
                'notification_type' => 'Toolkit Channel Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit channel successfully updated.'),
            'data' => $toolkitChannel
        ], 200);
    }

    public function deleteToolkitChannel(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:toolkit_channels,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $toolkitChannel = ToolkitChannel::where('id', $request->id)->first();

        if (!$toolkitChannel) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit channel not found.')
            ], 404);
        }

        $toolkitChannelName = $toolkitChannel->name;
        $toolkitChannelId = $toolkitChannel->id;

        $toolkitChannel->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $toolkitChannelName . " toolkit channel deleted",
                'message' => $toolkitChannelName . " toolkit channel deleted"
            ]),
            'module_id' => $toolkitChannelId,
            'module_type' => 'toolkit_channel',
            'notification_type' => 'Toolkit Channel Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit channel successfully deleted.')
        ], 200);
    }
}
