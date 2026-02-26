<?php

namespace App\Http\Controllers;

use http\Client;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use App\Models\Stage;
use App\Models\Invoice;
use App\Models\Utility;
use App\Models\Contract;
use App\Models\ClientDeal;
use App\Models\Estimation;
use App\Models\University;
use App\Models\CustomField;
use Illuminate\Http\Request;
use App\Models\ClientPermission;
use App\Models\HistoryRequest;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware(
            [
                'auth',
            ]
        );
    }

    private function companyEmployees($id)
    {
        $users = DB::table('users as u')
            ->select('u.id', 'u.name')
            ->join('roles as r', 'u.type', '=', 'r.name')
            ->join('role_has_permissions as rp', 'r.id', '=', 'rp.role_id')
            ->join('permissions as p', 'rp.permission_id', '=', 'p.id')
            ->where('u.created_by', '=', $id)
            ->where('p.name', '=', 'create lead')
            ->groupBy('u.id', 'u.name')
            ->get()
            ->pluck('name', 'id')
            ->toArray();

        return $users;
    }

    public function getClients(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage client') && Auth::user()->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate pagination and input
        $validator = Validator::make($request->all(), [
            'perPage'       => 'nullable|integer|min:1',
            'page'          => 'nullable|integer|min:1',
            'name'          => 'nullable|string',
            'email'         => 'nullable|string',
            'search'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Pagination defaults
        $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
        $page    = $request->input('page', 1);

        // Build query
        $query = User::where('users.type', 'client')
            ->withCount('clientDeals')
                ->addSelect([
                    'client_applications_count' => \App\Models\Deal::selectRaw('COUNT(deal_applications.id)')
                        ->join('deal_applications', 'deal_applications.deal_id', '=', 'deals.id')
                        ->join('client_deals', 'client_deals.deal_id', '=', 'deals.id')
                        ->whereColumn('client_deals.client_id', 'users.id')
                ]);

        // Filters
        if ($request->filled('name')) {
            $query->where('users.name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('email')) {
            $query->where('users.email', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('search')) {
            $query->where(function ($subQuery) use ($request) {
                $subQuery->where('users.name', 'like', '%' . $request->search . '%')
                    ->orWhere('users.passport_number', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->fetcttype == '') {
                $query->where('users.blocked_status','=','0');
            }

         if ($request->fetcttype == 'blocked') {
                $query->where('users.blocked_status','=','1');
                 $query->where('users.unblock_status','!=','1');
            }
        if ($request->fetcttype == 'active') {
                $query->where('users.blocked_status','=','0');
            }

         if ($request->fetcttype == 'unblockedrequest') {
                $query->where('users.unblock_status','=','1');
            }


        // Get paginated result
        $paginated = $query->orderBy('users.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status'        => 'success',
            'data'          => $paginated->items(),
            'current_page'  => $paginated->currentPage(),
            'last_page'     => $paginated->lastPage(),
            'total_records' => $paginated->total(),
            'per_page'      => $paginated->perPage(),
        ], 200);
    }


    public function create(Request $request)
    {

        if (\Auth::user()->can('create client')) {
            if ($request->ajax) {
                return view('clients.createAjax');
            } else {
                $customFields = CustomField::where('module', '=', 'client')->get();

                return view('clients.create', compact('customFields'));
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create client')) {
            $user      = \Auth::user();
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users',
                    'password' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                if ($request->ajax) {
                    return response()->json(['error' => $messages->first()], 401);
                } else {
                    return redirect()->back()->with('error', $messages->first());
                }
            }
            $objCustomer    = \Auth::user();
            $creator        = User::find($objCustomer->creatorId());
            $total_client = User::where('type', 'client')->count();
            // dd($total_client);
            $plan           = Plan::find($creator->plan);
            // if($total_client < $plan->max_clients || $plan->max_clients == -1)
            // {
            $role = Role::findByName('client');
            $client = User::create(
                [
                    'name' => $request->name,
                    'email' => $request->email,
                    'job_title' => $request->job_title,
                    'password' => Hash::make($request->password),
                    'type' => 'client',
                    'lang' => Utility::getValByName('default_language'),
                    'created_by' => $user->creatorId(),
                ]
            );

            $client->passport_number = $request->passport_number;
            $client->save();


            //Send Email

            $role_r = Role::findByName('client');
            $client->assignRole($role_r);

            $client->password = $request->password;
            $clientArr = [
                'client_name' => $client->name,
                'client_email' => $client->email,
                'client_password' =>  $client->password,
            ];
            $resp = Utility::sendEmailTemplate('new_client', [$client->email], $clientArr);


            return redirect()->route('clients.index')->with('success', __('Client successfully added.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));

            // }


            // else
            // {
            //     return redirect()->back()->with('error', __('Your user limit is over, Please upgrade plan.'));
            // }

        } else {
            if ($request->ajax) {
                return response()->json(['error' => __('Permission Denied.')], 401);
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        }
    }

    public function show(User $client)
    {
        $usr = Auth::user();
        if (!empty($client) && $usr->id == $client->creatorId() && $client->id != $usr->id && $client->type == 'client') {
            // For Estimations
            $estimations = $client->clientEstimations()->orderByDesc('id')->get();
            $curr_month  = $client->clientEstimations()->whereMonth('issue_date', '=', date('m'))->get();
            $curr_week   = $client->clientEstimations()->whereBetween(
                'issue_date',
                [
                    \Carbon\Carbon::now()->startOfWeek(),
                    \Carbon\Carbon::now()->endOfWeek(),
                ]
            )->get();
            $last_30days = $client->clientEstimations()->whereDate('issue_date', '>', \Carbon\Carbon::now()->subDays(30))->get();
            // Estimation Summary
            $cnt_estimation                = [];
            $cnt_estimation['total']       = Estimation::getEstimationSummary($estimations);
            $cnt_estimation['this_month']  = Estimation::getEstimationSummary($curr_month);
            $cnt_estimation['this_week']   = Estimation::getEstimationSummary($curr_week);
            $cnt_estimation['last_30days'] = Estimation::getEstimationSummary($last_30days);

            $cnt_estimation['cnt_total']       = $estimations->count();
            $cnt_estimation['cnt_this_month']  = $curr_month->count();
            $cnt_estimation['cnt_this_week']   = $curr_week->count();
            $cnt_estimation['cnt_last_30days'] = $last_30days->count();

            // For Contracts
            $contracts   = $client->clientContracts()->orderByDesc('id')->get();
            $curr_month  = $client->clientContracts()->whereMonth('start_date', '=', date('m'))->get();
            $curr_week   = $client->clientContracts()->whereBetween(
                'start_date',
                [
                    \Carbon\Carbon::now()->startOfWeek(),
                    \Carbon\Carbon::now()->endOfWeek(),
                ]
            )->get();
            $last_30days = $client->clientContracts()->whereDate('start_date', '>', \Carbon\Carbon::now()->subDays(30))->get();

            // Contracts Summary
            $cnt_contract                = [];
            $cnt_contract['total']       = Contract::getContractSummary($contracts);
            $cnt_contract['this_month']  = Contract::getContractSummary($curr_month);
            $cnt_contract['this_week']   = Contract::getContractSummary($curr_week);
            $cnt_contract['last_30days'] = Contract::getContractSummary($last_30days);

            $cnt_contract['cnt_total']       = $contracts->count();
            $cnt_contract['cnt_this_month']  = $curr_month->count();
            $cnt_contract['cnt_this_week']   = $curr_week->count();
            $cnt_contract['cnt_last_30days'] = $last_30days->count();

            return view('clients.show', compact('client', 'estimations', 'cnt_estimation', 'contracts', 'cnt_contract'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function edit(User $client)
    {
        if (\Auth::user()->can('edit client')) {
            $user = \Auth::user();
            // if($client->created_by == $user->creatorId() || \Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team')
            // {
            $client->customField = CustomField::getData($client, 'client');
            $customFields        = CustomField::where('module', '=', 'client')->get();

            return view('clients.edit', compact('client', 'customFields'));
            // }
            // else
            // {
            //     return response()->json(['error' => __('Invalid Client.')], 401);
            // }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function updateClient(Request $request)
    {
            // Check permission
            if (!Auth::user()->can('edit client')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission denied.',
                ], 403);
            }

        // Validate request input
        $validator = Validator::make($request->all(), [
            'id'              => 'required|exists:users,id',
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $request->id,
            'password'        => 'nullable|string|min:6',
            'passport_number' => 'nullable|string|max:255',
            'customField'     => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }


        // Fetch client
        $client = User::where('id', $request->id)->first();


        // Store original data
        $originalData = $client->toArray();

        // Update values
        $client->name = $request->name;
        $client->email = $request->email;
        $client->passport_number = $request->passport_number ?? $client->passport_number;

        if (!empty($request->password)) {
            $client->password = Hash::make($request->password);
        }

        $client->save();

        // Save custom fields if any
        if (!empty($request->customField)) {
            CustomField::saveData($client, $request->customField);
        }

        // Compare and log only changed fields
        $changes = [];
        foreach ($originalData as $field => $oldValue) {
            if (array_key_exists($field, $client->getAttributes()) && $client->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $client->$field
                ];
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' =>  $client->name. '  contact Updated',
                    'message' => 'Fields updated successfully',
                    'changes' => $changes
                ]),
                'module_id' => $client->id,
                'module_type' => 'client',
                'notification_type' => 'Client Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Client updated successfully.',
            'data' => $client,
        ], 200);
    }


    public function deleteClient(Request $request)
    {

     // Validate request input
        $validator = Validator::make($request->all(), [
            'id'              => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        $client = User::where('id', $request->id)->first();

        $user = \Auth::user();
        if ($client->created_by == $user->creatorId()) {
            $estimation = Estimation::where('client_id', '=', $request->id)->first();
            if (empty($estimation)) {
                /*  ClientDeal::where('client_id', '=', $client->id)->delete();
                    ClientPermission::where('client_id', '=', $client->id)->delete();*/

                    addLogActivity([
                'type' => 'warning',
                'note' => json_encode([
                    'title' => $client->name. ' contact deleted',
                    'message' =>  $client->name. '  contact deleted',
                ]),
                'module_id' => $client->id,
                'module_type' => 'client',
                'notification_type' => 'Client Updated'
            ]);
                $client->delete();
                return redirect()->back()->with('success', __('Client Deleted Successfully!'));
            } else {
                return redirect()->back()->with('error', __('This client has assigned some estimation.'));
            }
        } else {
            return redirect()->back()->with('error', __('Invalid Client.'));
        }
    }

    public function clientPassword($id)
    {
        $eId        = \Crypt::decrypt($id);
        $user = User::find($eId);
        $client = User::where('created_by', '=', $user->creatorId())->where('type', '=', 'client')->first();


        return view('clients.reset', compact('user', 'client'));
    }

    public function clientPasswordReset(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'password' => 'required|confirmed|same:password_confirmation',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }


        $user                 = User::where('id', $id)->first();
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return redirect()->route('clients.index')->with(
            'success',
            'Client Password successfully updated.'
        );
    }

    public function clientDetail(Request $request) {
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
            }

            $client = User::where('id', $request->id)->first();
          $clienthistory =  HistoryRequest::where('student_id', $request->id)->orderBy('created_at', 'desc')->get();

            if (!$client) {
                return response()->json(['status' => 'error', 'message' => 'Client not found.'], 404);
            }



            $lead = Lead::select('leads.*')
                ->join('deals as d', 'leads.is_converted', '=', 'd.id')
                ->join('client_deals as cd', 'cd.deal_id', '=', 'd.id')
                ->where('cd.client_id', $request->id)
                ->first();

            $deals = Deal::join('client_deals', 'client_deals.deal_id', 'deals.id')
                ->where('client_deals.client_id', $request->id)
                ->select('deals.*')
                ->get();

            $applications = Deal::join('deal_applications', 'deal_applications.deal_id', 'deals.id')
                ->join('client_deals', 'client_deals.deal_id', 'deals.id')
                ->where('client_deals.client_id', $request->id)
                ->select('deal_applications.*')
                ->get();

            $stages = Stage::pluck('name', 'id');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'client' => $client,
                    'lead' => $lead,
                    'deals' => $deals,
                    'applications' => $applications,
                    'clienthistory' => $clienthistory,
                    'stages' => $stages
                ]
            ]);
    }


    public function deleteBulkContacts(Request $request)
    {

        if ($request->ids != null) {
            User::whereIn('id', explode(',', $request->ids))->where('type', '=', 'client')->delete();
            return redirect()->route('clients.index')->with('success', 'Clients deleted successfully');
        } else {
            return redirect()->route('clients.index')->with('error', 'Atleast select 1 client.');
        }
    }

    public function updateBulkContact(Request $request)
    {

        $ids = explode(',', $request->contacts_ids);
        // dd($ids);
        if (isset($request->name)) {

            User::whereIn('id', $ids)->where('type', '=', 'client')->update(['name' => $request->name]);
            return redirect()->route('clients.index')->with('success', 'Contacts updated successfully');
        } elseif (isset($request->email)) {

            User::whereIn('id', $ids)->where('type', '=', 'client')->update(['email' => $request->email]);
            return redirect()->route('clients.index')->with('success', 'Contacts updated successfully');
        } elseif (isset($request->passport_number)) {

            User::whereIn('id', $ids)->where('type', '=', 'client')->update(['passport_number' => $request->passport_number]);
            return redirect()->route('clients.index')->with('success', 'Contacts updated successfully');
        } elseif (isset($request->password)) {
            $password = Hash::make($request->password);
            User::whereIn('id', $ids)->where('type', '=', 'client')->update(['password' => $password]);
            return redirect()->route('clients.index')->with('success', 'Contacts updated successfully');
        }
    }

    public function BlockRequest()
    {
        if (\Auth::user()->can('manage client') || \Auth::user()->type == 'super admin') {
            $user    = \Auth::user();

            $start = 0;
            $num_results_on_page = env("RESULTS_ON_PAGE", 50);
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                $num_of_result_per_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
                $start = ($page - 1) * $num_results_on_page;
            } else {
                $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
            }


            $client_query = User::select('users.*')->where('users.type', 'client')->where('blocked_status', '1');
            if (!empty($_GET['name'])) {
                $client_query->where('users.name', 'like', '%' . $_GET['name'] . '%');
            }

            if (!empty($_GET['email'])) {
                $client_query->where('users.email', 'like', '%' . $_GET['email'] . '%');
            }

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $g_search = $_GET['search'];
                $client_query->where(function ($query) use ($g_search) {
                    $query->where('users.name', 'like', '%' . $g_search . '%')
                        ->orWhere('users.passport_number', 'like', '%' . $g_search . '%');
                });
            }


            $total_records = $client_query->count();

            // Paginate the results
            $clients = $client_query
                ->orderBy('users.created_at', 'DESC')
                ->skip($start)
                ->limit($num_results_on_page)
                ->get();


            // $clients = User::where('created_by', '=', $user->creatorId())->where('type', '=', 'client')->skip($start)->limit($num_results_on_page)->get();

            if (isset($_GET['ajaxCall']) && $_GET['ajaxCall'] == 'true') {
                $html = view('blocking_system.block_clients_list_ajax', compact('clients', 'total_records'))->render();
                $pagination_html = view('layouts.pagination', [
                    'total_pages' => $total_records,
                    'num_results_on_page' =>  $num_results_on_page
                ])->render();
                return json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'pagination_html' => $pagination_html
                ]);
            }

            return view('blocking_system.block_index', compact('clients', 'total_records'));
        } else {

            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function BlockclientDetail($id)
    {
        $client = User::findOrFail($id);
        $deal = Deal::join('client_deals', 'client_deals.deal_id', 'deals.id')->where('client_deals.client_id', $id)->first();

        $lead = Lead::select('leads.*')
            ->join('deals as d', 'leads.is_converted', '=', 'd.id')
            ->join('client_deals as cd', 'cd.deal_id', '=', 'd.id')
            ->where('cd.client_id', $id)
            ->first();

        $organizations = User::get()->pluck('name', 'id')->toArray();

        $deals = Deal::join('client_deals', 'client_deals.deal_id', 'deals.id')->where('client_deals.client_id', $id)->get();
        $applications = Deal::select(['deal_applications.*'])->join('deal_applications', 'deal_applications.deal_id', 'deals.id')->join('client_deals', 'client_deals.deal_id', 'deals.id')->where('client_deals.client_id', $id)->get();
        $stages = Stage::get()->pluck('name', 'id')->toArray();
        $universities = University::get()->pluck('name', 'id')->toArray();

        $html = view('blocking_system.block_clientDetail', compact('client', 'deal', 'lead', 'organizations', 'deals', 'applications', 'stages', 'universities'))->render();
        return json_encode([
            'status' => 'success',
            'html' => $html
        ]);
    }



    public function UnblockRequest()
    {
        if (\Auth::user()->can('manage client') || \Auth::user()->type == 'super admin') {
            $user    = \Auth::user();

            $start = 0;
            $num_results_on_page = env("RESULTS_ON_PAGE", 50);
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                $num_of_result_per_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
                $start = ($page - 1) * $num_results_on_page;
            } else {
                $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
            }


            $client_query = User::select('users.*')->where('users.type', 'client')->where('unblock_status', '1');
            if (!empty($_GET['name'])) {
                $client_query->where('users.name', 'like', '%' . $_GET['name'] . '%');
            }

            if (!empty($_GET['email'])) {
                $client_query->where('users.email', 'like', '%' . $_GET['email'] . '%');
            }

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $g_search = $_GET['search'];
                $client_query->where(function ($query) use ($g_search) {
                    $query->where('users.name', 'like', '%' . $g_search . '%')
                        ->orWhere('users.passport_number', 'like', '%' . $g_search . '%');
                });
            }


            $total_records = $client_query->count();

            // Paginate the results
            $clients = $client_query
                ->orderBy('users.created_at', 'DESC')
                ->skip($start)
                ->limit($num_results_on_page)
                ->get();


            // $clients = User::where('created_by', '=', $user->creatorId())->where('type', '=', 'client')->skip($start)->limit($num_results_on_page)->get();

            if (isset($_GET['ajaxCall']) && $_GET['ajaxCall'] == 'true') {
                $html = view('blocking_system.unblock_clients_list_ajax', compact('clients', 'total_records'))->render();
                $pagination_html = view('layouts.pagination', [
                    'total_pages' => $total_records,
                    'num_results_on_page' =>  $num_results_on_page
                ])->render();
                return json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'pagination_html' => $pagination_html
                ]);
            }

            return view('blocking_system.unblock_index', compact('clients', 'total_records'));
        } else {

            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function UnblockclientDetail($id)
    {
        $client = User::findOrFail($id);
        $deal = Deal::join('client_deals', 'client_deals.deal_id', 'deals.id')->where('client_deals.client_id', $id)->first();

        $lead = Lead::select('leads.*')
            ->join('deals as d', 'leads.is_converted', '=', 'd.id')
            ->join('client_deals as cd', 'cd.deal_id', '=', 'd.id')
            ->where('cd.client_id', $id)
            ->first();

        $organizations = User::get()->pluck('name', 'id')->toArray();

        $deals = Deal::join('client_deals', 'client_deals.deal_id', 'deals.id')->where('client_deals.client_id', $id)->get();
        $applications = Deal::select(['deal_applications.*'])->join('deal_applications', 'deal_applications.deal_id', 'deals.id')->join('client_deals', 'client_deals.deal_id', 'deals.id')->where('client_deals.client_id', $id)->get();
        $stages = Stage::get()->pluck('name', 'id')->toArray();
        $universities = University::get()->pluck('name', 'id')->toArray();

        $html = view('blocking_system.unblock_clientDetail', compact('client', 'deal', 'lead', 'organizations', 'deals', 'applications', 'stages', 'universities'))->render();
        return json_encode([
            'status' => 'success',
            'html' => $html
        ]);
    }







    public function HistoryRequest()
    {
        if (\Auth::user()->can('manage client') || \Auth::user()->type == 'super admin') {
            $user    = \Auth::user();

            $start = 0;
            $num_results_on_page = env("RESULTS_ON_PAGE", 50);
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                $num_of_result_per_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
                $start = ($page - 1) * $num_results_on_page;
            } else {
                $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
            }

            // $HistoryRequest = new \App\Models\HistoryRequest();

            $HistoryRequest = HistoryRequest::select(
                'history_requests.*',
                'Student.name as StudentName',
                'Student.email as StudentEmail',
                'Student.id as StudentId',
                'history_requests.status as RequestsStatus',
            )
                ->join('users as Student', 'Student.id', '=', 'history_requests.student_id')
                ->join('users', 'users.id', '=', 'history_requests.student_id')
                ->where('users.type', 'client')
                ->groupBy('Student.id');
            // dd( $HistoryRequest->get());

            if (!empty($_GET['name'])) {
                $HistoryRequest->where('users.name', 'like', '%' . $_GET['name'] . '%');
            }

            if (!empty($_GET['email'])) {
                $HistoryRequest->where('users.email', 'like', '%' . $_GET['email'] . '%');
            }

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $g_search = $_GET['search'];
                $HistoryRequest->where(function ($query) use ($g_search) {
                    $query->where('users.name', 'like', '%' . $g_search . '%')
                        ->orWhere('users.passport_number', 'like', '%' . $g_search . '%');
                });
            }

            $total_records = $HistoryRequest->count();


            $total_records = $HistoryRequest->count();

            // Paginate the results
            $clients = $HistoryRequest
                ->orderBy('users.created_at', 'DESC')
                ->skip($start)
                ->limit($num_results_on_page)
                ->get();


            // $clients = User::where('created_by', '=', $user->creatorId())->where('type', '=', 'client')->skip($start)->limit($num_results_on_page)->get();

            if (isset($_GET['ajaxCall']) && $_GET['ajaxCall'] == 'true') {
                $html = view('blocking_system.history_clients_list_ajax', compact('clients', 'total_records'))->render();
                $pagination_html = view('layouts.pagination', [
                    'total_pages' => $total_records,
                    'num_results_on_page' =>  $num_results_on_page
                ])->render();
                return json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'pagination_html' => $pagination_html
                ]);
            }

            return view('blocking_system.history_index', compact('clients', 'total_records'));
        } else {

            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function HistoryclientDetail($id)
    {
        $HistoryRequest = HistoryRequest::where('student_id', $id)->first();
        $html = view('blocking_system.history_clientDetail', compact('HistoryRequest'))->render();
        return json_encode([
            'status' => 'success',
            'html' => $html
        ]);
    }
        public function blockClient(Request $request){
        if (\Auth::user()->can('edit deal')) {
            $validator = \Validator::make($request->all(), [
                'block_attachments' => 'required|file|mimes:png,jpg,pdf|max:1024', // Allow only jpg and pdf files with max size 1MB
                'id' => 'required|exists:users,id',
                'deal_id' => 'required|exists:deals,id',
                'blocked_reason' => 'required',
            ]);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                 return response()->json([
                    'status' => 'error',
                    'message' =>  $messages
                ]);
            }
            $User = User::findOrFail($request->id);
            if ($request->hasFile('block_attachments')) {
                $blockAttachmentName = time() . '.' . $request->file('block_attachments')->extension();
                $request->file('block_attachments')->move(public_path('block_attachments'), $blockAttachmentName);
                $User->block_attachments = $blockAttachmentName; // Save the file path if needed
            }
            // HistoryRequest
            $HistoryRequest = new \App\Models\HistoryRequest();
            $HistoryRequest->type = 'Blocked';
            $HistoryRequest->status = '1';
            $HistoryRequest->start_date = date('Y-m-d');
            $HistoryRequest->time = date('H:i:s');
            $HistoryRequest->note = json_encode([
                    'title' => 'Contact Updated',
                    'message' => 'Contact Blocked Request'
                ]);
            $HistoryRequest->module_type = 'Blocked';
            $HistoryRequest->student_id = $request->id;
            $HistoryRequest->created_by = \Auth::user()->id;
            $HistoryRequest->reason = $request->blocked_reason ?? 'No reason provided';
            $HistoryRequest->attachments = $blockAttachmentName ?? null;
            $HistoryRequest->save();

            $User->blocked_reason=$request->blocked_reason;
            $User->blocked_status= '1';
            $User->blocked_by= \Auth::id();
            $User->save();

            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Client Blocked Request',
                    'message' => 'Client Blocked Request'
                ]),
                'module_id' => $User->id,
                'module_type' => 'client',
                'notification_type' => 'Client Blocked Request Submit Successfully'
            ];
            addLogActivity($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Blocked User Successfully',
                'id' => $request->deal_id,
            ]);
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

       public function unBlockClient(Request $request){
        if (\Auth::user()->can('edit deal')) {
            $validator = \Validator::make($request->all(), [
                'block_attachments' => 'required|file|mimes:png,jpg,pdf|max:1024', // Allow only jpg and pdf files with max size 1MB
                'id' => 'required|exists:users,id',
                'blocked_reason' => 'required',
            ]);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                 return response()->json([
                    'status' => 'error',
                    'message' =>  $messages
                ]);
            }
            $User = User::findOrFail($request->id);
            if ($request->hasFile('block_attachments')) {
                $blockAttachmentName = time() . '.' . $request->file('block_attachments')->extension();
                $request->file('block_attachments')->move(public_path('unblock_attachments'), $blockAttachmentName);
                $User->unblock_attachments = $blockAttachmentName; // Save the file path if needed
            }
            $User->unblock_reason=$request->blocked_reason;
            $User->unblock_status= '1';



            $HistoryRequest = new \App\Models\HistoryRequest();
            $HistoryRequest->type = 'Unblocked';
            $HistoryRequest->status = '2';
            $HistoryRequest->start_date = date('Y-m-d');
            $HistoryRequest->time = date('H:i:s');
            $HistoryRequest->note = json_encode([
            'title' => 'Contact Updated',
            'message' => 'Contact Unblocked Request'
            ]);
            $HistoryRequest->module_type = 'Unblocked';
            $HistoryRequest->student_id = $request->id;
            $HistoryRequest->created_by = \Auth::user()->id;
            $HistoryRequest->reason = $request->blocked_reason ?? 'No reason provided';
            $HistoryRequest->attachments = $blockAttachmentName ?? null;
            $HistoryRequest->save();





            $User->unblock_by= \Auth::id();
            $User->save();

            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Client Unblock Request',
                    'message' => 'Client Unblock Request'
                ]),
                'module_id' => $User->id,
                'module_type' => 'client',
                'notification_type' => 'Client Unblock Request Submit Successfully'
            ];
            addLogActivity($data);

            return json_encode([
                'status' => 'success',
                'message' => 'Unlock Request Submit Successfully',
                'deal_id' => $User->id,
            ]);
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }


        public function updateUnblockRequestStatus(Request $request){

         $validator = \Validator::make($request->all(), [
                'block_attachments' => 'required|file|mimes:png,jpg,pdf|max:1024', // Allow only jpg and pdf files with max size 1MB
                'id' => 'required|exists:users,id',
                'blocked_reason' => 'required',
                'admin_action_status' => 'required',
            ]);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                 return response()->json([
                    'status' => 'error',
                    'message' =>  $messages
                ]);
            }
        if (\Auth::user()->can('edit deal')) {
            if($request->admin_action_status != null){
                 if($request->admin_action_status == '1'){

                    $User = User::findOrFail($request->id);

                    $HistoryRequest = new \App\Models\HistoryRequest();
                    $HistoryRequest->type = 'Approved';
                    $HistoryRequest->status = '3';
                    $HistoryRequest->start_date = date('Y-m-d');
                    $HistoryRequest->time = date('H:i:s');
                    $HistoryRequest->note = json_encode([
                    'title' => 'Contact Updated',
                    'message' => 'Contact Approved Unblock Request'
                    ]);
                    $HistoryRequest->module_type = 'Approved';
                    $HistoryRequest->student_id = $request->id;
                    $HistoryRequest->created_by = \Auth::user()->id;
                    $HistoryRequest->reason = $request->blocked_reason ?? 'No reason provided';
                    $HistoryRequest->attachments = $blockAttachmentName ?? null;
                    $HistoryRequest->save();

                    $User->blocked_status = '0';
                    $User->unblock_status = '0';

                    $User->save();

                    $data = [
                        'type' => 'info',
                        'note' => json_encode([
                            'title' => 'Client Unblock Request',
                            'message' => 'Client Unblock Request'
                        ]),
                        'module_id' => $User->id,
                        'module_type' => 'client',
                        'notification_type' => 'Client Unblock Request Submit Successfully'
                    ];
                    addLogActivity($data);

                     return response()->json([
                        'status' => 'success',
                        'message' => 'Unlock Request Submit Successfully',
                        'id' => $User->id,
                    ],200);
                 }else{

                    $User = User::findOrFail($request->id);


                    $User = User::findOrFail($request->id);

                    $HistoryRequest = new \App\Models\HistoryRequest();
                    $HistoryRequest->type = 'Rejected';
                    $HistoryRequest->status = '4';
                    $HistoryRequest->start_date = date('Y-m-d');
                    $HistoryRequest->time = date('H:i:s');
                    $HistoryRequest->note = json_encode([
                    'title' => 'Contact Updated',
                    'message' => 'Contact Rejected Unblock Request'
                    ]);
                    $HistoryRequest->module_type = 'Rejected';
                    $HistoryRequest->student_id = $request->id;
                    $HistoryRequest->created_by = \Auth::user()->id;
                    $HistoryRequest->reason = $request->blocked_reason ?? 'No reason provided';
                    $HistoryRequest->attachments = $blockAttachmentName ?? null;
                    $HistoryRequest->save();

                    $data = [
                        'type' => 'info',
                        'note' => json_encode([
                            'title' => 'Rejected Client Unblocked Request',
                            'message' => 'Rejected Client Unblocked Request'
                        ]),
                        'module_id' => $User->id,
                        'module_type' => 'client',
                        'notification_type' => 'Rejected Client Unblocked Request Submit Successfully'
                    ];
                    addLogActivity($data);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Rejected Client Request Successfully',
                        'id' => $request->id,
                    ]);
                 }
            }else{

            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }



}
