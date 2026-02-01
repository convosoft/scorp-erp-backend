<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailSendingQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendGridWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Log the full incoming request for debugging
        Log::info('SendGrid Webhook Received: ' . json_encode($request->all()));

        foreach ($request->all() as $event) {

            // Log each event individually 
            // Skip if queue_id is missing
           $queue = null;

            // First try unique_args
            if (isset($event['queue_id'])) { 
                $queue = EmailSendingQueue::where('id', $event['queue_id'])->first();
            }
            
            if (!$queue) {
                Log::warning('Queue ID missing in event: ' . $event['queue_id']);
                continue;
            }


            // Update email status timestamps
            switch ($event['event']) {
                case 'processed':
                    if (!$queue->processed_at) {
                        $queue->processed_at = Carbon::now();
                    }
                    break;
                case 'delivered':
                    if (!$queue->delivered_at) {
                        $queue->delivered_at = Carbon::now();
                    }
                    break;

                case 'open':
                    if (!$queue->opened_at) {
                        $queue->opened_at = Carbon::now();
                    }
                    break;

                case 'click':
                    if (!$queue->clicked_at) {
                        $queue->clicked_at = Carbon::now();
                    }
                    break;

                case 'bounce':
                case 'dropped':
                    if (!$queue->bounced_at) {
                        $queue->bounced_at = Carbon::now();
                    }
                    break;

                default:
                    Log::info('Unhandled event type: ' . $event['event']);
                    break;
            }

            $queue->save();
        }

        return response()->json(['status' => 'ok']);
    }
}
