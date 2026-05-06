<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Department;
use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BranchController extends Controller
{

    public function branchDetail(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'branch_id' =>  'required|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $Branch = Branch::select(['branches.*'])
        ->with(['region', 'brand', 'manager'])->where('branches.id',$request->branch_id)->first();

        // Return Complete Data as JSON
        return response()->json([
            'status' => 'success',
            'data' => $Branch,
            'encrpted_brand_id' => encryptData($Branch->brands), 
            'encrpted_region_id' => encryptData($Branch->region_id), 
            'encrpted_branch_id' => encryptData($Branch->id), 
        ], 200);
    }

    public function getBranches(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'brand_id' => 'nullable|integer',
            'region_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = \Auth::user();

        if (!$user->can('manage branch')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        $branchQuery = Branch::select(['branches.*'])
            ->with(['region', 'brand', 'manager']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $branchQuery->where(function ($query) use ($search) {
                $query->where('branches.name', 'like', "%$search%")
                    ->orWhere('branches.email', 'like', "%$search%")
                    ->orWhere('branches.google_link', 'like', "%$search%")
                    ->orWhere('branches.social_media_link', 'like', "%$search%")
                    ->orWhere('branches.phone', 'like', "%$search%")
                    ->orWhereHas('region', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    })
                    ->orWhereHas('brand', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    })
                    ->orWhereHas('manager', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            });
        }

        // Role-based restrictions
        if ($user->type === 'company') {
            $branchQuery->where('brand_id', $user->id);
        } elseif (!in_array($user->type, ['super admin', 'Admin Team', 'HR'])) {
            $brandIds = array_keys(FiltersBrands());
            $branchQuery->whereIn('brands', $brandIds);
        }

        if ($user->type === 'Region Manager') {
            $branchQuery->where('region_id', $user->region_id);
        }

        // Additional filters
        if ($request->filled('brand_id')) {
            $branchQuery->where('brands', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $branchQuery->where('region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $branchQuery->where('id', $request->branch_id);
        }

        // CSV download
        if ($request->input('download_csv')) {
            $data = $branchQuery->get()->toArray();
            $filename = 'Attendance_' . now()->timestamp . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return response()->stream(function () use ($data) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['ID', 'Name', 'Email', 'Brand', 'Region', 'Phone', 'Location', 'Branch Manager']);
                foreach ($data as $row) {
                    fputcsv($f, [
                        $row['id'],
                        $row['name'],
                        $row['email'] ?? '',
                        isset($row['brand']['name']) ? $row['brand']['name'] : '',
                        isset($row['region']['name']) ? $row['region']['name'] : '',
                        $row['phone'] ?? '',
                        $row['location'] ?? '',
                        isset($row['manager']['name']) ? $row['manager']['name'] : '',
                    ]);
                }
                fclose($f);
            }, 200, $headers);
        }

        // Pagination
        $totalRecords = $branchQuery->count();
        $branches = $branchQuery->orderBy('name', 'ASC')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $branches->items(),
            'current_page' => $branches->currentPage(),
            'last_page' => $branches->lastPage(),
            'total_records' => $totalRecords,
            'per_page' => $branches->perPage(),
        ], 200);
    }

    public function addBranch(Request $request)
    {
        // Check if the user has permission
        if (!\Auth::user()->can('create branch')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'brands' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_manager_id' => 'nullable|integer|exists:users,id',
            'email' => 'required|email|unique:branches,email,NULL,id,brands,' . $request->brands . ',region_id,' . $request->region_id,
            'full_number' => 'nullable|string|max:20',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
            'end_time' => 'required|string',
            'start_time' => 'required|string',
            'timezone' => 'required|string',
            'google_link' => 'nullable|url',
            'social_media_link' => 'nullable|url',
            'is_sat_off' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $start = \Carbon\Carbon::parse($request->start_time);
        $end = \Carbon\Carbon::parse($request->end_time);
        if ($end->lt($start)) $end->addDay();
        $decimalHours = round($start->floatDiffInHours($end), 2);
        // Create a new branch

         // Update branch details
       $startTimeUtc = Carbon::parse($request->start_time, $request->timezone)
                      ->setTimezone('UTC')
                      ->format('H:i:s');

        $endTimeUtc = Carbon::parse($request->end_time, $request->timezone)
                            ->setTimezone('UTC')
                            ->format('H:i:s');
        $branch = Branch::create([
            'name' => $request->name,
            'brands' => $request->brands,
            'region_id' => $request->region_id,
            'branch_manager_id' => $request->branch_manager_id,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'timezone' => $request->timezone,
            'start_time'         => $startTimeUtc,  // ✅ saved in UTC
            'end_time'           => $endTimeUtc,    // ✅ saved in UTC
            'google_link' => $request->google_link,
            'social_media_link' => $request->social_media_link,
            'phone' => $request->full_number,
            'email' => $request->email,
            'shift_time' => $decimalHours,
            'is_sat_off' => $request->is_sat_off,
            'created_by' => \Auth::user()->creatorId(),
        ]);

            $typeoflog = 'branch';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $branch->name. ' '.$typeoflog.' created',
                        'message' => $branch->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $branch->id,
                    'module_type' => 'branch',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);
        return response()->json([
            'status' => 'success',
            'id' => $branch,
            'message' => __('Branch successfully created.')
        ], 201);
    }
    public function updateBranch(Request $request)
    {
        // Validate request data, including $id existence
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:branches,id',
            'name' => 'required|string|max:255',
            'brands' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_manager_id' => 'nullable|integer|exists:users,id',
            'email' => 'required|email|unique:branches,email,' . $request->id . ',id,brands,' . $request->brands . ',region_id,' . $request->region_id,
            'full_number' => 'required|string|max:20',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
            'timezone' => 'required|string',
            'end_time' => 'required|string',
            'start_time' => 'required|string',
            'google_link' => 'required|url',
            'social_media_link' => 'required|url',
            'shift_time' => 'required|string',
           'is_sat_off' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the user has permission
        if (!\Auth::user()->can('edit branch')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Fetch the branch
        $branch = Branch::find($request->id);

        $originalData = $branch->toArray();

        $start = \Carbon\Carbon::parse($request->start_time);
        $end = \Carbon\Carbon::parse($request->end_time);
        if ($end->lt($start)) $end->addDay();
        $decimalHours = round($start->floatDiffInHours($end), 2);

        // Update branch details
       $startTimeUtc = Carbon::parse($request->start_time, $request->timezone)
                      ->setTimezone('UTC')
                      ->format('H:i:s');

        $endTimeUtc = Carbon::parse($request->end_time, $request->timezone)
                            ->setTimezone('UTC')
                            ->format('H:i:s');

        $branch->update([
            'name'               => $request->name,
            'brands'             => $request->brands,
            'region_id'          => $request->region_id,
            'branch_manager_id'  => $request->branch_manager_id,
            'longitude'          => $request->longitude,
            'latitude'           => $request->latitude,
            'timezone'           => $request->timezone,
            'google_link'        => $request->google_link,
            'social_media_link'  => $request->social_media_link,
            'phone'              => $request->full_number,
            'email'              => $request->email,
            'start_time'         => $startTimeUtc,  // ✅ saved in UTC
            'end_time'           => $endTimeUtc,    // ✅ saved in UTC
            'is_sat_off'         => $request->is_sat_off,
            'shift_time'         => $decimalHours,
        ]);


          // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($branch->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $branch->$field
                ];
                $updatedFields[] = $field;
            }
        } 
         $typeoflog = 'branch';
           
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $branch->name .  ' '.$typeoflog.'  updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $branch->id,
                    'module_type' => 'branch',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }

       
        return response()->json([
            'status' => 'success',
            'branch' => $branch,
            'message' => __('Branch successfully updated.')
        ], 200);
    }

    public function deleteBranch(Request $request)
    {
        // Validate request to ensure `id` exists
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check user permission
        if (!\Auth::user()->can('delete branch')) {
            return response()->json([
                'status' => 'error',
                'msg' => __('Permission denied.'),
            ], 200);
        }

        // Find and delete the branch
        $branch = Branch::find($request->id);


         // Find user
             $employees = User::where('branch_id', $branch->id)->count();

            if ($employees) {
                return response()->json([
                    'status' => 'error',
                    'message' => __("there are ($employees) employees found. Branch cannot be deleted as it is associated with employees.")
                ], 200);
            }
             

        
            $typeoflog = 'branch';
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $branch->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $branch->name .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $branch->id,
                    'module_type' => 'branch',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);
        $branch->delete();

        return response()->json([
            'status' => 'success',
            'msg' => __('Branch successfully deleted.'),
        ], 200);
    }
}
