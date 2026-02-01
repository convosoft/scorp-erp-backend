<?php

namespace App\Http\Controllers;

use App\Models\ToolkitInstallmentPayOut;
use Illuminate\Http\Request;

class ToolkitInstallmentPayOutController extends Controller
{
    public function getToolkitInstallmentPayOutPluck(Request $request)
    {
        $payOuts = ToolkitInstallmentPayOut::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $payOuts
        ], 200);
    }

    public function getToolkitInstallmentPayOuts()
    {
        $payOuts = ToolkitInstallmentPayOut::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $payOuts
        ], 200);
    }

    public function addToolkitInstallmentPayOut(Request $request)
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

        $payOut = ToolkitInstallmentPayOut::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $payOut->name . " toolkit installment pay out created",
                'message' => $payOut->name . " toolkit installment pay out created",
            ]),
            'module_id' => $payOut->id,
            'module_type' => 'toolkit_installment_pay_out',
            'notification_type' => 'Toolkit Installment Pay Out Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit installment pay out successfully created.'),
            'data' => $payOut
        ], 201);
    }

    public function updateToolkitInstallmentPayOut(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:toolkit_installment_pay_outs,id',
                'name' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $payOut = ToolkitInstallmentPayOut::where('id', $request->id)->first();

        if (!$payOut) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit installment pay out not found.')
            ], 404);
        }

        $originalData = $payOut->toArray();

        $payOut->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($payOut->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $payOut->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $payOut->name . " toolkit installment pay out updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $payOut->id,
                'module_type' => 'toolkit_installment_pay_out',
                'notification_type' => 'Toolkit Installment Pay Out Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit installment pay out successfully updated.'),
            'data' => $payOut
        ], 200);
    }

    public function deleteToolkitInstallmentPayOut(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:toolkit_installment_pay_outs,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $payOut = ToolkitInstallmentPayOut::where('id', $request->id)->first();

        if (!$payOut) {
            return response()->json([
                'status' => 'error',
                'message' => __('Toolkit installment pay out not found.')
            ], 404);
        }

        $payOutName = $payOut->name;
        $payOutId = $payOut->id;

        $payOut->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $payOutName . " toolkit installment pay out deleted",
                'message' => $payOutName . " toolkit installment pay out deleted"
            ]),
            'module_id' => $payOutId,
            'module_type' => 'toolkit_installment_pay_out',
            'notification_type' => 'Toolkit Installment Pay Out Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Toolkit installment pay out successfully deleted.')
        ], 200);
    }
}
