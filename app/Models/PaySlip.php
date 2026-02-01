<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaySlip extends Model
{
    protected $fillable = [
        'employee_id',
        'net_payble',
        'basic_salary',
        'salary_month',
        'status',
        'allowance',
        'commission',
        'loan',
        'saturation_deduction',
        'other_payment',
        'overtime',
        'created_by',
        'brand_id',
        'region_id',
        'branch_id',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    
    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'employee_id');
    }
     
    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
    public function employees()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'employee_id');
    }
}
