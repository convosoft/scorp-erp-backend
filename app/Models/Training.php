<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'branch',
        'trainer_option',
        'training_type',
        'trainer',
        'training_cost',
        'employee',
        'start_date',
        'end_date',
        'description',
        'created_by',
        'status',
    ];


    public static $options = [
        'Internal',
        'External',
    ];

    public static $performance = [
        'Not Concluded',
        'Satisfactory',
        'Average',
        'Poor',
        'Excellent',
    ];

    public static $Status = [
        'Pending',
        'Started',
        'Completed',
        'Terminated',
    ];

    public function branches()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch');
    }

    public function training_type()
    {
        return $this->hasOne('App\Models\TrainingType', 'id', 'training_type');
    }

    public function employees()
    {
        if (!empty($this->employee)) {
            // If employee is set, return the Employee relationship
            return $this->hasOne('App\Models\Employee', 'id', 'employee');
        } else {
            // Otherwise, return the User relationship
            return $this->hasOne('App\Models\User', 'id', 'employee');
        }
    }

    public function assign_to()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee');
    }

    public function trainer()
    {
        return $this->hasOne('App\Models\Trainer', 'id', 'trainer');
    }
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
