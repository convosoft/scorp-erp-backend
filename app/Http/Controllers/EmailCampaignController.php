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
}
