<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = [
        'name',
        'created_by',
    ];

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
