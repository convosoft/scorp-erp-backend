<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalTracking extends Model
{
    protected $fillable = [
        'branch',
        'goal_type',
        'start_date',
        'end_date',
        'subject',
        'target_achievement',
        'description',
        'created_by',
        'rating',
    ];

    public function goalType()
    {
        return $this->hasOne('App\Models\GoalType', 'id', 'goal_type');
    }

    public function branches()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch');
    }
    public function branch()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch');
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


    public static $status = [
        'Not Started',
        'In Progress',
        'Completed',
    ];
}
