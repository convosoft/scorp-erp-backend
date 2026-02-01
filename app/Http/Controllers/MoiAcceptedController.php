<?php

namespace App\Http\Controllers;

use App\Models\MoiAccepted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MoiAcceptedController extends Controller
{
    /**
     * Add institutes to university
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMOIInstitutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
            'institute_id' => 'required|array|min:1',
            'type' => 'required|integer|in:1,2',
            'institute_id.*' => 'integer|exists:institutes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $addedRecords = MoiAccepted::addInstitutesToUniversity(
                $request->university_id,
                $request->institute_id,
                auth()->id(),
                $request->type

            );

            if (empty($addedRecords)) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'All specified institutes already exist for this university'
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => count($addedRecords) . ' institute(s) added to university',
                'data' => $addedRecords
            ]);

        } catch (\Exception $e) {


            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add institutes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMOIInstitutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
            'institute_id' => 'nullable|array',
            'type' => 'required|integer|in:1,2',
            'institute_id.*' => 'integer|exists:institutes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instituteIds = $request->institute_id ?? []; 
            $results = MoiAccepted::updateInstitutes(
                $request->university_id,
                $instituteIds,
                auth()->id()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Institute associations updated successfully',
                'data' => [
                    'added' => count($results['added']),
                    'removed' => count($results['removed']),
                    'unchanged' => count($results['unchanged']),
                    'details' => $results
                ]
            ]);

        } catch (\Exception $e) {
            addLogActivity([
                'type' => 'error',
                'note' => json_encode([
                    'title' => 'Update Failed',
                    'message' => $e->getMessage(),
                    'university_id' => $request->university_id,
                    'institute_ids' => $request->institute_id
                ]),
                'module_id' => $request->university_id,
                'module_type' => 'university',
                'created_by' => auth()->id(),
                'notification_type' => 'Update Error'
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update institute associations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMIOList(Request $request)
    {
        // Validate university_id is required and exists
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('Validation Error.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Build the query with relationships
        // $query = MoiAccepted::with(['created_by', 'institute', 'university'])
        //     ->where('university_id', $request->university_id);

        // Get data
        // $mioList = $query->get();

        // return response()->json([
        //     'status' => true,
        //     'message' => 'MIO list fetched successfully.',
        //     'data' => $mioList
        // ]);
        $query = MoiAccepted::with(['institute.country'])
            ->where('university_id', $request->university_id)
            ->where('type', $request->type)
            ->get()
            ->pluck('institute')
            ->unique('id')
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'MIO list fetched successfully.',
            'data' => $query
        ]);

        
    }

}
