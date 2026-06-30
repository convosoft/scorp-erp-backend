<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WhatsappSendingQueue;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

class SendQueuedWhatsappController extends Controller
{
    /**
     * Process queued WhatsApp messages
     */
    public function handle(Request $request)
    {
        $queues = WhatsappSendingQueue::where('is_send', '0')
            ->where('status', '1')
            ->limit(350)
            ->get();

        $sendcount = 0;
        $failcount = 0;

        $apiKey = config('services.wasender.api_key');
        $baseUrl = config('services.wasender.base_url', 'https://www.wasenderapi.com/api');

        foreach ($queues as $queue) {
            try {
                $toPhone = $queue->phone;
                // Clean the phone number to be digits only for WASender API
                $toPhone = preg_replace('/[^0-9]/', '', $toPhone);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post($baseUrl . '/send-message', [
                    'to' => $toPhone,
                    'text' => $queue->message,
                ]);

                if ($response->successful()) {
                    $resData = $response->json();
                    $queue->twilio_sid = $resData['id'] ?? $resData['message_id'] ?? null;
                    $queue->is_send = '1';
                    $queue->status = '1';
                    $queue->delivered_at = now();
                    $queue->processed_at = now();
                    $queue->save();

                    $sendcount++;
                } else {
                    throw new \Exception($response->body());
                }
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
     * Add WhatsApp to queue
     */
    public function addToWhatsappQueue(Request $request)
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
            ], 422);
        }

        try {
            /* PHONE VALIDATION (GLOBAL) */
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneInput = trim($request->phone);

            if (!str_starts_with($phoneInput, '+')) {
                $phoneInput = '+' . $phoneInput;
            }

            try {
                $numberProto = $phoneUtil->parse($phoneInput, null);

                if (!$phoneUtil->isValidNumber($numberProto)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid phone number'
                    ], 422);
                }

                $phone = $phoneUtil->format($numberProto, PhoneNumberFormat::E164);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phone number format invalid'
                ], 422);
            }

            $whatsapp = new WhatsappSendingQueue();

            $whatsapp->priority = 2;
            $whatsapp->created_by = auth()->id();
            $whatsapp->phone = $phone;
            $whatsapp->message = $request->message;
            $whatsapp->brand_id = $request->brand_id;
            $whatsapp->branch_id = $request->branch_id;
            $whatsapp->region_id = $request->region_id;
            $whatsapp->from_number = config('services.twilio.from');
            $whatsapp->related_type = $request->related_type;
            $whatsapp->related_id = $request->related_id;
            $whatsapp->is_send = '0';
            $whatsapp->status = '1';

            $whatsapp->save();

            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => "WhatsApp message queued",
                    'message' => "WhatsApp queued for {$phone}",
                ]),
                'module_id' => $request->related_id,
                'module_type' => $request->related_type,
                'notification_type' => 'WhatsApp Queued',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'WhatsApp added to queue successfully',
                'data' => $whatsapp
            ]);
        } catch (\Exception $e) {
            addLogActivity([
                'type' => 'error',
                'note' => json_encode([
                    'title' => "WhatsApp Queue Failed",
                    'message' => $e->getMessage(),
                ]),
                'module_id' => 0,
                'module_type' => 'whatsapp_queue',
                'notification_type' => 'WhatsApp Queue Failed',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WhatsApp queue by related module
     */
    public function getWhatsappQueueByRelated(Request $request)
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
            ], 422);
        }

        try {
            $whatsapp = WhatsappSendingQueue::where('related_type', $request->related_type)
                ->where('related_id', $request->related_id)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $whatsapp
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
