<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

class CampaignEmail extends Mailable
{
    public $queue;

    public function __construct($queue)
    {
        $this->queue = $queue;
    }

    public function build()
    {
        // SendGrid Custom Headers
        $headerData = [
            'category' => 'convosoft-campaign',
            'unique_args' => [
                'queue_id' => $this->queue->id
            ]
        ];

        $mail = $this->from('no-reply@convosoftmail.com')
            ->subject($this->queue->subject)
            ->html($this->queue->content)
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) use ($headerData) {

                $message->getHeaders()->addTextHeader('X-SMTPAPI', json_encode($headerData));

                $headers = $message->getHeaders();

                if (!$headers->has('Message-ID')) {
                    $messageId = bin2hex(random_bytes(16)) . '@erp.scorp.co';
                    $headers->addIdHeader('Message-ID', $messageId);
                } else {
                    $messageId = trim($headers->getHeaderBody('Message-ID'), '<>');
                }

                Log::info("Captured Message-ID: " . $messageId);

                $this->queue->sg_message_id = $messageId;
                $this->queue->save();
            });

        /*
        |---------------------------------------
        | Attachments
        |---------------------------------------
        */

        if ($this->queue->attachment) {

            $attachments = json_decode($this->queue->attachment, true);

            if (is_array($attachments)) {
                foreach ($attachments as $filePath) {

                    $fullPath = public_path($filePath);

                    if (file_exists($fullPath)) {
                        $mail->attach($fullPath);
                    }
                }
            }
        }

        return $mail;
    }
}
