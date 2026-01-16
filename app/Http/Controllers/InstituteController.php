<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InstituteController extends Controller
{
    public function getInstitutes(Request $request)
    {
        // Permission check
        // if (!Auth::user()->can('manage institute')) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission denied.')
        //     ], 403);
        // }

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'perPage'    => 'nullable|integer|min:1',
            'page'       => 'nullable|integer|min:1',
            'search'     => 'nullable|string',
            'countryId' => 'nullable|integer|exists:countries,id',
            'city'       => 'nullable|string',
            'name' => 'nullable|string',
            'sector' => 'nullable|string',
            'created_at' => 'nullable|date',
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

        // Base query with necessary relationships
        $query = Institute::with(['country']);



        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('institutes.name', 'like', "%$search%")
                    ->orWhere('institutes.city', 'like', "%$search%")
                    ->orWhere('institutes.address', 'like', "%$search%");
            });
        }

        // Apply additional filters
        if ($request->filled('countryId')) {
            $query->where('institutes.country_id', $request->countryId);
        }
        if ($request->filled('city')) {
            $query->where('institutes.city', 'like', "%{$request->city}%");
        }
        if ($request->filled('name')) {
            $query->where('institutes.name', 'like', "%{$request->name}%");
        }
        if ($request->filled('sector')) {
            $query->where('institutes.sector', 'like', "%{$request->sector}%");
        }
        if ($request->filled('created_at')) {
            $query->whereDate('institutes.created_at', substr($request->created_at, 0, 10));
        }

        // Apply sorting and pagination
        $institutes = $query->orderBy('institutes.created_at', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $institutes->items(),
            'current_page' => $institutes->currentPage(),
            'last_page' => $institutes->lastPage(),
            'total_records' => $institutes->total(),
            'per_page' => $institutes->perPage(),
        ], 200);
    }


    public function addInstitute(Request $request)
    {
        // if (!Auth::user()->can('create institute')) {
        //     return response()->json(['error' => __('Permission denied.')], 403);
        // }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'country_id' => 'required|exists:countries,id',
            'other_details' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $institute = new Institute();
        $institute->fill($request->all());
        $institute->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Institute Created',
                'message' => 'New institute record created successfully'
            ]),
            'module_id' => $institute->id,
            'module_type' => 'institute',
            'notification_type' => 'Institute Created'
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Institute successfully created.',
        ], 200);
    }

    public function show($id)
    {
        $institute = Institute::with('country')->where('id', $id)->first();

        if (!$institute) {
            return response()->json([
                'status' => 'error',
                'message' => 'Institute not found.',
            ], 404);
        }

        return response()->json(['institute' => $institute], 200);
    }

    public function updateInstitute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:institutes,id',
            'name' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'country_id' => 'required|exists:countries,id',
            'other_details' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        $institute = Institute::where('id', $request->id)->first();

        if (!$institute) {
            return response()->json(['error' => __('Institute not found.')], 404);
        }

        $institute->fill($request->all());
        $institute->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Institute Updated',
                'message' => 'Institute record updated successfully'
            ]),
            'module_id' => $institute->id,
            'module_type' => 'institute',
            'notification_type' => 'Institute Updated'
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Institute successfully updated.',
        ], 200);
    }

    public function deleteInstitute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:institutes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $institute = Institute::where('id', $request->id)->first();

        if (!$institute) {
            return response()->json(['error' => __('Institute not found.')], 404);
        }

        $institute->delete();

        // Log Activity
        addLogActivity([
            'type' => 'danger',
            'note' => json_encode([
                'title' => 'Institute Deleted',
                'message' => 'An Institute record has been deleted.'
            ]),
            'module_id' => $request->id,
            'module_type' => 'Institute',
            'notification_type' => 'Institute Deleted'
        ]);

        return response()->json(['success' => __('Institute successfully deleted.')], 200);
    }

    public function pluckInstitutes()
    {

        $Institute =    Institute::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
        return response()->json(['status' => 'success', 'data' => $Institute], 200);
    }

    public function instituteDetail(Request $request)
    {
          $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:institutes,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $Institute = Institute::findOrFail($request->id);
        $responseData = [
            'status' => 'success',
            'data' => $Institute
        ];
    return response()->json($responseData);
}

}
