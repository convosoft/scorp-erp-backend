<?php

namespace App\Http\Controllers;

use App\Models\UniversityRank;
use Illuminate\Http\Request;

class UniversityRankController extends Controller
{
    public function pluckUniversityRanks()
    {
        $universityRanks = UniversityRank::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        return response()->json(['status' => 'success', 'data' => $universityRanks], 200);
    }

    public function getUniversityRanks()
    {
        if (!\Auth::user()->can('manage university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $universityRanks = UniversityRank::get();

        return response()->json([
            'status' => 'success',
            'data' => $universityRanks
        ], 200);
    }

    public function addUniversityRank(Request $request)
    {
        if (!\Auth::user()->can('create university')) {
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

        $universityRank = UniversityRank::create([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('University rank successfully created.'),
            'data' => $universityRank
        ], 201);
    }

    public function updateUniversityRank(Request $request)
    {
        if (!\Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:university_ranks,id',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $universityRank = UniversityRank::where('id', $request->id)->first();

        if (!$universityRank) {
            return response()->json([
                'status' => 'error',
                'message' => __('University rank not found or unauthorized.')
            ], 404);
        }

        $universityRank->update([
            'name' => $request->name,
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('University rank successfully updated.'),
            'data' => $universityRank
        ], 200);
    }

    public function deleteUniversityRank(Request $request)
    {
        if (!\Auth::user()->can('delete university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:university_ranks,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $universityRank = UniversityRank::where('id', $request->id)->first();

        if (!$universityRank) {
            return response()->json([
                'status' => 'error',
                'message' => __('University rank not found or unauthorized.')
            ], 404);
        }

        $universityRank->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('University rank successfully deleted.')
        ], 200);
    }
}
