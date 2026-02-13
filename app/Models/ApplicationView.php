<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationView extends Model
{
    protected $table = 'deal_applications_view_v2';


    public function labels()
    {
        if($this->labels)
        {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }


    public function users()
    {
        return $this->belongsToMany('App\Models\User', 'user_deals', 'deal_id', 'user_id');
    }

     public function getTagsAttribute()
    {
        return LeadTag::whereRaw("FIND_IN_SET(id, ?)", [$this->tag_ids])->get();
    }





}
