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
            'recipient_type' => 'required|in:leads,admissions,applications,agents',
            'template_id' => 'nullable|integer',
            'from_email' => 'nullable|email',
            'subject' => 'required|string|max:500',
            'body' => 'required',
            'filters_json' => 'nullable|array',
            'total_recipients' => 'nullable|integer|min:0',
            'status' => 'required|in:draft,pending_approval',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = EmailCampaign::create([
            'campaign_name'    => $request->campaign_name,
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

        foreach ($request->recipient_ids as $recipient) {

            EmailCampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'recipient_type' => $request->recipient_type,
                'recipient_id' => $recipient['id'],
                'name' => $recipient['name'],
                'email' => $recipient['email'],
            ]);
        }

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
}
