<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\LeadTag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeadTagController extends Controller
{

    /**
     * Get list of LeadTags
     */
    public function getLeadTags(Request $request)
    {
        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'brand_id' => 'nullable|integer',
            'region_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE",50));
        $page = $request->input('page',1);

        $user = Auth::user();

        $query = LeadTag::select(
            'lead_tags.id',
            'lead_tags.tag',
            'users.name as brand',
            'branches.name as branch',
            'regions.name as region'
        )
        ->leftJoin('users', 'users.id', '=', 'lead_tags.brand_id')
        ->leftJoin('branches', 'branches.id', '=', 'lead_tags.branch_id')
        ->leftJoin('regions', 'regions.id', '=', 'branches.region_id')
        ->where('lead_tags.tag','!=','');

        /**
         * Permission based filtering
         */
        if (!in_array($user->type,['super admin','Admin Team'])) {
            $query->where('lead_tags.brand_id',$user->brand_id);
        }

        /**
         * Search
         */
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function($q) use ($search){
                $q->where('lead_tags.tag','like',"%$search%")
                ->orWhere('users.name','like',"%$search%")
                ->orWhere('branches.name','like',"%$search%");
            });
        }

        /**
         * Filters
         */
        if ($request->filled('brand_id')) {
            $query->where('lead_tags.brand_id',$request->brand_id);
        }

        if ($request->filled('region_id')) {
            $query->where('branches.region_id',$request->region_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('lead_tags.branch_id',$request->branch_id);
        }

        $totalRecords = $query->count();

        $tags = $query
            ->orderBy('lead_tags.tag','DESC')
            ->paginate($perPage,['*'],'page',$page);

        return response()->json([
            'status'=>'success',
            'data'=>$tags->items(),
            'current_page'=>$tags->currentPage(),
            'last_page'=>$tags->lastPage(),
            'total_records'=>$totalRecords,
            'per_page'=>$tags->perPage()
        ],200);
    }

    /**
     * Create Tag
     */
    public function addLeadTag(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        $validator = Validator::make($request->all(),[
            'name'=>'required|max:20',
            'brand'=>'required|integer',
            'region_id'=>'required|integer',
            'branch_id'=>'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        $tag = LeadTag::create([
            'tag'=>$request->name,
            'brand_id'=>$request->brand,
            'region_id'=>$request->region_id,
            'branch_id'=>$request->branch_id,
            'created_by'=>Auth::user()->ownerId()
        ]);

        /**
         * Logging
         */
        addLogActivity([
            'type'=>'success',
            'note'=>json_encode([
                'title'=>$tag->tag.' LeadTag created',
                'message'=>$tag->tag.' LeadTag created'
            ]),
            'module_id'=>$tag->id,
            'module_type'=>'LeadTag',
            'notification_type'=>'LeadTag Created'
        ]);

        return response()->json([
            'status'=>'success',
            'message'=>__('LeadTag created successfully.'),
            'data'=>$tag
        ],201);

    }


    /**
     * Update Tag
     */
    public function updateLeadTag(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        $validator = Validator::make($request->all(),[
            'id'=>'required|integer|exists:lead_tags,id',
            'name'=>'required|max:20',
            'brand'=>'required|integer',
            'region_id'=>'required|integer',
            'branch_id'=>'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        $tag = LeadTag::findOrFail($request->id);

        $originalData = $tag->toArray();

        $tag->update([
            'tag'=>$request->name,
            'brand_id'=>$request->brand,
            'region_id'=>$request->region_id,
            'branch_id'=>$request->branch_id,
            'created_by'=>Auth::user()->ownerId()
        ]);

        /**
         * Track Changes
         */
        $changes=[];
        $updatedFields=[];

        foreach($originalData as $field=>$oldValue){

            if(in_array($field,['created_at','updated_at'])) continue;

            if($tag->$field != $oldValue){

                $changes[$field]=[
                    'old'=>$oldValue,
                    'new'=>$tag->$field
                ];

                $updatedFields[]=$field;

            }

        }

        if(!empty($changes)){

            addLogActivity([
                'type'=>'info',
                'note'=>json_encode([
                    'title'=>$tag->tag.' LeadTag updated',
                    'message'=>'Fields updated: '.implode(', ',$updatedFields),
                    'changes'=>$changes
                ]),
                'module_id'=>$tag->id,
                'module_type'=>'LeadTag',
                'notification_type'=>'LeadTag Updated'
            ]);

        }

        return response()->json([
            'status'=>'success',
            'message'=>__('LeadTag updated successfully.'),
            'data'=>$tag
        ],200);

    }


    /**
     * Delete Tag
     */
    public function deleteLeadTag(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        $validator = Validator::make($request->all(),[
            'id'=>'required|integer|exists:lead_tags,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        $tag = LeadTag::findOrFail($request->id);

        addLogActivity([
            'type'=>'warning',
            'note'=>json_encode([
                'title'=>$tag->tag.' LeadTag deleted',
                'message'=>$tag->tag.' LeadTag deleted'
            ]),
            'module_id'=>$tag->id,
            'module_type'=>'LeadTag',
            'notification_type'=>'LeadTag Deleted'
        ]);

        $tag->delete();

        return response()->json([
            'status'=>'success',
            'message'=>__('LeadTag deleted successfully.')
        ],200);

    }


    /**
     * Bulk Delete
     */
    public function deleteBulkLeadTags(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        if(!$request->ids){
            return response()->json([
                'status'=>'error',
                'message'=>'At least select 1 LeadTag.'
            ],422);
        }

        $ids = explode(',',$request->ids);

        $tags = LeadTag::whereIn('id',$ids)->get();

        foreach($tags as $tag){

            addLogActivity([
                'type'=>'warning',
                'note'=>json_encode([
                    'title'=>$tag->tag.' LeadTag deleted',
                    'message'=>$tag->tag.' LeadTag deleted'
                ]),
                'module_id'=>$tag->id,
                'module_type'=>'LeadTag',
                'notification_type'=>'LeadTag Deleted'
            ]);

            $tag->delete();
        }

        return response()->json([
            'status'=>'success',
            'message'=>'LeadTags deleted successfully'
        ],200);

    }


    /**
     * LeadTag Detail
     */
    public function LeadTagDetail(Request $request)
    {

        $validator = Validator::make($request->all(),[
            'id'=>'required|integer|exists:lead_tags,id'
        ]);

        if($validator->fails()){
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        $tag = LeadTag::with(['brand','branch'])->findOrFail($request->id);

        return response()->json([
            'status'=>'success',
            'data'=>$tag
        ],200);

    }

}
