<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappCampaignRecipient extends Model
{
    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'recipient_type',
        'recipient_id',
        'name',
        'phone',
    ];

    public function campaign()
    {
        return $this->belongsTo(WhatsappCampaign::class, 'campaign_id');
    }
}
