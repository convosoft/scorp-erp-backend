<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SavedFilter;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Validator;

class TrainingController extends Controller
{
    public function getTraining(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage training')) {
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
            'brand_id' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'employee_id' => 'nullable|integer|exists:users,id',
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

        // Build the query for trainings
         $Training_query = Training::with(['created_by', 'brand', 'branch', 'region', 'trainer','training_type','assign_to']);

        // Apply search filter if provided
                    
            if ($request->filled('search')) {
                $search = $request->input('search');

                $Training_query->where(function ($query) use ($search) {
                    $query->orWhere('training_cost', 'like', "%$search%")
                         ->orWhereHas('training_type', function ($q) use ($search) {
                            $q->where('name', 'like', "%$search%") ;
                        })
                        ->orWhereHas('trainer', function ($q) use ($search) {
                            $q->where('firstname', 'like', "%$search%")
                            ->orWhere('lastname', 'like', "%$search%");
                        });
                });
            }

        // Apply user-specific filters
        $user = Auth::user();
        $brand_ids = array_keys(FiltersBrands());
        if ($user->type === 'super admin' || $user->type === 'Admin Team' || $user->type === 'HR' || $user->can('level 1')) {
            // No additional filtering for these roles
        } elseif ($user->type === 'company') {
            $Training_query->where('trainings.brand_id', $user->id);
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $Training_query->whereIn('trainings.brand_id', $brand_ids);
        } elseif ($user->type === 'Region Manager' && !empty($user->region_id)) {
            $Training_query->where('trainings.region_id', $user->region_id);
        } elseif (($user->type === 'Branch Manager' || in_array($user->type, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) && !empty($user->branch_id)) {
            $Training_query->where('trainings.branch_id', $user->branch_id);
        } else {
            $Training_query->where('trainings.created_by', $user->id);
        }

        // Apply additional filters
        if ($request->filled('brand_id')) {
            $Training_query->where('trainings.brand_id', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $Training_query->where('trainings.region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $Training_query->where('trainings.branch_id', $request->branch_id);
        }
        if ($request->filled('employee_id')) {
            $Training_query->where('trainings.employee', $request->employee_id);
        }

        // Apply sorting and pagination
        $trainings = $Training_query->orderBy('trainings.created_at', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        $totalRecords = $trainings->total();

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $trainings->items(),
            'current_page' => $trainings->currentPage(),
            'last_page' => $trainings->lastPage(),
            'total_records' => $totalRecords,
            'per_page' => $trainings->perPage(),
        ], 200);
    }


    public function addTraining(Request $request)
    {
        if (\Auth::user()->can('create training')) {

            $validator = Validator::make($request->all(), [
                'brand_id' => 'required|numeric|min:1|exists:users,id',
                'region_id' => 'required|numeric|min:1|exists:regions,id',
                'lead_branch' => 'required|numeric|min:1|exists:branches,id',
                'training_type' => 'required',
                'training_cost' => 'required',
                'lead_assigned_user' => 'required|exists:users,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'trainer_option' => 'required',
                'trainer' => 'required',
                'description' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ]);
            }

            $training = new Training();
            $training->brand_id = $request->brand_id;
            $training->region_id = $request->region_id;
            $training->branch_id = $request->lead_branch;
            $training->trainer_option = $request->trainer_option;
            $training->training_type = $request->training_type;
            $training->trainer = $request->trainer;
            $training->training_cost = $request->training_cost;
            $training->employee = $request->lead_assigned_user;
            $training->start_date = $request->start_date;
            $training->end_date = $request->end_date;
            $training->description = $request->description;
            $training->created_by = \Auth::id();
            $training->save();

             //  ========== add ============
                $user = User::find($training->employee);
                $typeoflog = 'training';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $user->name. ' '.$typeoflog.' created',
                        'message' => $user->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $training->id,
                    'module_type' => 'employee',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $user->name. ' '.$typeoflog.'  created',
                        'message' => $user->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $training->employee,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);


            return response()->json([
                'status' => 'success',
                'message' => 'Training created successfully.',
                'id' => $training
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ]);
        }
    }

    public function TrainingDetail(Request $request)
    {
        if (!Auth::user()->can('manage training')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:trainings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $training = Training::with(['employees','created_by', 'brand', 'branch', 'region', 'trainer','training_type'])->where('id', $request->id)->first();

        $status = Training::$Status;

        return response()->json([
            'status' => 'success',
            'data' => $training,
        ]);
    }
    /**
     * Update an existing training.
     */
    public function updateTraining(Request $request)
    {
        if (!Auth::user()->can('edit training')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:trainings,id',
            'brand_id' => 'required|numeric|min:1|exists:users,id',
            'region_id' => 'required|numeric|min:1|exists:regions,id',
            'lead_branch' => 'required|numeric|min:1|exists:branches,id',
            'training_type' => 'required|string|max:255',
            'training_cost' => 'required|numeric|min:0|max:999999',
            'lead_assigned_user' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'trainer_option' => 'required|string|max:255',
            'trainer' => 'required|max:255',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => $validator->errors(),
            ], 422);
        }

        $training = Training::findOrFail($request->id);
         $originalData = $training->toArray();


        $training->update([
            'brand_id' => $request->brand_id,
            'region_id' => $request->region_id,
            'branch_id' => $request->lead_branch,
            'training_type' => $request->training_type,
            'training_cost' => $request->training_cost,
            'employee' => $request->lead_assigned_user,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'trainer_option' => $request->trainer_option,
            'trainer' => $request->trainer,
            'description' => $request->description,
        ]);


        // ============ edit ============


        


           // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($training->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $training->$field
                ];
                $updatedFields[] = $field;
            }
        }
        $user = User::find($training->employee);
         $typeoflog = 'training';
           
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name .  ' '.$typeoflog.'  updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $training->id,
                    'module_type' => 'employee',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }

             
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name .  ' '.$typeoflog.' updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $training->employee,
                    'module_type' => 'employeeprofile',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }



        return response()->json([
            'status' => 'success',
            'message' => __('Training updated successfully.'),
            'data' => $training
        ], 200);
    }   
   public function updateTrainingStatus(Request $request)
    {
        if (!Auth::user()->can('edit training')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:trainings,id',
            'status' => 'required|in:0,1,2,3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => $validator->errors(),
            ], 422);
        }

        $training = Training::findOrFail($request->id);
        

        $training->update([
            'status' => $request->status,
        ]);

        // Log activity
        $statusMap = [
            0 => 'Pending',
            1 => 'Completed',
            2 => 'Terminated',
            3 => 'Suspended',
        ];

        $statusText = $statusMap[$request->status] ?? 'Unknown';

        
 
            $user = User::find($training->employee); // Or $training->employee if relation
            $logNote = [
                'title' => $user->name . ' training status updated to ' . $statusText,
                'message' => $user->name . ' training status updated to ' . $statusText,
            ];

            // Log for module_type: employee
            addLogActivity([
                'type' => 'info',
                'note' => json_encode($logNote),
                'module_id' => $training->id,
                'module_type' => 'employee',
                'notification_type' => 'training Updated',
            ]);

            // Log for module_type: employeeprofile
            addLogActivity([
                'type' => 'info',
                'note' => json_encode($logNote),
                'module_id' => $training->employee,
                'module_type' => 'employeeprofile',
                'notification_type' => 'training Updated',
            ]);
        

        return response()->json([
            'status' => 'success',
            'message' => __('Training updated successfully.'),
            'data' => $training
        ], 200);
    }


    /**
     * Delete a training.
     */
    public function deleteTraining(Request $request)
    {
        if (!Auth::user()->can('delete training')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:trainings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $training = Training::findOrFail($request->id);

        $training->delete();
        
            //    =================== delete ===========

            $user = User::find($training->employee); 
            $typeoflog = 'training';
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $user->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $user->name .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $training->id,
                    'module_type' => 'training',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);
            

                
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $user->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $user->name .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $training->employee,
                    'module_type' => 'employeeprofile',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Training deleted successfully.')
        ], 200);
    }
}
