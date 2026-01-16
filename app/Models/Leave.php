<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = [
        'employee_id',
        'Leave_type_id',
        'applied_on',
        'start_date',
        'end_date',
        'total_leave_days',
        'leave_reason',
        'remark',
        'status',
        'created_by',
    ];

    public function leaveType()
    {
        return $this->hasOne('App\Models\LeaveType', 'id', 'leave_type_id');
    }

    public function employees()
    {
        return $this->hasOne('App\Models\user', 'id', 'employee_id');
    }

    public function User()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee_id');
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
    public function updated_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'updated_by');
    }

    public function getApprovedLeaveDaysCount()
    {
        return Leave::where('status', 'Approved')
            ->where('employee_id', $this->employee_id)
            ->selectRaw('leave_type_id, SUM(CAST(total_leave_days AS SIGNED)) as total_days')
            ->groupBy('leave_type_id')
            ->pluck('total_days', 'leave_type_id')
            ->toArray();
    }

}
