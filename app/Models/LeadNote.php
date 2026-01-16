<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadNote extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function author()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
}
