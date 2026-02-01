<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    protected $with = ['pipeline:id,name','stage:id,name','source:id,name','assignedUser:id,name','brand:id,name','branch:id,name','region:id,name','lead']; //  Always eager load this relationship
    protected $fillable = [
        'name',
        'price',
        'pipeline_id',
        'stage_id',
        'group_id',
        'sources',
        'products',
        'created_by',
        'notes',
        'labels',
        'permissions',
        'status',
        'is_active',
        'branch_id',
        'assigned_to'
    ];

    public static $permissions = [
        'Client View Tasks',
        'Client View Products',
        'Client View Sources',
        'Client View Contacts',
        'Client View Files',
        'Client View Invoices',
        'Client View Custom fields',
        'Client View Members',
        'Client Add File',
        'Client Deal Activity',
    ];

    public static $statues = [
        'Active' => 'Active',
        'Won' => 'Won',
        'Loss' => 'Loss',
    ];

    public $customField;

    public function labels()
    {
        if($this->labels)
        {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }

    public function pipeline()
    {
        return $this->hasOne('App\Models\Pipeline', 'id', 'pipeline_id');
    }

    public function stage()
    {
        return $this->hasOne('App\Models\Stage', 'id', 'stage_id');
    }

    public function group()
    {
        return $this->hasOne('App\Models\Group', 'id', 'group_id');
    }

    public function clients()
    {
        return $this->belongsToMany('App\Models\User', 'client_deals', 'deal_id', 'client_id');
    }

    public function courses()
    {
        if($this->courses)
        {
            return Course::whereIn('id', explode(',', $this->courses))->get();
        }

        return [];
    }

    public function users()
    {
        return $this->belongsToMany('App\Models\User', 'user_deals', 'deal_id', 'user_id');
    }


    public function products()
    {
        if($this->products)
        {
            return ProductService::whereIn('id', explode(',', $this->products))->get();
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

    public function files()
    {
        return $this->hasMany('App\Models\DealFile', 'deal_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany('App\Models\DealTask', 'deal_id', 'id');
    }

    public function complete_tasks()
    {
        return $this->hasMany('App\Models\DealTask', 'deal_id', 'id')->where('status', '=', 1);
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice', 'deal_id', 'id');
    }

    public function calls()
    {
        return $this->hasMany('App\Models\DealCall', 'deal_id', 'id');
    }

    public function emails()
    {
        return $this->hasMany('App\Models\DealEmail', 'deal_id', 'id')->orderByDesc('id');
    }

    public function activities()
    {
        return $this->hasMany('App\Models\ActivityLog', 'deal_id', 'id')->orderBy('id', 'desc');
    }

    public function discussions()
    {
        return $this->hasMany('App\Models\DealDiscussion', 'deal_id', 'id')->orderBy('id', 'desc');
    }

    public static function getDealSummary($deals)
    {
        $total = 0;

        foreach($deals as $deal)
        {
            $total += $deal->price;
        }

        return \Auth::user()->priceFormat($total);
    }

    public function assignedUser()
{
    return $this->belongsTo(User::class, 'assigned_to');
}

public function brand()
{
    return $this->belongsTo(User::class, 'brand_id');
}

public function branch()
{
    return $this->belongsTo(Branch::class, 'branch_id');
}

public function region()
{
    return $this->belongsTo(Region::class, 'region_id');
}

public function source()
{
    return $this->belongsTo(Source::class, 'source_id');
}
public function lead()
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


}
