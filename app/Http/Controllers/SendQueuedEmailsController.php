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

            'attachment' => 'nullable|file|max:10240'
        ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 200);
        }

    try {

        $attachmentPath = null;

        $email = new EmailSendingQueue();

        $email->priority = 2;
        $email->created_by = auth()->id();

        $email->to = $request->to;
        $email->cc = $request->cc;   // comma separated allowed
        $email->bcc = $request->bcc; // comma separated allowed

        $email->subject = $request->subject;
        $email->content = $request->content;

        $email->brand_id = $request->brand_id;
        $email->branch_id = $request->branch_id;
        $email->region_id = $request->region_id;

        $email->from_email = config('app.queue_mail_from');

        $email->related_type = $request->related_type;
        $email->related_id = $request->related_id;

        $email->is_send = 0;
        $email->status = 0;


        /* Attachment Upload */

        if ($request->hasFile('attachment')) {

            $file = $request->file('attachment');

            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('EmailAttachments'), $fileName);

            $email->attachment = 'EmailAttachments/' . $fileName;
        }

        $email->save();

        /* Activity Log */

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' =>  $email->subject." Email queued",
                'message' =>  $email->subject." Email queued for {$request->to}",
            ]),
            'module_id' =>$request->related_id,
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
}
