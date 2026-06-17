<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmailCampaignController extends Controller
{
    public function createCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_name' => 'required|string|max:255',
            'brand_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'region_id' => 'required|integer|exists:regions,id',
            'recipient_type' => 'required|in:leads,admissions,applications,agents',
            'template_id' => 'nullable|integer',
            'from_email' => 'nullable|email',
            'subject' => 'required|string|max:500',
            'body' => 'required',
            'filters_json' => 'nullable|array',
            'total_recipients' => 'nullable|integer|min:0',
            'status' => 'required|in:draft,pending_approval',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*.id' => 'required|integer',
            'recipient_ids.*.name' => 'required|string',
            'recipient_ids.*.email' => 'required|email',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = EmailCampaign::create([
            'campaign_name'    => $request->campaign_name,
            'brand_id'    => $request->brand_id,
            'branch_id'    => $request->branch_id,
            'region_id'    => $request->region_id,
            'recipient_type'   => $request->recipient_type,
            'template_id'      => $request->template_id,
            'email_sender_id'  => Auth::id(),
            'from_email'       => $request->from_email,
            'subject'          => $request->subject,
            'body'             => $request->body,
            'filters_json'     => $request->filters_json,
            'total_recipients' => $request->total_recipients ?? 0,
            'status'           => $request->status,
            'created_by'       => Auth::id(),
        ]);

        $rows = [];

        foreach ($request->recipient_ids as $recipient) {
            $rows[] = [
                'campaign_id'    => $campaign->id,
                'recipient_type' => $request->recipient_type,
                'recipient_id'   => $recipient['id'],
                'name'           => $recipient['name'],
                'email'          => $recipient['email'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        EmailCampaignRecipient::insert($rows);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => "'{$campaign->campaign_name}' Email Campaign Created",
                'message' => "A new email campaign '{$campaign->campaign_name}' has been created successfully."
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'email_campaign',
            'notification_type' => 'Email Campaign Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign created successfully',
            'data' => $campaign,
        ]);
    }

    public function approveCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:email_campaigns,id',
            'comments' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $campaign = EmailCampaign::find($request->id);

        if ($campaign->status !== 'pending_approval') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending approval campaigns can be approved.'
            ], 422);
        }

        $campaign->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'comments' => $request->comments,
        ]);

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Email Campaign Approved',
                'message' => "Email campaign '{$campaign->campaign_name}' has been approved. Comments: {$request->comments}"
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'email_campaign',
            'notification_type' => 'Email Campaign Approved',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign approved successfully.',
            'data' => $campaign->fresh()
        ]);
    }

    public function rejectCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:email_campaigns,id',
            'comments' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $campaign = EmailCampaign::find($request->id);

        if ($campaign->status !== 'pending_approval') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending approval campaigns can be rejected.'
            ], 422);
        }

        $campaign->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'comments' => $request->comments,
        ]);

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Email Campaign Rejected',
                'message' => "Email campaign '{$campaign->campaign_name}' has been rejected. Comments: {$request->comments}"
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'email_campaign',
            'notification_type' => 'Email Campaign Rejected',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign rejected successfully.',
            'data' => $campaign->fresh()
        ]);
    }
    public function getEmailCampaigns(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',

            'search' => 'nullable|string',
            'campaign_name' => 'nullable|string',

            'recipient_type' => 'nullable|in:leads,admissions,applications,agents',
            'status' => 'nullable|in:draft,pending_approval,approved,rejected,sending,completed',

            'brand_id' => 'nullable|integer',
            'region_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',

            'created_by' => 'nullable|integer',
            'approved_by' => 'nullable|integer',

            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = \Auth::user();
        $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
        $page = $request->input('page', 1);

        $query = EmailCampaign::query();

        // 🔎 SEARCH (name / subject / email)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('campaign_name', 'like', "%$search%")
                    ->orWhere('subject', 'like', "%$search%")
                    ->orWhere('from_email', 'like', "%$search%");
            });
        }

        // Filters
        if ($request->filled('campaign_name')) {
            $query->where('campaign_name', 'like', "%{$request->campaign_name}%");
        }

        if ($request->filled('recipient_type')) {
            $query->where('recipient_type', $request->recipient_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->region_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('approved_by')) {
            $query->where('approved_by', $request->approved_by);
        }

        // Date filters
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // 🔐 ROLE-BASED ACCESS CONTROL
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);

        $userType = \Auth::user()->type;
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // No additional filtering needed
        } elseif ($userType === 'company') {
            $query->where('brand_id', \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $query->whereIn('brand_id', $brand_ids);
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $query->where('region_id', \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Careers Consultant', 'Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
            $query->where('branch_id', \Auth::user()->branch_id);
        } elseif ($userType === 'Agent') {
            $query->where('agent_id', $usr->agent_id);
        } else {
            $query->where('user_id', \Auth::user()->id);
        }


        // Sorting
        $query->orderBy('created_at', 'desc');

        // Pagination
        $campaigns = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $campaigns->items(),

            'current_page' => $campaigns->currentPage(),
            'last_page' => $campaigns->lastPage(),
            'total_records' => $campaigns->total(),
            'per_page' => $campaigns->perPage(),
        ]);
    }

    public function getCampaignDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:email_campaigns,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = EmailCampaign::with('recipients')->find($request->id);

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found.',
            ], 404);
        }

        // 🔐 ROLE-BASED ACCESS CONTROL
        $user = Auth::user();
        $userType = $user->type;
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);

        $allowed = false;
        if (in_array($userType, ['super admin', 'Admin Team']) || $user->can('level 1')) {
            $allowed = true;
        } elseif ($userType === 'company') {
            $allowed = ($campaign->brand_id == $user->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $allowed = in_array($campaign->brand_id, $brand_ids);
        } elseif (($userType === 'Region Manager' || $user->can('level 3')) && !empty($user->region_id)) {
            $allowed = ($campaign->region_id == $user->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Careers Consultant', 'Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || $user->can('level 4') && !empty($user->branch_id)) {
            $allowed = ($campaign->branch_id == $user->branch_id);
        } elseif ($userType === 'Agent') {
            $allowed = ($campaign->created_by == $user->id);
        } else {
            $allowed = ($campaign->created_by == $user->id);
        }

        if (!$allowed) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $campaign,
        ], 200);
    }
}

