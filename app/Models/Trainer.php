<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trainer extends Model
{
    protected $fillable = [
        'branch_id',
        'region_id',
        'brand_id',
        'firstname',
        'lastname',
        'contact',
        'email',
        'address',
        'expertise',
        'created_by',
    ];

    public function branch()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch_id');
    }

    public function brand()
    {
        return $this->hasOne('App\Models\User', 'id', 'brand_id');
    }

    public function region()
    {
        return $this->hasOne('App\Models\Region', 'id', 'region_id');
    }

    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }


}
