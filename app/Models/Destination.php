<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    use HasFactory;
    protected $appends = ['country_code', 'meta_data' ];
    protected $hidden = ['destinationMeta'];
    protected $with = ['createdBy:id,name','universities'];


    public function course()
    {
        return $this->hasOne(Course::class)->where('type', 1);
    }

    public function getCountryCodeAttribute()
    {
        $ct = Country::where('name', $this->country)->first();
        return strtolower($ct->country_code ?? '');
    }

    public function getMetaDataAttribute()
    {
        $metas = new \stdClass();

        foreach ($this->destinationMeta as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;
            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return $metas;
    }

    public function destinationMeta()
    {
        return $this->hasMany(DestinationMeta::class, 'destination_id')->where('type', 1);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by'); // Ensure 'created_by' matches your DB column
    }
    public function productcoordinator()
    {
        return $this->belongsTo(User::class, 'product_coordinator_id'); // Ensure 'product_coordinator_id' matches your DB column
    }

    // public function rank()
    // {
    //     return $this->belongsTo(destinationRank::class, 'rank_id');
    // }
    public function ApplicableFee()
    {
        return $this->belongsTo(ToolkitApplicableFee::class, 'fee_id');
    }
    public function Channel()
    {
        return $this->belongsTo(ToolkitChannel::class, 'channel_id');
    }
    public function InstallmentPayOut()
    {
        return $this->belongsTo(ToolkitInstallmentPayOut::class, 'pay_out_id');
    }
    public function ToolkitLevel()
    {
        return $this->belongsTo(ToolkitLevel::class, 'level_id');
    }
    public function PaymentType()
    {
        return $this->belongsTo(ToolkitPaymentType::class, 'payment_type_id');
    }


    public function GlobalPaymentType()
    {
        return $this->belongsTo(ToolkitPaymentType::class, 'global_payment_type_id');
    }
    public function ToolkitTeam()
    {
        return $this->belongsTo(ToolkitTeam::class, 'team_id');
    }
    public function universities()
    {
        return $this->belongsTo(University::class, 'destination_id');
    }
    // public function homedestination()
    // {
    //     return $this->hasOne(Homedestination::class, 'main_uni_id', 'id');
    // }

    public function getCountryNameAttribute()
    {
        // If numeric → treat as country_id
        if (is_numeric($this->country)) {
            $country = Country::find($this->country);
            return $country?->name ?? $this->country; // fallback
        }

        // If string → return as it is
        return $this->country;
    }


}
