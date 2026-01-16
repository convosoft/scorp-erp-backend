<?php

namespace App\Http\Controllers;

use App\Models\Salaryappriasal;
use App\Models\Salaryappriasalremark;
use App\Models\Branch;
use App\Models\Competencies;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Indicator;
use App\Models\LogActivity;
use App\Models\Performance_Type;
use App\Models\PerformanceType;
use App\Models\SavedFilter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SalaryappriasalController extends Controller
{

    public function getSalaryappriasals(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'user_id' => 'required|integer|exists:users,id',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id', 
            'created_at' => 'nullable|date',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
 
        // Default pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Build the query
        $SalaryappriasalQuery = Salaryappriasal::select(
            'salaryappriasals.*',
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'assigned_to.name as created_user',
            'branches.name as branch_id',
        )
            ->with('employees')
            ->leftJoin('users', 'users.id', '=', 'salaryappriasals.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'salaryappriasals.branch_id')
            ->leftJoin('regions', 'regions.id', '=', 'salaryappriasals.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'salaryappriasals.created_by');

         $SalaryappriasalQuery->where('salaryappriasals.user_id', $request->user_id);
        // Apply filters
        if ($request->filled('brand')) {
            $SalaryappriasalQuery->where('salaryappriasals.brand_id', $request->brand);
           
        }

        if ($request->filled('region_id')) {
            $SalaryappriasalQuery->where('salaryappriasals.region_id', $request->region_id);
        }

        if ($request->filled('branch_id')) {
            $SalaryappriasalQuery->where('salaryappriasals.branch_id', $request->branch_id);
        }

         

        if ($request->filled('created_at')) {
            $SalaryappriasalQuery->whereDate('salaryappriasals.created_at', '=', $request->created_at);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $SalaryappriasalQuery->where(function($query) use ($search) {
                $query->whereHas('employees', function($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    })
                    ->orWhere('regions.name', 'like', "%$search%")
                    ->orWhere('branches.name', 'like', "%$search%")
                    ->orWhere('users.name', 'like', "%$search%");
            });
        }

        // Fetch paginated Salaryappriasals
        $Salaryappriasals = $SalaryappriasalQuery
            ->orderBy('salaryappriasals.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Get competency count
        $userType = Role::where('name', $user->type)->first();
        $competencyCount = Competencies::where('type', $userType->id)->count();
        $competencyCount = $competencyCount == 0 ? 1 : $competencyCount;

        // Get summary data for active/inactive Salaryappriasals

        // Return JSON response
        return response()->json([
            'status' => 'success',
            'baseurl' => asset('/'),
            'data' => $Salaryappriasals->items(),
            'current_page' => $Salaryappriasals->currentPage(),
            'last_page' => $Salaryappriasals->lastPage(),
            'total_records' => $Salaryappriasals->total(),
            'per_page' => $Salaryappriasals->perPage(),
            'competency_count' => $competencyCount,
            'message' => __('Salaryappriasals retrieved successfully'),
        ]);
    }

    public function addSalaryappriasal(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('create appraisal')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 200);
        }

        // Validation rules
        $validator = Validator::make($request->all(), [
           'user_id' => 'required|integer|exists:users,id',
            'brand_id' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'role_id' => 'required|integer|exists:roles,id',
            'connenced_date' => 'required|date',
            'completed_date' => 'required|date', 
            'remuneration' => 'required|numeric',
            'currency' => 'required|string',
            'salary_type' => 'required|string',
            'comments' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }
 

        // Create a new Salaryappriasal instance
        $Salaryappriasal = new Salaryappriasal();
        $Salaryappriasal->brand_id = $request->brand_id;
        $Salaryappriasal->region_id = $request->region_id;
        $Salaryappriasal->branch_id = $request->branch_id;
        $Salaryappriasal->user_id = $request->user_id;
        $Salaryappriasal->role_id = $request->role_id; 
        $Salaryappriasal->connenced_date = $request->connenced_date;
        $Salaryappriasal->completed_date = $request->completed_date ;
        $Salaryappriasal->remuneration = $request->remuneration;
        $Salaryappriasal->currency = $request->currency ;
        $Salaryappriasal->salary_type = $request->salary_type;
        $Salaryappriasal->job_type = $request->job_type ;
        $Salaryappriasal->comments = $request->comments;
          $files = [ 'attachment'];
        $uploadedFiles = [];

        foreach ($files as $fileType) {
            if ($request->hasFile($fileType)) {
                // Generate unique file name
                $filename = time() . '-' . uniqid() . '.' . $request->file($fileType)->extension();
                $request->file($fileType)->move(public_path('appriasal'), $filename);
                $uploadedFiles[$fileType] = $filename; // ✅ Correct assignment
            } else {
                $uploadedFiles[$fileType] = null;
            }
        }  

        if (!empty($uploadedFiles['attachment'])) {
            $Salaryappriasal->attachment = 'appriasal/'.$uploadedFiles['attachment'];
        }
  
        $Salaryappriasal->created_by = Auth::id();
        $Salaryappriasal->updated_by = Auth::id();
        $Salaryappriasal->save();

      

            //  ========== add ============
        $user = User::find($Salaryappriasal->user_id);
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $user->name. ' Salary appriasal created',
                'message' => $user->name. ' Salary appriasal created'
            ]),
            'module_id' => $Salaryappriasal->id,
            'module_type' => 'Salaryappriasal',
            'notification_type' => 'Salary appriasal Created',
        ]);

          addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $user->name. ' Salary appriasal created',
                'message' => $user->name. ' Salary appriasal created'
            ]),
            'module_id' => $Salaryappriasal->employee,
            'module_type' => 'employeeprofile',
            'notification_type' => 'Salary appriasal Created',
        ]);


        return response()->json([
            'status' => 'success',
            'message' => __('Salaryappriasal successfully created.'),
            'data' => $Salaryappriasal,
        ], 201);
    }


    public function updateSalaryappriasal(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('edit appriasal')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 200);
        }
 

         // Validation rules
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:salaryappriasals,id',
           'user_id' => 'required|integer|exists:users,id',
            'brand_id' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'role_id' => 'required|integer|exists:roles,id',
            'connenced_date' => 'required|date',
            'completed_date' => 'required|date', 
            'remuneration' => 'required|numeric',
            'currency' => 'required|string',
            'salary_type' => 'required|string',
            'comments' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Retrieve the Salaryappriasal
        $Salaryappriasal = Salaryappriasal::findOrFail($request->id);

        $originalData = $Salaryappriasal->toArray();

        // Update Salaryappriasal details
         $Salaryappriasal->brand_id = $request->brand_id;
        $Salaryappriasal->region_id = $request->region_id;
        $Salaryappriasal->branch_id = $request->branch_id;
        $Salaryappriasal->user_id = $request->user_id;
        $Salaryappriasal->role_id = $request->role_id; 
        $Salaryappriasal->connenced_date = $request->connenced_date;
        $Salaryappriasal->completed_date = $request->completed_date ;
        $Salaryappriasal->remuneration = $request->remuneration;
        $Salaryappriasal->currency = $request->currency ;
        $Salaryappriasal->salary_type = $request->salary_type;
        $Salaryappriasal->job_type = $request->job_type ;
        $Salaryappriasal->comments = $request->comments;
          $files = [ 'attachment'];
        $uploadedFiles = [];

        foreach ($files as $fileType) {
            if ($request->hasFile($fileType)) {
                // Generate unique file name
                $filename = time() . '-' . uniqid() . '.' . $request->file($fileType)->extension();
                $request->file($fileType)->move(public_path('appriasal'), $filename);
                $uploadedFiles[$fileType] = $filename; // ✅ Correct assignment
            } else {
                $uploadedFiles[$fileType] = null;
            }
        }  

        if (!empty($uploadedFiles['attachment'])) {
            $Salaryappriasal->attachment = 'appriasal/'.$uploadedFiles['attachment'];
        }
   
        $Salaryappriasal->updated_by = Auth::id();
        $Salaryappriasal->save();

      
 

        

        // Log activity if Salaryappriasal is submitted
         // ============ edit ============

           // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($Salaryappriasal->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $Salaryappriasal->$field
                ];
                $updatedFields[] = $field;
            }
        }
        $user = User::find($Salaryappriasal->user_id);
           
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name . ' Salary appriasal updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $Salaryappriasal->id,
                    'module_type' => 'Salaryappriasal',
                    'notification_type' => 'Salary appriasal Updated'
                ]);
            }

             
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name . ' Salary appriasal updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $Salaryappriasal->employee,
                    'module_type' => 'employeeprofile',
                    'notification_type' => 'Salary appriasal Updated'
                ]);
            }



        return response()->json([
            'status' => 'success',
            'message' => __('Salaryappriasal successfully updated.'),
            'data' => $Salaryappriasal,
        ]);
    }


    public function deleteSalaryappriasal(Request $request)
    {

         $user = Auth::user();

        // if (!$user->can('delete appriasal')) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission denied'),
        //     ], 200);
        // }
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salaryappriasals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        if (!Auth::user()->can('delete appriasal')) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied.')], 403);
        }

        $Salaryappriasal = Salaryappriasal::find($request->id);
        if (!$Salaryappriasal) {
            return response()->json(['status' => 'error', 'message' => 'Salary appriasal not found.'], 404);
        }



        

        // Delete the Salaryappriasal
        $Salaryappriasal->delete();

            //    =================== delete ===========

            $user = User::find($Salaryappriasal->user_id    ); 
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $user->name . ' Salary appriasal deleted ',
                        'message' => $user->name . ' Salary appriasal deleted '
                    ]),
                    'module_id' => $Salaryappriasal->id,
                    'module_type' => 'Salaryappriasal',
                    'notification_type' => 'Salaryappriasal deleted'
                ]);
            

                
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $user->name . ' Salary appriasal deleted ',
                        'message' => $user->name . ' Salary appriasal deleted '
                    ]),
                    'module_id' => $user->id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => 'Salary appriasal deleted'
                ]);
            

        return response()->json(['status' => 'success', 'message' => 'Salary appriasal  successfully deleted.']);
    }

    public function SalaryappriasalDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salaryappriasals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $Salaryappriasal = Salaryappriasal::select(
            'salaryappriasals.*',
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'assigned_to.name as created_user',
            'branches.id as branch_id',
            'assigned_to.id as created_id',
            'createdby.name as createdbyname',
            'updatedby.name as updatedbname'
        )
            ->with('employees')
            ->leftJoin('users', 'users.id', '=', 'salaryappriasals.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'salaryappriasals.branch_id')
            ->leftJoin('regions', 'regions.id', '=', 'salaryappriasals.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'salaryappriasals.user_id')
            ->leftJoin('users as createdby', 'createdby.id', '=', 'salaryappriasals.created_by')
            ->leftJoin('users as updatedby', 'updatedby.id', '=', 'salaryappriasals.updated_by')
            ->where('salaryappriasals.id', $request->id)
            ->first();

        if (!$Salaryappriasal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Not Found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'baseurl' => asset('/'),
            'data' => $Salaryappriasal,
        ]);
    }

    public function fetchperformance(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'employee' => 'required|exists:users,id',
            'Salaryappriasal' => 'nullable|exists:salaryappriasals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

         // Fetch Salaryappriasal data
        $Salaryappriasal = Salaryappriasal::with('SalaryappriasalRemarks')->find($request->Salaryappriasal);

        $userget = User::find($request->employee);
        $user_type = Role::where('name', $userget->type)->first();
        //dd($user_type);
        $indicator = Indicator::where('designation', $user_type->id)->first();
        $ratings = !empty($indicator) ? json_decode($indicator->rating, true) : []; 
        $rating = !empty($Salaryappriasal) ? json_decode($Salaryappriasal->rating, true) : [];
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $performance_types = Role::whereNotIn('name', $excludedTypes)
            ->where('name', $userget->type)
            ->get();

        foreach ($performance_types as $performance_type) {
            $performance_type->competencies = Competencies::whereRaw(
                'JSON_CONTAINS(type, ?, "$")',
                [json_encode((int)$performance_type->id)]
            )->get();
        }

        return response()->json([
            'status' => "success",
            'userget' => $userget,
            'performance_types' => $performance_types,
            'ratings' => $ratings,
            'rating' => $rating,
        ]);
    }

    public function fetchperformanceedit(Request $request)
    {
        // Fetch Salaryappriasal data
        $Salaryappriasal = Salaryappriasal::with('SalaryappriasalRemarks')->find($request->Salaryappriasal);

        // Fetch user data
        $userget = User::find($request->employee);

        // Fetch user role
        $user_type = Role::where('name', $userget->type)->first();

        // Fetch indicator data
        $indicator = Indicator::where('designation', $user_type->id)->first();

        // Exclude certain roles
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $performance_types = Role::whereNotIn('name', $excludedTypes)
            ->where('name', $userget->type)
            ->get();

        // Decode ratings
        $ratings = !empty($indicator) ? json_decode($indicator->rating, true) : [];
        $rating = !empty($Salaryappriasal) ? json_decode($Salaryappriasal->rating, true) : [];

        // Add competencies to each performance type
        foreach ($performance_types as $performance_type) {
            $performance_type->competencies = Competencies::whereRaw(
                'JSON_CONTAINS(type, ?, "$")',
                [json_encode((int)$performance_type->id)]
            )->get();
        }

        // Return JSON response
        return response()->json([
            'status' => "success",
            'userget' => $userget,
            'performance_types' => $performance_types,
            'ratings' => $ratings,
            'rating' => $rating,
            'Salaryappriasal' => $Salaryappriasal,
        ]);
    }
}
