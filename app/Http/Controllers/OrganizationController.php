<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    private function organizationsFilter(Request $request)
    {
        $filters = [];
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $filters['name'] = $_POST['name'];
        }

        if (isset($_POST['phone']) && !empty($_POST['phone'])) {
            $filters['phone'] = $_POST['phone'];
        }

        if (isset($_POST['billing_city']) && !empty($_POST['billing_city'])) {
            $filters['billing_city_id'] = $_POST['billing_city'];
        }

        if (isset($_POST['billing_country']) && !empty($_POST['billing_country'])) {
            $filters['billing_country'] = $_POST['billing_country'];
        }

        if (isset($_POST['billing_street']) && !empty($_POST['billing_street'])) {
            $filters['billing_street'] = $_POST['billing_street'];
        }

        if (isset($_POST['billing_state']) && !empty($_POST['billing_state'])) {
            $filters['billing_state'] = $_POST['billing_state'];
        }

        if (isset($_POST['perPage']) && !empty($_POST['perPage'])) {
            $filters['perPage'] = $_POST['perPage'];
        }

        if (isset($_POST['page']) && !empty($_POST['page'])) {
            $filters['page'] = $_POST['page'];
        }

        if (isset($_POST['search']) && !empty($_POST['search'])) {
            $filters['search'] = $_POST['search'];
        }

        return $filters;
    }

    public function getorganization(Request $request)
    {
        if (!auth()->user()->can('manage organization')) {
            return response()->json(['error' => 'Permission Denied.'], 403);
        }

        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        $query = User::select(['users.*'])
            ->join('organizations', 'organizations.user_id', '=', 'users.id')
            ->where('users.type', 'organization');

        $filters = $this->organizationsFilter($request);
        foreach ($filters as $column => $value) {
            if ($column === 'billing_street') {
                $query->where('organizations.billing_street', $value);
            } elseif ($column === 'billing_city') {
                $query->where('organizations.billing_city', $value);
            } elseif ($column === 'billing_state') {
                $query->where('organizations.billing_state', $value);
            } elseif ($column === 'billing_country') {
                $query->where('organizations.billing_country', $value);
            } elseif ($column === 'phone') {
                $query->where('organizations.phone', $value);
            } elseif ($column === 'name') {
                $query->whereDate('users.name', $value);
            }
        }


        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('users.name', 'like', "%$search%")
                    ->orWhere('organizations.billing_street', 'like', "%$search%")
                    ->orWhere('organizations.billing_city', 'like', "%$search%")
                    ->orWhere('organizations.billing_state', 'like', "%$search%")
                    ->orWhere('organizations.billing_country', 'like', "%$search%");
            });
        }

        $organizations = $query
        ->orderBy('id', 'ASC')
        ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $organizations->items(),
            'current_page' => $organizations->currentPage(),
            'last_page' => $organizations->lastPage(),
            'total_records' => $organizations->total(),
            'perPage' => $organizations->perPage()
        ], 200);
    }

    public function organizationCreate(Request $request)
    {
        if (!auth()->user()->can('create organization')) {
            return response()->json(['error' => 'Permission Denied.'], 403);
        }
        $validator = Validator::make($request->all(), [
            'organization_name' => 'required',
            'organization_type' => 'required',
            'organization_email' => 'required|email|unique:users,email',
            'organization_phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->organization_name,
            'type' => 'organization',
            'email' => $request->organization_email,
            'password' => Hash::make('123456789'),
            'is_active' => true,
            'lang' => 'en',
            'mode' => 'light',
            'created_by' => auth()->id(),
        ]);

        $Organization =  Organization::create([
            'type' => $request->organization_type,
            'phone' =>  $request->organization_phone,
            'website' => $request->organization_website,
            'linkedin' => $request->organization_linkedin,
            'facebook' => $request->organization_facebook,
            'twitter' => $request->organization_twitter,
            'billing_street' => $request->organization_billing_street,
            'contactname' => $request->contactname,
            'contactemail' => $request->contactemail,
            'contactphone' => $request->contactphone,
            'contactjobroll' => $request->contactjobroll,
            'billing_country' => $request->organization_billing_country,
            'description' => $request->organization_description,
        ]);

        $Organization->user_id = $user->id;
        $Organization->save();

        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Organization Created',
                'message' => 'Organization created successfully'
            ]),
            'module_id' => $user->id,
            'module_type' => 'organization',
            'notification_type' => 'Organization Created'
        ];
        addLogActivity($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Organization created successfully!.',
        ]);
    }

    public function organizationupdate(Request $request)
    {

     $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'id' => 'required|integer|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $id=$request->user_id;
        if (!auth()->user()->can('create organization')) {
            return response()->json(['error' => 'Permission Denied.'], 403);
        }

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'organization_name' => 'required',
            'organization_type' => 'required',
            'organization_email' => "required|email|unique:users,email,$id",
            'organization_phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }


        $user->name = $request->organization_name;
        $user->email =  $request->organization_email;
        $user->save();

         Organization::where('user_id', $user->id)->update([
            'type' => $request->organization_type,
            'phone' =>  $request->organization_phone,
            'website' => $request->organization_website,
            'linkedin' => $request->organization_linkedin,
            'facebook' => $request->organization_facebook,
            'twitter' => $request->organization_twitter,
            'billing_street' => $request->organization_billing_street,
            'contactname' => $request->contactname,
            'contactemail' => $request->contactemail,
            'contactphone' => $request->contactphone,
            'contactjobroll' => $request->contactjobroll,
            'billing_country' => $request->organization_billing_country,
            'description' => $request->organization_description,
        ]);

        //Log
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => $user->name. ' Organization Updated',
                'message' => $user->name. ' Organization updated successfully'
            ]),
            'module_id' => $user->id,
            'module_type' => 'organization',
            'notification_type' => 'Organization Updated'
        ];
        addLogActivity($data);

        return response()->json([
            'status' => 'success',
            'message' => 'organization updated successfully!',
        ]);
    }

    public function organizationDelete(Request $request)
    {
        if (!auth()->user()->can('delete organization')) {
            return response()->json(['error' => 'Permission Denied.'], 403);
        }

          $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'id' => 'required|integer|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $id=$request->user_id;

        $user = User::findOrFail($id);
            //Log
        $data = [
            'type' => 'warning',
            'note' => json_encode([
                'title' => $user->name .' Organization deleted',
                'message' =>  $user->name .' Organization deleted',
            ]),
            'module_id' => $user->id,
            'module_type' => 'organization',
            'notification_type' => 'Organization Updated'
        ];
        addLogActivity($data);
        $user->delete();

        $organization = Organization::where('user_id', $id)->first();
        if ($organization) {
            $organization->delete();
        }



        return response()->json(['status' => 'success', 'message' => 'Organization successfully deleted.']);
    }

    public function organizationshow(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'id' => 'required|integer|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $organization = Organization::where('id', $request->id)->with('user')->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $organization,
        ]);
    }
}
