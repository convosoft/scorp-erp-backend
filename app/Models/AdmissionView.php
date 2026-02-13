<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionView extends Model
{

 protected $table = 'leads_view';


    public function labels()
    {
        if($this->labels)
        {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }





    public function clients()
    {
        return $this->belongsToMany('App\Models\User', 'client_deals', 'deal_id', 'client_id');
    }



    public function users()
    {
        return $this->belongsToMany('App\Models\User', 'user_deals', 'deal_id', 'user_id');
    }





    public function tasks()
    {
        return $this->hasMany('App\Models\DealTask', 'deal_id', 'id');
    }

    public function complete_tasks()
    {
        return $this->hasMany('App\Models\DealTask', 'deal_id', 'id')->where('status', '=', 1);
    }




    public function activities()
    {
        return $this->hasMany('App\Models\ActivityLog', 'deal_id', 'id')->orderBy('id', 'desc');
    }

    public function discussions()
    {
        return $this->hasMany('App\Models\DealDiscussion', 'deal_id', 'id')->orderBy('id', 'desc');
    }




public function leadDetails()
{
    return $this->belongsTo(Lead::class,"id","is_converted");
}
public function client()
{
    return $this->hasOneThrough(
        User::class,        // Final model
        ClientDeal::class,  // Intermediate model
        'deal_id',          // Foreign key on client_deals
        'id',               // Foreign key on users
        'id',               // Local key on deals
        'client_id'         // Local key on client_deals
    );
}

 public function getTagsAttribute()
    {
        return LeadTag::whereRaw("FIND_IN_SET(id, ?)", [$this->tag_ids])->get();
    }

     public function deal()
    {
        return $this->belongsTo(Deal::class, 'id');
    }


}
