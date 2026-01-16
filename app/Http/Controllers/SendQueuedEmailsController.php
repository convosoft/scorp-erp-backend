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
}
