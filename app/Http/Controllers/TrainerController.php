<?php


namespace App\Http\Controllers;

use App\Models\Trainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TrainerController extends Controller
{
    public function getTrainers(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage trainer')) {
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

        // Build the query for trainers
        $Trainer_query = Trainer::with(['created_by', 'brand', 'branch', 'region']);

        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->input('search');
            $Trainer_query->where(function ($query) use ($search) {
                $query->where('trainers.firstname', 'like', "%$search%")
                    ->orWhere('trainers.lastname', 'like', "%$search%")
                    ->orWhere('trainers.email', 'like', "%$search%");
            });
        }

        // Apply user-specific filters
        $user = Auth::user();
        $brand_ids = array_keys(FiltersBrands());
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->type == 'HR' || \Auth::user()->can('level 1')) {
        } else if ($user->type === 'company') {
            $Trainer_query->where('trainers.brand_id', $user->id);
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $Trainer_query->whereIn('trainers.brand_id', $brand_ids);
        } elseif ($user->type === 'Region Manager' && !empty($user->region_id)) {
            $Trainer_query->where('trainers.region_id', $user->region_id);
        } elseif (($user->type === 'Branch Manager' || in_array($user->type, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) && !empty($user->branch_id)) {
            $Trainer_query->where('trainers.branch_id', $user->branch_id);
        } else {
            $Trainer_query->where('trainers.created_by', $user->id);
        }

        // Apply additional filters
        if ($request->filled('brand_id')) {
            $Trainer_query->where('trainers.brand_id', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $Trainer_query->where('trainers.region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $Trainer_query->where('trainers.branch_id', $request->branch_id);
        }

        // Apply sorting and pagination
        $trainers = $Trainer_query->orderBy('trainers.firstname', 'ASC')
            ->paginate($perPage, ['*'], 'page', $page);

        $totalRecords = $trainers->total();

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $trainers->items(),
            'current_page' => $trainers->currentPage(),
            'last_page' => $trainers->lastPage(),
            'total_records' => $totalRecords,
            'per_page' => $trainers->perPage(),
        ], 200);
    }
    
    public function Trainers(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage trainer')) {
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

        // Build the query for trainers
        $Trainer_query = Trainer::query();

        // Apply search filter if provided


        // Apply user-specific filters
        $user = Auth::user();
        $brand_ids = array_keys(FiltersBrands());
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->type == 'HR' || \Auth::user()->can('level 1')) {
        } else if ($user->type === 'company') {
            $Trainer_query->where('trainers.brand_id', $user->id);
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $Trainer_query->whereIn('trainers.brand_id', $brand_ids);
        } elseif ($user->type === 'Region Manager' && !empty($user->region_id)) {
            $Trainer_query->where('trainers.region_id', $user->region_id);
        } elseif (($user->type === 'Branch Manager' || in_array($user->type, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) && !empty($user->branch_id)) {
            $Trainer_query->where('trainers.branch_id', $user->branch_id);
        } else {
            $Trainer_query->where('trainers.created_by', $user->id);
        }

        // Apply additional filters


        // Apply sorting and pagination
        $trainers = $Trainer_query->orderBy('trainers.firstname', 'ASC')
        ->select('trainers.id', \DB::raw("CONCAT(trainers.firstname, ' ', trainers.lastname) as fullname"))
        ->pluck('fullname', 'id')
        ->toArray();


        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $trainers,
        ], 200);
    }
    public function trainerDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:trainers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }
        $trainer = Trainer::with(['created_by', 'brand', 'branch', 'region'])->find($request->id);
        if (!$trainer) {
            return response()->json(['status' => 'error', 'message' => 'Trainer not found.'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $trainer]);
    }

    public function addTrainer(Request $request)
    {
        if (!Auth::user()->can('create trainer')) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied.')], 403);
        }

        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|numeric|min:1',
            'region_id' => 'required|numeric|min:1',
            'branch_id' => 'required|numeric|min:1',
            'firstname' => 'required',
            'lastname' => 'required',
            'contact' => 'required',
            'email' => 'required|email',
            'expertise' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $trainer = Trainer::create(array_merge($request->all(), ['created_by' => Auth::id()]));

               //  ========== add ============
                
                $typeoflog = 'trainer';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $trainer->firstname. ' '.$typeoflog.' created',
                        'message' => $trainer->firstname. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $trainer->id,
                    'module_type' => 'training',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

               

        return response()->json(['status' => 'success', 'message' => 'Trainer created successfully.', 'data' => $trainer]);
    }

    public function updateTrainer(Request $request)
    {
        if (!Auth::user()->can('edit trainer')) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied.')], 403);
        }



        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|numeric|min:1',
            'region_id' => 'required|numeric|min:1',
            'branch_id' => 'required|numeric|min:1',
            'firstname' => 'required',
            'lastname' => 'required',
            'contact' => 'required',
            'email' => 'required',
            'expertise' => 'required',
            'id' => 'required|exists:trainers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $trainer = Trainer::find($request->id);
         $originalData = $trainer->toArray();
        if (!$trainer) {
            return response()->json(['status' => 'error', 'message' => 'Trainer not found.'], 404);
        }

        $trainer->update($request->all());

        // ============ edit ============


        


           // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($trainer->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $trainer->$field
                ];
                $updatedFields[] = $field;
            }
        } 
         $typeoflog = 'trainer';
           
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $trainer->name .  ' '.$typeoflog.'  updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $trainer->id,
                    'module_type' => 'trainer',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }

       



        return response()->json(['status' => 'success', 'message' => 'Trainer updated successfully.', 'data' => $trainer]);
    }

    public function deleteTrainer(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:trainers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }
        if (!Auth::user()->can('delete trainer')) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied.')], 403);
        }

        $trainer = Trainer::find($request->id);
        if (!$trainer) {
            return response()->json(['status' => 'error', 'message' => 'Trainer not found.'], 404);
        }

          //    =================== delete ===========
 
            $typeoflog = 'trainer';
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $trainer->firstname .  ' '.$typeoflog.'  deleted ',
                        'message' => $trainer->firstname .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $trainer->id,
                    'module_type' => 'trainer',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);

        $trainer->delete();
        return response()->json(['status' => 'success', 'message' => 'Trainer successfully deleted.']);
    }
}
