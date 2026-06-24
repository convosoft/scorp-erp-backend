<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyPermission extends Model
{
    use HasFactory;
    protected $table = 'company_permission';

     protected $fillable = [
        'user_id',
        'permitted_company_id',
        'active',
        'created_by',
    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'company_id');
    }


}
