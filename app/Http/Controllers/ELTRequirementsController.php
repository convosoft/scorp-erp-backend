<?php

namespace App\Http\Controllers;

use App\Models\ELTRequirement;
use Illuminate\Http\Request;

class ELTRequirementsController extends Controller
{
    public function getELTRequirementsPluck(Request $request)
    {
        $eltRequirements = ELTRequirement::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $eltRequirements
        ], 200);
    }

    public function getELTRequirements()
    {
        $eltRequirements = ELTRequirement::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $eltRequirements
        ], 200);
    }

    public function addELTRequirement(Request $request)
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

        $eltRequirement = ELTRequirement::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $eltRequirement->name . " ELT requirement created",
                'message' => $eltRequirement->name . " ELT requirement created",
            ]),
            'module_id' => $eltRequirement->id,
            'module_type' => 'elt_requirement',
            'notification_type' => 'ELT Requirement Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('ELT requirement successfully created.'),
            'data' => $eltRequirement
        ], 201);
    }

    public function updateELTRequirement(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:elt_requirements,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $eltRequirement = ELTRequirement::find($request->id);

        if (!$eltRequirement) {
            return response()->json([
                'status' => 'error',
                'message' => __('ELT requirement not found.')
            ], 404);
        }

        $originalData = $eltRequirement->toArray();

        $eltRequirement->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }

            if ($eltRequirement->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $eltRequirement->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $eltRequirement->name . " ELT requirement updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $eltRequirement->id,
                'module_type' => 'elt_requirement',
                'notification_type' => 'ELT Requirement Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('ELT requirement successfully updated.'),
            'data' => $eltRequirement
        ], 200);
    }

    public function deleteELTRequirement(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:elt_requirements,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $eltRequirement = ELTRequirement::find($request->id);

        if (!$eltRequirement) {
            return response()->json([
                'status' => 'error',
                'message' => __('ELT requirement not found.')
            ], 404);
        }

        $eltRequirementName = $eltRequirement->name;
        $eltRequirementId = $eltRequirement->id;

        $eltRequirement->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $eltRequirementName . " ELT requirement deleted",
                'message' => $eltRequirementName . " ELT requirement deleted"
            ]),
            'module_id' => $eltRequirementId,
            'module_type' => 'elt_requirement',
            'notification_type' => 'ELT Requirement Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('ELT requirement successfully deleted.')
        ], 200);
    }
}
