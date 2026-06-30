<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappSendingQueue extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_sending_queues';

    protected $fillable = [
        'campaign_id',
        'phone',
        'message',
        'created_by',
        'brand_id',
        'from_number',
        'branch_id',
        'region_id',
        'sender_id',
        'stage_id',
        'pipeline_id',
        'template_id',
        'related_type',
        'related_id',
        'priority',
        'is_send',
        'status',
        'error_message',
        'twilio_sid',
        'processed_at',
        'delivered_at',
    ];

    public function brand()
    {
        return $this->hasOne('App\Models\User', 'id', 'brand_id');
    }
}
