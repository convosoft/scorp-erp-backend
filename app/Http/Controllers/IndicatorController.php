<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class IndicatorController extends Controller
{
    public function getIndicators(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage indicator')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Build the query for indicators
        $indicator_query = Indicator::with(['created_by', 'brand', 'branch', 'region','updated_by','designations','departments']);

        // Apply search filter if provided
        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->input('search');
            $indicator_query->where(function ($query) use ($search) {
                $query->whereHas('created_by', function($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                })
                ->orWhereHas('brand', function($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                })
                ->orWhereHas('branch', function($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                })
                ->orWhereHas('region', function($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                })
                ->orWhereHas('updated_by', function($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            });
        }

        // Apply additional filters using IndicatorFilters()
        $filters = $this->IndicatorFilters();
        foreach ($filters as $column => $value) {
            if ($column == 'created_at') {
                $indicator_query->whereDate('indicators.created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
            } elseif ($column == 'brand') {
                if(!is_null($value)){

                $indicator_query->where('indicators.brand_id', $value);
                }
            } elseif ($column == 'region_id') {
                $indicator_query->where('indicators.region_id', $value);
            } elseif ($column == 'branch_id') {
                $indicator_query->where('indicators.branch', $value);
            }
        }

        // Apply sorting and pagination
        $indicators = $indicator_query->orderBy('indicators.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $totalRecords = $indicators->total();

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $indicators->items(),
            'current_page' => $indicators->currentPage(),
            'last_page' => $indicators->lastPage(),
            'total_records' => $totalRecords,
            'per_page' => $indicators->perPage(),
        ], 200);
    }
    private function IndicatorFilters()
    {
        return request()->only(['created_at', 'brand', 'region_id', 'branch_id']);
    }

    public function indicatorDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:indicators,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $indicator = Indicator::with(['created_by', 'brand', 'branch', 'region','updated_by','designations','departments'])->find($request->id);
        if (!$indicator) {
            return response()->json(['status' => 'error', 'message' => 'Indicator not found.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $indicator]);
    }

    public function addIndicator(Request $request)
    {
        if (!Auth::user()->can('create indicator')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate request input
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'lead_branch' => 'required|integer|min:1',
            'department' => 'required',
            'designation' => 'required',
            'rating' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Create a new Indicator
        $indicator = new Indicator();
        $indicator->branch = $request->lead_branch;
        $indicator->brand_id = $request->brand_id;
        $indicator->region_id = $request->region_id;
        $indicator->department = $request->department;
        $indicator->designation = $request->designation;
        $indicator->rating = json_encode($request->rating, true);
        $indicator->created_user = Auth::id();
        $indicator->created_by = Auth::id();
        $indicator->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Indicator successfully created.',
            'id' => $indicator
        ], 201);
    }


    public function updateIndicator(Request $request)
    {
        if (!Auth::user()->can('edit indicator')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate request input including ID
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:indicators,id',
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'lead_branch' => 'required|integer|min:1',
            'department' => 'required',
            'designation' => 'required',
            'rating' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Find the indicator
        $indicator = Indicator::find($request->id);
        if (!$indicator) {
            return response()->json([
                'status' => 'error',
                'message' => 'Indicator not found.'
            ], 404);
        }

        // Update the indicator
        $indicator->branch = $request->lead_branch;
        $indicator->brand_id = $request->brand_id;
        $indicator->region_id = $request->region_id;
        $indicator->department = $request->department;
        $indicator->designation = $request->designation;
        $indicator->rating = json_encode($request->rating, true);
        $indicator->updated_by = Auth::id();
        $indicator->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Indicator successfully updated.',
            'id' => $indicator->id
        ], 200);
    }


    public function deleteIndicator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:indicators,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        if (!Auth::user()->can('delete indicator')) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied.')], 403);
        }

        $indicator = Indicator::find($request->id);
        if (!$indicator) {
            return response()->json(['status' => 'error', 'message' => 'Indicator not found.'], 404);
        }

        $indicator->delete();
        return response()->json(['status' => 'success', 'message' => 'Indicator successfully deleted.']);
    }
}
