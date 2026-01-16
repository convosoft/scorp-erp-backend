<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id','document_id','document_value','created_by'
    ];
    protected $with = ['uploadedby:id,name','user:id,name','documentType:id,name']; // Always eager load this relationship
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee_id');
    } 
    public function uploadedby()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
     public function documentType()
    {
        return $this->hasOne('App\Models\DocumentType', 'id', 'documenttypeID');
    }
}
