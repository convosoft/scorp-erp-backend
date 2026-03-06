<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\AgencyTag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AgencyTagController extends Controller
{

    /**
     * Get list of AgencyTags
     */
    public function getAgencyTags(Request $request)
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

        $query = AgencyTag::select(
            'agency_tags.id',
            'agency_tags.tag',
            'users.name as brand',
            'branches.name as branch',
            'regions.name as region'
        )
        ->leftJoin('users', 'users.id', '=', 'agency_tags.brand_id')
        ->leftJoin('branches', 'branches.id', '=', 'agency_tags.branch_id')
        ->leftJoin('regions', 'regions.id', '=', 'agency_tags.region_id')
        ->where('agency_tags.tag','!=','');

        /**
         * Permission based filtering
         */
        if (!in_array($user->type,['super admin','Admin Team'])) {
            $query->where('agency_tags.brand_id',$user->brand_id);
        }

        /**
         * Search
         */
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function($q) use ($search){
                $q->where('agency_tags.tag','like',"%$search%")
                ->orWhere('users.name','like',"%$search%")
                ->orWhere('branches.name','like',"%$search%");
            });
        }

        /**
         * Filters
         */
        if ($request->filled('brand_id')) {
            $query->where('agency_tags.brand_id',$request->brand_id);
        }

        if ($request->filled('region_id')) {
            $query->where('agency_tags.region_id',$request->region_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('agency_tags.branch_id',$request->branch_id);
        }

        $totalRecords = $query->count();

        $tags = $query
            ->orderBy('agency_tags.id','DESC')
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
    public function addAgencyTag(Request $request)
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

        // $tag = AgencyTag::create([
        //     'tag'=>$request->name,
        //     'brand_id'=>$request->brand,
        //     'region_id'=>$request->region_id,
        //     'branch_id'=>$request->branch_id,
        //     'created_by'=>Auth::user()->ownerId()
        // ]);

            $tag                    = new AgencyTag();
            $tag->tag               = $request->name;
            $tag->brand_id          = $request->brand;
            $tag->region_id         = $request->region_id;
            $tag->branch_id         = $request->branch_id;
            $tag->created_by        = \Auth::user()->ownerId();
            $tag->save();

        /**
         * Logging
         */
        addLogActivity([
            'type'=>'success',
            'note'=>json_encode([
                'title'=>$tag->tag.' Agency Tag created',
                'message'=>$tag->tag.' Agency Tag created'
            ]),
            'module_id'=>$tag->id,
            'module_type'=>'AgencyTag',
            'notification_type'=>'Agency Tag Created'
        ]);

        return response()->json([
            'status'=>'success',
            'message'=>__('Agency Tag created successfully.'),
            'data'=>$tag
        ],201);

    }


    /**
     * Update Tag
     */
    public function updateAgencyTag(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        $validator = Validator::make($request->all(),[
            'id'=>'required|integer|exists:agency_tags,id',
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

        $tag = AgencyTag::findOrFail($request->id);

        $originalData = $tag->toArray();

        // $tag->update([
        //     'tag'=>$request->name,
        //     'brand_id'=>$request->brand,
        //     'region_id'=>$request->region_id,
        //     'branch_id'=>$request->branch_id,
        //     'created_by'=>Auth::user()->ownerId()
        // ]);


            $tag->tag       = $request->name;
            $tag->brand_id       = $request->brand;
            $tag->region_id       = $request->region_id;
            $tag->branch_id       = $request->branch_id;
            $tag->created_by = \Auth::user()->ownerId();
            $tag->save();

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
                    'title'=>$tag->tag.' Agency Tag updated',
                    'message'=>'Fields updated: '.implode(', ',$updatedFields),
                    'changes'=>$changes
                ]),
                'module_id'=>$tag->id,
                'module_type'=>'AgencyTag',
                'notification_type'=>'Agency Tag Updated'
            ]);

        }

        return response()->json([
            'status'=>'success',
            'message'=>__('Agency Tag updated successfully.'),
            'data'=>$tag
        ],200);

    }


    /**
     * Delete Tag
     */
    public function deleteAgencyTag(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        $validator = Validator::make($request->all(),[
            'id'=>'required|integer|exists:agency_tags,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        $tag = AgencyTag::findOrFail($request->id);

        addLogActivity([
            'type'=>'warning',
            'note'=>json_encode([
                'title'=>$tag->tag.' Agency Tag deleted',
                'message'=>$tag->tag.' Agency Tag deleted'
            ]),
            'module_id'=>$tag->id,
            'module_type'=>'AgencyTag',
            'notification_type'=>'Agency Tag Deleted'
        ]);

        $tag->delete();

        return response()->json([
            'status'=>'success',
            'message'=>__('Agency Tag deleted successfully.')
        ],200);

    }


    /**
     * Bulk Delete
     */
    public function deleteBulkAgencyTags(Request $request)
    {

        if (!(Auth::user()->can('level 2') || Auth::user()->type == 'Project Director' || Auth::user()->type == 'Project Manager')) {
            return response()->json([
                'status'=>'error',
                'message'=>__('Permission Denied.')
            ],403);
        }

        $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:agency_tags,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ids = $request->ids;

            $tags = AgencyTag::whereIn('id', $ids)->get();

        foreach($tags as $tag){

            addLogActivity([
                'type'=>'warning',
                'note'=>json_encode([
                    'title'=>$tag->tag.' Agency Tag deleted',
                    'message'=>$tag->tag.' Agency Tag deleted'
                ]),
                'module_id'=>$tag->id,
                'module_type'=>'AgencyTag',
                'notification_type'=>'Agency Tag Deleted'
            ]);

            $tag->delete();
        }

        return response()->json([
            'status'=>'success',
            'message'=>'Agency Tags deleted successfully'
        ],200);

    }


    /**
     * AgencyTag Detail
     */
    public function AgencyTagDetail(Request $request)
    {

        $validator = Validator::make($request->all(),[
            'id'=>'required|integer|exists:agency_tags,id'
        ]);

        if($validator->fails()){
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        $tag = AgencyTag::with(['brand','branch'])->findOrFail($request->id);

        return response()->json([
            'status'=>'success',
            'data'=>$tag
        ],200);

    }

}
