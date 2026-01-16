<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'date',
        'end_date',
        'occasion',
        'created_by',
    ];
     protected $with = ['created_by:id,name']; // Always eager load this relationship

      public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

}
