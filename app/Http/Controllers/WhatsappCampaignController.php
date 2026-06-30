<?php

namespace App\Http\Controllers;

use App\Models\WhatsappCampaign;
use App\Models\User;
use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappSendingQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WhatsappCampaignController extends Controller
{
    public function createCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_name' => 'required|string|max:255',
            'brand_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'region_id' => 'required|integer|exists:regions,id',
            'recipient_type' => 'required|in:leads,admissions,applications,agents,import',
            'template_id' => 'nullable|integer',
            'from_number' => 'nullable|string|max:50',
            'body' => 'required|string',
            'filters_json' => 'nullable|array',
            'total_recipients' => 'nullable|integer|min:0',
            'status' => 'required|in:draft,pending_approval',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*.id' => 'required|integer',
            'recipient_ids.*.name' => 'required|string',
            'recipient_ids.*.phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = WhatsappCampaign::create([
            'campaign_name'      => $request->campaign_name,
            'brand_id'           => $request->brand_id,
            'branch_id'          => $request->branch_id,
            'region_id'          => $request->region_id,
            'recipient_type'     => $request->recipient_type,
            'template_id'        => $request->template_id,
            'whatsapp_sender_id' => Auth::id(),
            'from_number'        => $request->from_number,
            'body'               => $request->body,
            'filters_json'       => $request->filters_json,
            'total_recipients'   => $request->total_recipients ?? 0,
            'status'             => $request->status,
            'created_by'         => Auth::id(),
        ]);

        $rows = [];
        foreach ($request->recipient_ids as $recipient) {
            $rows[] = [
                'campaign_id'    => $campaign->id,
                'recipient_type' => $request->recipient_type,
                'recipient_id'   => $recipient['id'],
                'name'           => $recipient['name'],
                'phone'          => $recipient['phone'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        WhatsappCampaignRecipient::insert($rows);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => "'{$campaign->campaign_name}' WhatsApp Campaign Created",
                'message' => "A new WhatsApp campaign '{$campaign->campaign_name}' has been created successfully."
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'whatsapp_campaign',
            'notification_type' => 'WhatsApp Campaign Created',
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
            'id' => 'required|integer|exists:whatsapp_campaigns,id',
            'comments' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $campaign = WhatsappCampaign::with('recipients')->find($request->id);
        $senderDetails = User::with(['branch', 'region', 'brand'])->where('id', $campaign->whatsapp_sender_id)->first();

        if ($campaign->status !== 'pending_approval') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending approval campaigns can be approved.'
            ], 422);
        }

        $recipientIds = $campaign->recipients->pluck('recipient_id')->toArray();
        $recipientStages = [];
        $recipientPipelines = [];

        if ($campaign->recipient_type === 'leads') {
            $stagesAndPipelines = \DB::table('leads')
                ->whereIn('id', $recipientIds)
                ->select('id', 'stage_id', 'pipeline_id')
                ->get()
                ->keyBy('id');
            foreach ($stagesAndPipelines as $id => $row) {
                $recipientStages[$id] = $row->stage_id;
                $recipientPipelines[$id] = $row->pipeline_id;
            }
        } elseif ($campaign->recipient_type === 'admissions') {
            $stagesAndPipelines = \DB::table('deals')
                ->whereIn('id', $recipientIds)
                ->select('id', 'stage_id', 'pipeline_id')
                ->get()
                ->keyBy('id');
            foreach ($stagesAndPipelines as $id => $row) {
                $recipientStages[$id] = $row->stage_id;
                $recipientPipelines[$id] = $row->pipeline_id;
            }
        } elseif ($campaign->recipient_type === 'applications') {
            $stagesAndPipelines = \DB::table('deal_applications')
                ->whereIn('id', $recipientIds)
                ->select('id', 'stage_id')
                ->get()
                ->keyBy('id');
            foreach ($stagesAndPipelines as $id => $row) {
                $recipientStages[$id] = $row->stage_id;
            }
        }

        $relatedType = 'lead';
        if ($campaign->recipient_type === 'leads') {
            $relatedType = 'lead';
        } elseif ($campaign->recipient_type === 'admissions') {
            $relatedType = 'admission';
        } elseif ($campaign->recipient_type === 'applications') {
            $relatedType = 'application';
        } elseif ($campaign->recipient_type === 'agents') {
            $relatedType = 'agent';
        } elseif ($campaign->recipient_type === 'import') {
            $relatedType = 'file_import';
        }

        $campaign->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'comments' => $request->comments,
        ]);

        $insertData = [];
        foreach ($campaign->recipients as $recipient) {
            $parsedBody = parseEmailTemplate($campaign->body, $recipient->recipient_id, $campaign->recipient_type, $senderDetails);

            $insertData[] = [
                'campaign_id'  => $campaign->id,
                'phone'        => $recipient->phone,
                'message'      => $parsedBody,
                'created_by'   => Auth::id(),
                'brand_id'     => $campaign->brand_id,
                'from_number'  => $campaign->from_number ?? config('services.twilio.from'),
                'branch_id'    => $campaign->branch_id,
                'region_id'    => $campaign->region_id,
                'sender_id'    => $campaign->whatsapp_sender_id ?? Auth::id(),
                'stage_id'     => $recipientStages[$recipient->recipient_id] ?? null,
                'pipeline_id'  => $recipientPipelines[$recipient->recipient_id] ?? null,
                'template_id'  => $campaign->template_id,
                'related_type' => $relatedType,
                'priority'     => '3',
                'related_id'   => $recipient->recipient_id,
                'is_send'      => '0',
                'status'       => '1',
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        foreach (array_chunk($insertData, 1000) as $chunk) {
            WhatsappSendingQueue::insert($chunk);
        }

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => $campaign->campaign_name . ' WhatsApp Campaign Approved',
                'message' => "WhatsApp campaign '{$campaign->campaign_name}' has been approved and queued for sending. Comments: {$request->comments}"
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'whatsapp_campaign',
            'notification_type' => 'WhatsApp Campaign Approved',
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
            'id' => 'required|integer|exists:whatsapp_campaigns,id',
            'comments' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $campaign = WhatsappCampaign::find($request->id);

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
                'title' => 'WhatsApp Campaign Rejected',
                'message' => "WhatsApp campaign '{$campaign->campaign_name}' has been rejected. Comments: {$request->comments}"
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'whatsapp_campaign',
            'notification_type' => 'WhatsApp Campaign Rejected',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign rejected successfully.',
            'data' => $campaign->fresh()
        ]);
    }

    public function getWhatsappCampaigns(Request $request)
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
            'created_at_from' => 'nullable|date',
            'created_at_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
        $page = $request->input('page', 1);

        $query = WhatsappCampaign::query()
            ->select('whatsapp_campaigns.*')
            ->addSelect([
                'total_queued' => WhatsappSendingQueue::selectRaw('COUNT(*)')
                    ->whereColumn('whatsapp_sending_queues.campaign_id', 'whatsapp_campaigns.id'),
                'total_sent' => WhatsappSendingQueue::selectRaw('COUNT(*)')
                    ->whereColumn('whatsapp_sending_queues.campaign_id', 'whatsapp_campaigns.id')
                    ->where('is_send', '1'),
                'total_pending' => WhatsappSendingQueue::selectRaw('COUNT(*)')
                    ->whereColumn('whatsapp_sending_queues.campaign_id', 'whatsapp_campaigns.id')
                    ->where('is_send', '0'),
                'total_failed' => WhatsappSendingQueue::selectRaw('COUNT(*)')
                    ->whereColumn('whatsapp_sending_queues.campaign_id', 'whatsapp_campaigns.id')
                    ->where('status', '2'),
                'total_delivered' => WhatsappSendingQueue::selectRaw('COUNT(*)')
                    ->whereColumn('whatsapp_sending_queues.campaign_id', 'whatsapp_campaigns.id')
                    ->whereNotNull('delivered_at'),
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('campaign_name', 'like', "%$search%")
                  ->orWhere('body', 'like', "%$search%")
                  ->orWhere('from_number', 'like', "%$search%");
            });
        }

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
        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->created_at_from);
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->created_at_to);
        }

        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);
        $userType = \Auth::user()->type;

        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // Unrestricted
        } elseif ($userType === 'company') {
            $query->where('brand_id', \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $query->whereIn('brand_id', $brand_ids);
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $query->where('region_id', \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Careers Consultant', 'Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
            $query->where('branch_id', \Auth::user()->branch_id);
        } elseif ($userType === 'Agent') {
            $query->where('agent_id', \Auth::user()->agent_id);
        } else {
            $query->where('created_by', \Auth::user()->id);
        }

        $query->orderBy('created_at', 'desc');
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
            'id' => 'required|integer|exists:whatsapp_campaigns,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = WhatsappCampaign::with('recipients')->find($request->id);

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found.',
            ], 404);
        }

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

    public function updateCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:whatsapp_campaigns,id',
            'campaign_name' => 'required|string|max:255',
            'brand_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'region_id' => 'required|integer|exists:regions,id',
            'recipient_type' => 'required|in:leads,admissions,applications,agents,import',
            'template_id' => 'nullable|integer',
            'from_number' => 'nullable|string|max:50',
            'body' => 'required|string',
            'filters_json' => 'nullable|array',
            'total_recipients' => 'nullable|integer|min:0',
            'status' => 'required|in:draft,pending_approval',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*.id' => 'required|integer',
            'recipient_ids.*.name' => 'required|string',
            'recipient_ids.*.phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = WhatsappCampaign::find($request->id);
        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found.',
            ], 404);
        }

        if (in_array($campaign->status, ['approved', 'sending', 'completed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign cannot be updated once it is approved, sending, or completed.',
            ], 422);
        }

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

        $campaign->update([
            'campaign_name'    => $request->campaign_name,
            'brand_id'         => $request->brand_id,
            'branch_id'        => $request->branch_id,
            'region_id'        => $request->region_id,
            'recipient_type'   => $request->recipient_type,
            'template_id'      => $request->template_id,
            'from_number'      => $request->from_number,
            'body'             => $request->body,
            'filters_json'     => $request->filters_json,
            'total_recipients' => $request->total_recipients ?? 0,
            'status'           => $request->status,
        ]);

        WhatsappCampaignRecipient::where('campaign_id', $campaign->id)->delete();

        $rows = [];
        foreach ($request->recipient_ids as $recipient) {
            $rows[] = [
                'campaign_id'    => $campaign->id,
                'recipient_type' => $request->recipient_type,
                'recipient_id'   => $recipient['id'],
                'name'           => $recipient['name'],
                'phone'          => $recipient['phone'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        WhatsappCampaignRecipient::insert($rows);

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => "'{$campaign->campaign_name}' WhatsApp Campaign Updated",
                'message' => "WhatsApp campaign '{$campaign->campaign_name}' has been updated successfully."
            ]),
            'module_id' => $campaign->id,
            'module_type' => 'whatsapp_campaign',
            'notification_type' => 'WhatsApp Campaign Updated',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign updated successfully',
            'data' => $campaign->fresh(),
        ]);
    }

    public function previewWhatsapp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'           => 'required|integer|exists:whatsapp_campaigns,id',
            'recipient_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = WhatsappCampaign::with('recipients')->find($request->id);
        $senderDetails = User::with(['branch', 'region', 'brand'])->where('id', $campaign->whatsapp_sender_id)->first();

        if (!$campaign) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Campaign not found.',
            ], 404);
        }

        $recipientId = $request->recipient_id;
        if (!$recipientId && $campaign->recipients->isNotEmpty()) {
            $recipientId = $campaign->recipients->first()->recipient_id;
        }

        $recipientType = $campaign->recipient_type;
        $parsedBody = $campaign->body;

        if ($recipientId) {
            $parsedBody = parseEmailTemplate($campaign->body, $recipientId, $recipientType, $senderDetails);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'campaign_id'    => $campaign->id,
                'campaign_name'  => $campaign->campaign_name,
                'from_number'    => $campaign->from_number,
                'recipient_type' => $campaign->recipient_type,
                'recipient_id'   => $recipientId,
                'body'           => $parsedBody,
            ],
        ]);
    }

    public function previewWhatsappByTypeID(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
            'recipient_id' => 'required|integer',
            'recipient_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $recipientId = $request->recipient_id;
        $recipientType = $request->recipient_type;
        $senderDetails = User::with(['branch', 'region', 'brand'])->where('id', auth()->id())->first();

        $parsedBody = $request->body;
        if ($recipientId) {
            $parsedBody = parseEmailTemplate($request->body, $recipientId, $recipientType, $senderDetails);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'recipient_type' => $request->recipient_type,
                'recipient_id'   => $recipientId,
                'body'           => $parsedBody,
            ],
        ]);
    }
}
