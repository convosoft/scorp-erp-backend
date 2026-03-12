<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\CompanyPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyPermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
public function index(Request $request)
{
    $validator = Validator::make($request->all(), [
        'role' => 'required|in:1,2'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    $type = $request->role == 1 ? 'Project Manager' : 'Project Director';

    $employees = User::where('type', $type)
        ->orderBy('name', 'ASC')
        ->get();

    $companies = User::where('type', 'company')
        ->orderBy('name', 'ASC')
        ->get();

    $permissions = CompanyPermission::get();

    $permission_arr = [];

    foreach ($permissions as $per) {
        $permission_arr[$per->user_id][$per->permitted_company_id] = $per->active;
    }

    return response()->json([
        'status' => 'success',
        'employees' => $employees,
        'companies' => $companies,
        'permissions' => $permission_arr
    ]);
}



public function updatePermission(Request $request)
{
    $validator = Validator::make($request->all(), [
        'permissions' => 'required|array|min:1',
        'permissions.*.user_id' => 'required|integer|exists:users,id',
        'permissions.*.company_id' => 'required|integer|exists:users,id',
        'permissions.*.active' => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }



    foreach ($request->permissions as $perm) {

    dd($perm);

        $company_per = CompanyPermission::where([
            'user_id' => $perm['user_id'],
            'permitted_company_id' => $perm['company_id']
        ])->first();

        if (!$company_per) {

            CompanyPermission::create([
                'user_id' => $perm['user_id'],
                'permitted_company_id' => $perm['company_id'],
                'active' => $perm['active'],
                'created_by' => auth()->id()
            ]);

        } else {

            $company_per->update([
                'active' => $perm['active']
            ]);
        }
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Permissions updated successfully.'
    ]);
}

    public function company_permission_updated(Request $request)
    {
        //dd($request->input());

        //dd(\Auth::user()->id);
        //Project Director or Manager
        $user_id = $request->company_for;

        //Brand
        $permitted_company = $request->company_permission;
        $is_active = $_POST['active'];

        $company_per = CompanyPermission::where(['user_id' =>  $user_id, 'permitted_company_id' => $permitted_company])->first();

        if (!$company_per) {
            $new_permission = new CompanyPermission();
            $new_permission->user_id = $user_id;
            $new_permission->permitted_company_id = $permitted_company;
            $new_permission->active = $is_active;
            $new_permission->created_by = \Auth::user()->id;
            $new_permission->save();
        } else {
            $company_per->user_id = $user_id;
            $company_per->permitted_company_id = $permitted_company;
            $company_per->active = $is_active;
            $company_per->created_by = \Auth::user()->id;
            $company_per->save();
        }




        // Step 1: Check whether it is adding or removing
        if ($is_active == 'true') {
            $user = User::findOrFail($user_id);

            if ($user->type == 'Project Director') {
                User::where('id', $permitted_company)->update(['project_director_id' => $user_id]);
            } else {
                User::where('id', $permitted_company)->update(['project_manager_id' => $user_id]);
            }
        } else {
            $user = User::findOrFail($user_id);

            if ($user->type == 'Project Director') {
                User::where('id', $permitted_company)->update(['project_director_id' => null]);
            } else {
                User::where('id', $permitted_company)->update(['project_manager_id' => null]);
            }
        }



        return json_encode([
            'status' => 'success'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
