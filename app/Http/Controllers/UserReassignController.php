<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserReassignController extends Controller
{
    public function reassignUserData(Request $request)
    {
        // First validate that `update_all` is a string and not empty
        $validator = Validator::make($request->all(), [
            'oldUserId'   => 'required|integer|exists:users,id',
            'newUserId'   => 'required|integer|exists:users,id',
            'oldBranchId' => 'required|integer|exists:branches,id',
            'newBranchId' => 'required|integer|exists:branches,id',
            'oldBrandId'  => 'required|integer|exists:users,id',
            'newBrandId'  => 'required|integer|exists:users,id',
            'oldRegionId' => 'required|integer|exists:regions,id',
            'newRegionId' => 'required|integer|exists:regions,id',
            'update_all'   => 'required|string'
        ], [
            'exists' => 'The selected :attribute does not exist in the database.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first()
            ], 200);
        }

        $params = $validator->validated();

        // Convert to array and validate choices
        $allowed = ['all update', 'deal_tasks', 'leads', 'deals'];
        $selected = array_map('trim', explode(',', $params['update_all']));

        foreach ($selected as $option) {
            if (!in_array($option, $allowed)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Invalid update option: {$option}"
                ], 200);
            }
        }

        try {
            DB::beginTransaction();

            if (in_array('all update', $selected) || in_array('deal_tasks', $selected)) {
                DB::table('deal_tasks')
                    ->where('branch_id', $params['oldBranchId'])
                    ->where('brand_id', $params['oldBrandId'])
                    ->where('region_id', $params['oldRegionId'])
                    ->where(function ($query) use ($params) {
                        $query->where('created_by', $params['oldUserId'])
                            ->orWhere('assigned_to', $params['oldUserId']);
                    })
                    ->update([
                        'branch_id'   => $params['newBranchId'],
                        'brand_id'    => $params['newBrandId'],
                        'region_id'   => $params['newRegionId'],
                        'assigned_to' => $params['newUserId'],
                        // 'created_by'  => $params['newUserId'],
                    ]);
            }

            if (in_array('all update', $selected) || in_array('leads', $selected)) {
                $leadsIds = DB::table('leads')
                    ->where('branch_id', $params['oldBranchId'])
                    ->where('brand_id', $params['oldBrandId'])
                    ->where('region_id', $params['oldRegionId'])
                    ->where(function ($query) use ($params) {
                        $query->where('user_id', $params['oldUserId'])
                            ->orWhere('created_by', $params['oldUserId']);
                    })
                    ->pluck('id');
                // Batch update related deal_tasks in one query
                if ($leadsIds->isNotEmpty()) {
                    DB::table('deal_tasks')
                        ->where('related_type', 'lead')
                        ->whereIn('related_to', $leadsIds)
                        ->update([
                            'branch_id'   => $params['newBranchId'],
                            'brand_id'    => $params['newBrandId'],
                            'region_id'   => $params['newRegionId'],
                            'assigned_to' => $params['newUserId'],
                        ]);
                }
                DB::table('leads')
                    ->whereIn('id', $leadsIds)
                    ->update([
                        'branch_id'  => $params['newBranchId'],
                        'brand_id'   => $params['newBrandId'],
                        'region_id'  => $params['newRegionId'],
                        'user_id'    => $params['newUserId'],
                        // 'created_by' => $params['newUserId'],
                    ]);
            }

            if (in_array('all update', $selected) || in_array('deals', $selected)) {

                // Get all related application IDs
                $applicationIds = DB::table('deal_applications')
                    ->join('deals', 'deals.id', '=', 'deal_applications.deal_id')
                    ->where('deals.branch_id', $params['oldBranchId'])
                    ->where('deals.brand_id', $params['oldBrandId'])
                    ->where('deals.region_id', $params['oldRegionId'])
                    ->where(function ($query) use ($params) {
                        $query->where('deals.created_by', $params['oldUserId'])
                            ->orWhere('deals.assigned_to', $params['oldUserId']);
                    })
                    ->pluck('deal_applications.id');
                    
                // Batch update in one query
                if ($applicationIds->isNotEmpty()) {
                    DB::table('deal_tasks')
                        ->where('related_type', 'application')
                        ->whereIn('related_to', $applicationIds)
                        ->update([
                            'branch_id'   => $params['newBranchId'],
                            'brand_id'    => $params['newBrandId'],
                            'region_id'   => $params['newRegionId'],
                            'assigned_to' => $params['newUserId'],
                        ]);
                }

                DB::table('deals')
                    ->where('branch_id', $params['oldBranchId'])
                    ->where('brand_id', $params['oldBrandId'])
                    ->where('region_id', $params['oldRegionId'])
                    ->where(function ($query) use ($params) {
                        $query->where('created_by', $params['oldUserId'])
                            ->orWhere('assigned_to', $params['oldUserId']);
                    })
                    ->update([
                        'branch_id'   => $params['newBranchId'],
                        'brand_id'    => $params['newBrandId'],
                        'region_id'   => $params['newRegionId'],
                        'assigned_to' => $params['newUserId'],
                    ]);
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Selected records updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Database update failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
