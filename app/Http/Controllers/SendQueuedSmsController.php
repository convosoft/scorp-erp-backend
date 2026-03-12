<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SmsSendingQueue;
use Twilio\Rest\Client;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

class SendQueuedSmsController extends Controller
{

    /**
     * Process queued SMS
     */
    public function handle(Request $request)
    {

        $queues = SmsSendingQueue::where('is_send', '0')
            ->where('status', '0')
            ->limit(350)
            ->get();

        $sendcount = 0;
        $failcount = 0;

        $twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        foreach ($queues as $queue) {

            try {

                $message = $twilio->messages->create(
                    $queue->phone,
                    [
                        "from" => config('services.twilio.from'),
                        "body" => $queue->message
                    ]
                );

                $queue->twilio_sid = $message->sid;
                $queue->is_send = '1';
                $queue->status = '1';
                $queue->delivered_at = now();
                $queue->processed_at = now();
                $queue->save();

                $sendcount++;

            } catch (\Exception $e) {

                $queue->status = '2';
                $queue->error_message = $e->getMessage();
                $queue->processed_at = now();
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


    /**
     * Add SMS to queue
     */
    public function addToSmsQueue(Request $request)
    {

        $validator = \Validator::make(
            $request->all(),
            [
                'phone' => 'required|string',
                'message' => 'required|string',

                'brand_id' => 'required|integer',
                'branch_id' => 'required|integer',
                'region_id' => 'required|integer',

                'related_type' => 'required|in:lead,admission,application',
                'related_id' => 'required|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 200);
        }

        try {


        /* PHONE VALIDATION (GLOBAL) */

        $phoneUtil = PhoneNumberUtil::getInstance();
        $phoneInput = trim($request->phone);

        // ensure international format
        if (!str_starts_with($phoneInput, '+')) {
            $phoneInput = '+' . $phoneInput;
        }

        try {

            $numberProto = $phoneUtil->parse($phoneInput, null);

            if (!$phoneUtil->isValidNumber($numberProto)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid phone number'
                ]);
            }

            // Convert to E164 format required by Twilio
            $phone = $phoneUtil->format($numberProto, PhoneNumberFormat::E164);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Phone number format invalid'
            ]);
        }
            $sms = new SmsSendingQueue();

            $sms->priority = 2;
            $sms->created_by = auth()->id();

            $sms->phone = $request->phone;
            $sms->message = $request->message;

            $sms->brand_id = $request->brand_id;
            $sms->branch_id = $request->branch_id;
            $sms->region_id = $request->region_id;

            $sms->from_number = config('services.twilio.from');

            $sms->related_type = $request->related_type;
            $sms->related_id = $request->related_id;

            $sms->is_send = '0';
            $sms->status = '1';

            $sms->save();


            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => "SMS queued",
                    'message' => "SMS queued for {$request->phone}",
                ]),
                'module_id' => $request->related_id,
                'module_type' => $request->related_type,
                'notification_type' => 'SMS Queued',
            ]);


            return response()->json([
                'status' => 'success',
                'message' => 'SMS added to queue successfully',
                'data' => $sms
            ]);

        } catch (\Exception $e) {

            addLogActivity([
                'type' => 'error',
                'note' => json_encode([
                    'title' => "SMS Queue Failed",
                    'message' => $e->getMessage(),
                ]),
                'module_id' => 0,
                'module_type' => 'sms_queue',
                'notification_type' => 'SMS Queue Failed',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get SMS queue by related module
     */
    public function getSmsQueueByRelated(Request $request)
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

            $sms = SmsSendingQueue::where('related_type', $request->related_type)
                ->where('related_id', $request->related_id)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $sms
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
