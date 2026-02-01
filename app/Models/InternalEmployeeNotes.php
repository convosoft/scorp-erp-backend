<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalEmployeeNotes extends Model
{
    use HasFactory;
    protected $guarded = [];

      public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

     
    public function employee()
    {
        return $this->hasOne('App\Models\User', 'id', 'lead_assigned_user');
    }   

    public function lead_assigned_user()
    {
        return $this->hasOne('App\Models\User', 'id', 'lead_assigned_user');
    }   

    

}
