<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewSchedule extends Model
{
    protected $fillable = [
        'candidate',
        'employee',
        'date',
        'time',
        'comment',
        'employee_response',
        'created_by',
    ];

    public function applications()
    {
       return $this->hasOne('App\Models\JobApplication','id','candidate');
    }

    public function users()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee');
    }

    public function scheduled_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

    
}
