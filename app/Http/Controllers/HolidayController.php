<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function getHolidayPluck(Request $request)
    {
        $holidays = Holiday::pluck('occasion', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $holidays
        ], 200);
    }

    public function getHolidays()
    {
        $holidays = Holiday::get();

        return response()->json([
            'status' => 'success',
            'data' => $holidays
        ], 200);
    }

    public function addHoliday(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:date',
                'occasion' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $holiday = Holiday::create([
            'date' =>date('Y-m-d', strtotime($request->date)),
            'end_date' => date('Y-m-d', strtotime($request->end_date)),
            'occasion' => $request->occasion,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $holiday->occasion . " holiday created",
                'message' => $holiday->occasion . " holiday created",
            ]),
            'module_id' => $holiday->id,
            'module_type' => 'holiday',
            'notification_type' => 'Holiday Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Holiday successfully created.'),
            'data' => $holiday
        ], 201);
    }

    public function updateHoliday(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:holidays,id',
                'date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:date',
                'occasion' => 'required|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $holiday = Holiday::find($request->id);

        if (!$holiday) {
            return response()->json([
                'status' => 'error',
                'message' => __('Holiday not found.')
            ], 404);
        }

        $originalData = $holiday->toArray();

        $holiday->update([
            'date' =>date('Y-m-d', strtotime($request->date)),
            'end_date' => date('Y-m-d', strtotime($request->end_date)),
            'occasion' => $request->occasion,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($holiday->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $holiday->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $holiday->occasion . " holiday updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $holiday->id,
                'module_type' => 'holiday',
                'notification_type' => 'Holiday Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Holiday successfully updated.'),
            'data' => $holiday
        ], 200);
    }

    public function deleteHoliday(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:holidays,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $holiday = Holiday::find($request->id);

        if (!$holiday) {
            return response()->json([
                'status' => 'error',
                'message' => __('Holiday not found.')
            ], 404);
        }

        $holidayOccasion = $holiday->occasion;
        $holidayId = $holiday->id;

        $holiday->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $holidayOccasion . " holiday deleted",
                'message' => $holidayOccasion . " holiday deleted"
            ]),
            'module_id' => $holidayId,
            'module_type' => 'holiday',
            'notification_type' => 'Holiday Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Holiday successfully deleted.')
        ], 200);
    }
}
