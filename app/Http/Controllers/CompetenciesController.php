<?php

namespace App\Http\Controllers;

use App\Models\Competencies;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class CompetenciesController extends Controller
{
    public function getCompetencies()
    {
        if (!\Auth::user()->can('Manage Competencies')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $competencies = Competencies::orderBy('name')->get();

        foreach ($competencies as $competency) {
            // Decode JSON type field
            $roleIds = json_decode($competency->type, true);

            // Ensure it's an array
            if (!is_array($roleIds)) {
                $roleIds = [];
            }

            // Fetch role names
            $roles = \Spatie\Permission\Models\Role::whereIn('id', $roleIds)
                ->pluck('name')
                ->toArray();

            // Attach role names to the competency object
          //  $competency->role_names = implode(', ', $roles);
        }

        return response()->json([
            'status' => 'success',
            'data' => $competencies
        ], 200);
    }
    public function getCompetenciesByType(Request $request)
    {
        if (!\Auth::user()->can('Manage Competencies')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'type' => 'required|integer'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $competencies = Competencies::whereRaw("JSON_CONTAINS(type, '$request->type')")->get();

        return response()->json([
            'status' => 'success',
            'data' => $competencies
        ], 200);
    }

    public function addCompetency(Request $request)
    {
        if (!\Auth::user()->can('Create Competencies')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string',
                'type' => 'required|array'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $competency = Competencies::create([
            'name' => $request->name,
            'type' => json_encode(array_map('intval', $request->type)),
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Competency successfully created.'),
            'data' => $competency
        ], 201);
    }

    public function updateCompetency(Request $request)
    {
        if (!\Auth::user()->can('Edit Competencies')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:competencies,id',
                'name' => 'required|string',
                'type' => 'required|array'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $competency = Competencies::where('id', $request->id)->first();

        if (!$competency) {
            return response()->json([
                'status' => 'error',
                'message' => __('Competency not found or unauthorized.')
            ], 404);
        }

        $competency->update([
            'name' => $request->name,
            'type' => json_encode(array_map('intval', $request->type)),
            'created_by' => \Auth::id()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Competency successfully updated.'),
            'data' => $competency
        ], 200);
    }

    public function deleteCompetency(Request $request)
    {
        if (!\Auth::user()->can('Delete Competencies')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:competencies,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $competency = Competencies::where('id', $request->id)->first();

        if (!$competency) {
            return response()->json([
                'status' => 'error',
                'message' => __('Competency not found or unauthorized.')
            ], 404);
        }

        $competency->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Competency successfully deleted.')
        ], 200);
    }
}
