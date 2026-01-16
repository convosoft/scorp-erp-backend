<?php

namespace App\Http\Controllers;

use App\Models\ToolkitPaymentType;
use Illuminate\Http\Request;

class ToolkitPaymentTypeController extends Controller
{
    public function getToolkitPaymentTypePluck(Request $request)
    {
        $ToolkitPaymentTypes = ToolkitPaymentType::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $ToolkitPaymentTypes
        ], 200);
    }

    public function getToolkitPaymentTypes()
    {
        $ToolkitPaymentTypes = ToolkitPaymentType::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $ToolkitPaymentTypes
        ], 200);
    }

    public function addToolkitPaymentType(Request $request)
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

        $ToolkitPaymentType = ToolkitPaymentType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $ToolkitPaymentType->name . " toolkit payment type created",
                'message' => $ToolkitPaymentType->name . " toolkit payment type created",
            ]),
            'module_id' => $ToolkitPaymentType->id,
            'module_type' => 'toolkit_payment_type',
            'notification_type' => 'Toolkit payment type Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit payment type successfully created.'),
            'data' => $ToolkitPaymentType
        ], 201);
    }

    public function updateToolkitPaymentType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:toolkit_payment_types,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $ToolkitPaymentType = ToolkitPaymentType::where('id', $request->id)->first();

        if (!$ToolkitPaymentType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit payment type not found.')
            ], 404);
        }

        $originalData = $ToolkitPaymentType->toArray();

        $ToolkitPaymentType->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($ToolkitPaymentType->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $ToolkitPaymentType->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $ToolkitPaymentType->name . " toolkit payment type updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $ToolkitPaymentType->id,
                'module_type' => 'toolkit_payment_type',
                'notification_type' => 'Toolkit payment type Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit payment type successfully updated.'),
            'data' => $ToolkitPaymentType
        ], 200);
    }

    public function deleteToolkitPaymentType(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:toolkit_payment_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $ToolkitPaymentType = ToolkitPaymentType::where('id', $request->id)->first();

        if (!$ToolkitPaymentType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit payment_type not found.')
            ], 404);
        }

        $ToolkitPaymentTypeName = $ToolkitPaymentType->name;
        $ToolkitPaymentTypeId = $ToolkitPaymentType->id;

        $ToolkitPaymentType->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $ToolkitPaymentTypeName . " toolkit payment type deleted",
                'message' => $ToolkitPaymentTypeName . " toolkit payment type deleted"
            ]),
            'module_id' => $ToolkitPaymentTypeId,
            'module_type' => 'toolkit_payment_type',
            'notification_type' => 'Toolkit payment_type Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit payment_type successfully deleted.')
        ], 200);
    }
}
