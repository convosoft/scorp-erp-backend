<?php

namespace App\Http\Controllers;

use App\Models\AwardType;
use Illuminate\Http\Request;

class AwardTypeController extends Controller
{
    public function getAwardTypes()
    {
        if (!\Auth::user()->can('manage award type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $awardTypes = AwardType::get();

        return response()->json([
            'status' => 'success',
            'data' => $awardTypes
        ], 200);
    }

    public function addAwardType(Request $request)
    {
        if (!\Auth::user()->can('create award type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['name' => 'required|string']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $awardType = AwardType::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Award type successfully created.'),
            'data' => $awardType
        ], 201);
    }

    public function updateAwardType(Request $request)
    {
        if (!\Auth::user()->can('edit award type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:award_types,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $awardType = AwardType::where('id', $request->id)->first();

        if (!$awardType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Award type not found or unauthorized.')
            ], 404);
        }

        $awardType->update(['name' => $request->name, 'created_by' => \Auth::id()]);

        return response()->json([
            'status' => 'success',
            'message' => __('Award type successfully updated.'),
            'data' => $awardType
        ], 200);
    }

    public function deleteAwardType(Request $request)
    {
        if (!\Auth::user()->can('delete award type')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:award_types,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $awardType = AwardType::where('id', $request->id)->first();

        if (!$awardType) {
            return response()->json([
                'status' => 'error',
                'message' => __('Award type not found or unauthorized.')
            ], 404);
        }

        $awardType->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Award type successfully deleted.')
        ], 200);
    }
}
