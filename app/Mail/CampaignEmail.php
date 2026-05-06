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
            'category'   => 'convosoft-campaign',
            'unique_args' => [
                'queue_id' => $this->queue->id
            ]
        ];

        return $this->from('no-reply@convosoftmail.com')
                    ->subject($this->queue->subject)
                    ->html($this->queue->content)
                    ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) use ($headerData) {
                        // Add SendGrid X-SMTPAPI header
                        $message->getHeaders()->addTextHeader('X-SMTPAPI', json_encode($headerData));
                        
                        // Generate and set Message-ID if not exists
                        $headers = $message->getHeaders();
                        if (!$headers->has('Message-ID')) {
                            $messageId = bin2hex(random_bytes(16)) . '@erp.scorp.co';
                            $headers->addIdHeader('Message-ID', $messageId);
                        } else {
                            $messageId = $headers->getHeaderBody('Message-ID');
                            // Remove angle brackets
                            $messageId = trim($messageId, '<>');
                        }
                        
                        Log::info("Captured Message-ID: " . $messageId);
                        
                        // Save to database
                        $this->queue->sg_message_id = $messageId;
                        $this->queue->save();
                    });
    }
}