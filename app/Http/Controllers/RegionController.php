<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Region;
use App\Models\Branch;
use App\Models\SavedFilter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RegionController extends Controller
{
    /**
     * Get a paginated list of regions.
     */
    public function getRegions(Request $request)
    {
        if (!Auth::user()->can('manage region')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        $query = Region::with('manager', 'brand');

        // Permission-based filtering
        $user = Auth::user();
        if ($user->type === 'company') {
            $query->where('brands', $user->id);
        } elseif (!in_array($user->type, ['super admin', 'Admin Team', 'HR'])) {
            $query->whereIn('brands', array_keys(FiltersBrands()));
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where('regions.name', 'like', "%{$search}%")
                ->orWhereHas('brand', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('manager', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
        }

        // Brand filter
        if ($request->filled('brand_id')) {
            $query->where('brands', $request->brand_id);
        }

        // Region filter
        if ($request->filled('region_id')) {
            $query->where('id', $request->region_id);
        }

        // Total records before pagination
        $totalRecords = $query->count();

        // CSV Download
        if ($request->input('download_csv')) {
            $data = $query->get()->toArray();
            $filename = 'Attendance_' . now()->timestamp . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return response()->stream(function () use ($data) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['ID', 'Name', 'Email', 'Brand', 'Phone', 'Location', 'Region Manager']);
                foreach ($data as $row) {
                    fputcsv($f, [
                        $row['id'],
                        $row['name'],
                        $row['email'] ?? '',
                        isset($row['brand']['name']) ? $row['brand']['name'] : '',
                        $row['phone'] ?? '',
                        $row['location'] ?? '',
                        isset($row['manager']['name']) ? $row['manager']['name'] : '',
                    ]);
                }
                fclose($f);
            }, 200, $headers);
        }

        // Pagination
        $regions = $query->orderBy('name', 'ASC')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $regions->items(),
            'current_page' => $regions->currentPage(),
            'last_page' => $regions->lastPage(),
            'total_records' => $totalRecords,
            'per_page' => $regions->perPage(),
        ], 200);
    }

    /**
     * Store a new region.
     */
    public function addRegion(Request $request)
    {
        if (!Auth::user()->can('create region')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'brand_id' => 'required|integer|exists:users,id',
            'region_manager_id' => 'nullable|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = Region::create([
            'name' => $request->name,
            'brands' => $request->brand_id,
            'location' => $request->location,
            'phone' => $request->phone,
            'email' => $request->email,
            'region_manager_id' => $request->region_manager_id
        ]);

        $typeoflog = 'region';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $region->name. ' '.$typeoflog.' created',
                        'message' => $region->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $region->id,
                    'module_type' => 'region',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);


        return response()->json([
            'status' => 'success',
            'message' => __('Region created successfully.'),
            'data' => $region
        ], 201);
    }

    /**
     * Update an existing region.
     */
    public function updateRegion(Request $request)
    {
        if (!Auth::user()->can('edit region')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:regions,id',
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'brand_id' => 'required|integer|exists:users,id',
            'region_manager_id' => 'nullable|integer|exists:users,id',
            'email' => 'required|email|unique:branches,email,' . $request->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = Region::findOrFail($request->id);
         $originalData = $region->toArray();
        $region->update([
            'name' => $request->name,
            'brands' => $request->brand_id,
            'location' => $request->location,
            'phone' => $request->phone,
            'email' => $request->email,
            'region_manager_id' => $request->region_manager_id
        ]);


        // ============ edit ============


        


           // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($region->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $region->$field
                ];
                $updatedFields[] = $field;
            }
        } 
         $typeoflog = 'region';
           
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $region->name .  ' '.$typeoflog.'  updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $region->id,
                    'module_type' => 'region',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }

       


        return response()->json([
            'status' => 'success',
            'message' => __('Region updated successfully.'),
            'data' => $region
        ], 200);
    }

    /**
     * Delete a region.
     */
    public function deleteRegion(Request $request)
    {
        if (!Auth::user()->can('delete region')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:regions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = Region::findOrFail($request->id);

         
        // Find user
                    $branches = Branch::where('region_id', $region->id)->count();

                    if ($branches) {
                        return response()->json([
                            'status' => 'error',
                            'message' => __("there are ($branches) branches found. Region cannot be deleted as it is associated with branches.")
                        ], 200);
                    }
        
        
        //    =================== delete ===========
 
            $typeoflog = 'region';
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $region->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $region->name .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $region->id,
                    'module_type' => 'region',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);
            

        $region->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Region deleted successfully.')
        ], 200);
    }

    public function deleteBulkRegions(Request $request)
    {
        if (\Auth::user()->can('delete region') || \Auth::user()->type == 'super admin') {

            if ($request->ids != null) {
                // Delete regions based on the IDs
                Region::whereIn('id', explode(',', $request->ids))->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Regions deleted successfully'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'At least select 1 region.'
                ], 422);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }
    }


    public function regionDetail(Request $request)
{
    // Validate request
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer|exists:regions,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Fetch region
        $region = Region::with(['manager', 'brand'])->findOrFail($request->id);


        return response()->json([
            'status' => 'success',
            'data' => $region
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => __('An error occurred while fetching user details.'),
            'error' => $e->getMessage()
        ], 500);
    }
}
}
