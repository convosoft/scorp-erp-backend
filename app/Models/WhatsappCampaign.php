<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'campaign_name',
        'recipient_type',
        'template_id',
        'whatsapp_sender_id',
        'brand_id',
        'region_id',
        'branch_id',
        'from_number',
        'body',
        'attachment',
        'filters_json',
        'total_recipients',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'comments',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'approved_at' => 'datetime',
    ];

    protected $with = ['creator:id,name', 'approver:id,name', 'branch:id,name', 'region:id,name', 'brand:id,name'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function branch()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch_id');
    }

    public function region()
    {
        return $this->hasOne('App\Models\Region', 'id', 'region_id');
    }

    public function brand()
    {
        return $this->hasOne('App\Models\User', 'id', 'brand_id');
    }

    public function recipients()
    {
        return $this->hasMany(WhatsappCampaignRecipient::class, 'campaign_id');
    }
}
