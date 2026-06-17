<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $table = 'email_campaigns';

    protected $fillable = [
        'campaign_name',
        'recipient_type',
        'template_id',
        'email_sender_id',
        'from_email',
        'subject',
        'body',
        'filters_json',
        'total_recipients',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'approved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
