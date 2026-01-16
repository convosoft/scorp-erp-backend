<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deduction extends Model
{
    protected $fillable = [
        'employee_id',
        'deduction_option',
        'title',
        'amount',
        'created_by',
    ];
    protected $table = 'saturation_deductions'; // Custom table name

    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'employee_id');
    }

    public function deduction_option()
    {
        return $this->hasOne('App\Models\DeductionOption', 'id', 'deduction_option');
    }
    public static $saturationDeductiontype = [
        'fixed'=>'Fixed',
        'percentage'=> 'Percentage',
    ];
}
