<?php

namespace App\Http\Controllers;

use App\Models\ToolkitApplicableFee;
use Illuminate\Http\Request;

class ToolkitApplicableFeeController extends Controller
{
    public function getToolkitApplicableFeePluck(Request $request)
    {
        $fees = ToolkitApplicableFee::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $fees
        ], 200);
    }

    public function getToolkitApplicableFees()
    {
        $fees = ToolkitApplicableFee::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $fees
        ], 200);
    }

    public function addToolkitApplicableFee(Request $request)
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

        $fee = ToolkitApplicableFee::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $fee->name . " toolkit applicable fee created",
                'message' => $fee->name . " toolkit applicable fee created",
            ]),
            'module_id' => $fee->id,
            'module_type' => 'toolkit_applicable_fee',
            'notification_type' => 'Toolkit Applicable Fee Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit applicable fee successfully created.'),
            'data' => $fee
        ], 201);
    }

    public function updateToolkitApplicableFee(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:toolkit_applicable_fees,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $fee = ToolkitApplicableFee::where('id', $request->id)->first();

        if (!$fee) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit applicable fee not found.')
            ], 404);
        }

        $originalData = $fee->toArray();

        $fee->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($fee->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $fee->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $fee->name . " toolkit applicable fee updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $fee->id,
                'module_type' => 'toolkit_applicable_fee',
                'notification_type' => 'Toolkit Applicable Fee Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit applicable fee successfully updated.'),
            'data' => $fee
        ], 200);
    }

    public function deleteToolkitApplicableFee(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:toolkit_applicable_fees,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $fee = ToolkitApplicableFee::where('id', $request->id)->first();

        if (!$fee) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit applicable fee not found.')
            ], 404);
        }

        $feeName = $fee->name;
        $feeId = $fee->id;

        $fee->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $feeName . " toolkit applicable fee deleted",
                'message' => $feeName . " toolkit applicable fee deleted"
            ]),
            'module_id' => $feeId,
            'module_type' => 'toolkit_applicable_fee',
            'notification_type' => 'Toolkit Applicable Fee Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit applicable fee successfully deleted.')
        ], 200);
    }
}
