<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaignRecipient extends Model
{
    protected $table = 'email_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'recipient_type',
        'recipient_id',
        'name',
        'email',
    ];

    public function campaign()
    {
        return $this->belongsTo(EmailCampaign::class, 'campaign_id');
    }
}
