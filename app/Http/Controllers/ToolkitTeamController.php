<?php

namespace App\Http\Controllers;

use App\Models\ToolkitTeam;
use Illuminate\Http\Request;

class ToolkitTeamController extends Controller
{
    public function getToolkitTeamPluck(Request $request)
    {
        $toolkitTeams = ToolkitTeam::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $toolkitTeams
        ], 200);
    }

    public function getToolkitTeams()
    {
        $toolkitTeams = ToolkitTeam::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $toolkitTeams
        ], 200);
    }

    public function addToolkitTeam(Request $request)
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

        $toolkitTeam = ToolkitTeam::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $toolkitTeam->name . " toolkit team created",
                'message' => $toolkitTeam->name . " toolkit team created",
            ]),
            'module_id' => $toolkitTeam->id,
            'module_type' => 'toolkit_team',
            'notification_type' => 'Toolkit Team Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit team successfully created.'),
            'data' => $toolkitTeam
        ], 201);
    }

    public function updateToolkitTeam(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:toolkit_teams,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $toolkitTeam = ToolkitTeam::where('id', $request->id)->first();

        if (!$toolkitTeam) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit team not found.')
            ], 404);
        }

        $originalData = $toolkitTeam->toArray();

        $toolkitTeam->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($toolkitTeam->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $toolkitTeam->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $toolkitTeam->name . " toolkit team updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $toolkitTeam->id,
                'module_type' => 'toolkit_team',
                'notification_type' => 'Toolkit Team Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit team successfully updated.'),
            'data' => $toolkitTeam
        ], 200);
    }

    public function deleteToolkitTeam(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:toolkit_teams,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $toolkitTeam = ToolkitTeam::where('id', $request->id)->first();

        if (!$toolkitTeam) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit team not found.')
            ], 404);
        }

        $toolkitTeamName = $toolkitTeam->name;
        $toolkitTeamId = $toolkitTeam->id;

        $toolkitTeam->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $toolkitTeamName . " toolkit team deleted",
                'message' => $toolkitTeamName . " toolkit team deleted"
            ]),
            'module_id' => $toolkitTeamId,
            'module_type' => 'toolkit_team',
            'notification_type' => 'Toolkit Team Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit team successfully deleted.')
        ], 200);
    }
}
