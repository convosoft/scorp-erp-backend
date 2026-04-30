<?php

namespace App\Http\Controllers;

use App\Models\BudgetRange;
use Illuminate\Http\Request;

class BudgetRangesController extends Controller
{
    public function getBudgetRangesPluck()
    {
        $data = BudgetRange::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function getBudgetRanges()
    {
        $data = BudgetRange::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function addBudgetRange(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $item = BudgetRange::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $item->name . " budget range created",
                'message' => $item->name . " budget range created",
            ]),
            'module_id' => $item->id,
            'module_type' => 'budget_range',
            'notification_type' => 'budget range Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('budget range created.'),
            'data' => $item
        ], 201);
    }

    public function updateBudgetRange(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:budget_ranges,id',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $item = BudgetRange::find($request->id);

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not found'
            ], 404);
        }

        $original = $item->toArray();

        $item->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($original as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) continue;

            if ($item->$field != $oldValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $item->$field];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $item->name . " budget range updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $item->id,
                'module_type' => 'budget_range',
                'notification_type' => 'budget range Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Updated'),
            'data' => $item
        ], 200);
    }

    public function deleteBudgetRange(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required|exists:budget_ranges,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $item = BudgetRange::find($request->id);

        $name = $item->name;
        $id = $item->id;

        $item->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $name . " budget range deleted",
                'message' => $name . " budget range deleted"
            ]),
            'module_id' => $id,
            'module_type' => 'budget_range',
            'notification_type' => 'budget range Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Deleted')
        ], 200);
    }
}
