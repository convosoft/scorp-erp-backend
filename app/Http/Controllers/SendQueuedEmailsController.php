<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailSendingQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\CampaignEmail;

class SendQueuedEmailsController extends Controller
{
    public function handle(Request $request)
    {
        $queues = EmailSendingQueue::where('is_send', '0')
            ->where('status', '1')
            ->where('priority', '3')
            ->limit(350)
            ->get();

        $sendcount = 0;
        $failcount = 0;

        foreach ($queues as $queue) {

            // Replace placeholders dynamically
            $queue->content = str_replace(
                ['{email}', '{name}', '{activation_link}'],
                [
                    $queue->to,
                    $queue->related_type ?? 'User',
                    $queue->related_id ? "https://erp.scorp.co/activate/{$queue->related_id}" : ""
                ],
                $queue->content
            );

            try {
                Mail::to($queue->to)->send(new CampaignEmail($queue));

                // only update after successful send
                $queue->is_send = '1';
                $queue->save();

                $sendcount++;

            } catch (\Exception $e) {
                $queue->status = '2';
                $queue->mailerror = $e->getMessage();
                $queue->save();

                $failcount++;
            }
        }

        return response()->json([
            'status' => 'completed',
            'sendcount' => $sendcount,
            'failcount' => $failcount,
        ]);
    }
    public function handleCrm(Request $request)
    {
        $queues = EmailSendingQueue::where('is_send', '0')
            ->where('status', '1')
            ->where('priority', '2')
            ->limit(350)
            ->get();

        $sendcount = 0;
        $failcount = 0;

        foreach ($queues as $queue) {

            // Replace placeholders dynamically
            $queue->content = str_replace(
                ['{email}', '{name}', '{activation_link}'],
                [
                    $queue->to,
                    $queue->related_type ?? 'User',
                    $queue->related_id ? "https://erp.scorp.co/activate/{$queue->related_id}" : ""
                ],
                $queue->content
            );

            try {
                Mail::to($queue->to)->send(new CampaignEmail($queue));

                // only update after successful send
                $queue->is_send = '1';
                $queue->save();

                $sendcount++;

            } catch (\Exception $e) {
                $queue->status = '2';
                $queue->mailerror = $e->getMessage();
                $queue->save();

                $failcount++;
            }
        }

        return response()->json([
            'status' => 'completed',
            'sendcount' => $sendcount,
            'failcount' => $failcount,
        ]);
    }

    public function addToEmailQueue(Request $request)
{
    $validator = \Validator::make(
        $request->all(),
        [
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',

            'subject' => 'required|string',
            'content' => 'required|string',

            'brand_id' => 'required|integer',
            'branch_id' => 'required|integer',
            'region_id' => 'required|integer',

            'related_type' => 'required|in:lead,admission,application',
            'related_id' => 'required|integer',

            // Validate multiple attachments
            'attachment.*' => 'nullable|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg'
        ]
    );

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()
        ], 200);
    }

    try {

        $email = new EmailSendingQueue();

        $email->priority = 2;
        $email->created_by = auth()->id();

        $email->to = $request->to;
        $email->cc = $request->cc;
        $email->bcc = $request->bcc;

        $email->subject = $request->subject;
        $email->content = $request->content;

        $email->brand_id = $request->brand_id;
        $email->branch_id = $request->branch_id;
        $email->region_id = $request->region_id;

        $email->from_email = config('app.queue_mail_from');

        $email->related_type = $request->related_type;
        $email->related_id = $request->related_id;

        $email->is_send = '0';
        $email->status = '1';

        /* Handle Multiple Attachments */
        $attachmentPaths = [];

        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('EmailAttachments'), $fileName);
                $attachmentPaths[] = 'EmailAttachments/' . $fileName;
            }
        }

        // Save attachments as JSON
        $email->attachment = !empty($attachmentPaths) ? json_encode($attachmentPaths) : null;

        $email->save();

        /* Activity Log */
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' =>  $email->subject." Email queued",
                'message' =>  $email->subject." Email queued for {$request->to}",
            ]),
            'module_id' => $request->related_id,
            'module_type' => $request->related_type,
            'notification_type' => 'Email Queued',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Email added to queue successfully',
            'data' => $email
        ]);

    } catch (\Exception $e) {

        addLogActivity([
            'type' => 'error',
            'note' => json_encode([
                'title' => "Email Queue Failed",
                'message' => $e->getMessage(),
            ]),
            'module_id' => 0,
            'module_type' => 'email_queue',
            'notification_type' => 'Email Queue Failed',
        ]);

        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ],500);
    }
}

    public function getEmailQueueByRelated(Request $request)
        {
            $validator = \Validator::make(
                $request->all(),
                [
                    'related_type' => 'required|in:lead,admission,application',
                    'related_id' => 'required|integer'
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()
                ], 200);
            }

            try {

                $emails = EmailSendingQueue::where('related_type', $request->related_type)
                            ->where('related_id', $request->related_id)
                            ->orderBy('id','desc')
                            ->get();

                return response()->json([
                    'status' => 'success',
                    'data' => $emails
                ], 200);

            } catch (\Exception $e) {

                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        }


}
