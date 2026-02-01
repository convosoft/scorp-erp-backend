<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salaryappriasal extends Model
{
    

    public static $technical = [
        'None',
        'Beginner',
        'Intermediate',
        'Advanced',
        'Expert / Leader',
    ];

    public static $organizational = [
        'None',
        'Beginner',
        'Intermediate',
        'Advanced',
    ];

    public function branches()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch');
    }

    public function employees()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }
     
}
