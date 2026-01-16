<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'email',
        //'subject',
        'user_id',
        'pipeline_id',
        'stage_id',
        'brand_id',
        'branch_id',
        'sources',
        'products',
        'notes',
        'labels',
        'order',
        'created_by',
        'is_active',
        'date',
        'branch_id',
    ];

    public function labels()
    {
        if($this->labels)
        {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }

    public function stage()
    {
        return $this->hasOne('App\Models\LeadStage', 'id', 'stage_id');
    }

    public function files()
    {
        return $this->hasMany('App\Models\LeadFile', 'lead_id', 'id');
    }

    public function pipeline()
    {
        return $this->hasOne('App\Models\Pipeline', 'id', 'pipeline_id');
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

    public function assignto()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function Agency()
    {
        return $this->hasOne('App\Models\Agency', 'id', 'organization_link');
    }
    
    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

    public function products()
    {
        if($this->products)
        {
            return ProductService::whereIn('id', explode(',', $this->products))->get();
        }

        return [];
    }

    public function courses()
    {
        if($this->courses)
        {
            return Course::whereIn('id', explode(',', $this->courses))->get();
        }

        return [];
    }

    public function sources()
    {
        if($this->sources)
        {
            return Source::whereIn('id', explode(',', $this->sources))->get();
        }

        return [];
    }
    public function Source()
    {
        return $this->hasOne('App\Models\Source', 'id', 'sources');
    }

    public function users()
    {
        return $this->belongsToMany('App\Models\User', 'user_leads', 'lead_id', 'user_id');
    }

    public function activities()
    {
        return $this->hasMany('App\Models\LeadActivityLog', 'lead_id', 'id')->orderBy('id', 'desc');
    }

    public function discussions()
    {
        return $this->hasMany('App\Models\LeadDiscussion', 'lead_id', 'id')->orderBy('id', 'desc');
    }

    public function calls()
    {
        return $this->hasMany('App\Models\LeadCall', 'lead_id', 'id');
    }

    public function emails()
    {
        return $this->hasMany('App\Models\LeadEmail', 'lead_id', 'id')->orderByDesc('id');
    }

    public function getTagsAttribute()
    {
        return LeadTag::whereRaw("FIND_IN_SET(id, ?)", [$this->tag_ids])->get();
    }
}
