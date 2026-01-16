<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplicationNote extends Model
{
    protected $fillable = [
        'application_id',
        'note_created',
        'note',
        'created_by',
    ];

    public function Creater()
    {
        return $this->hasOne('App\Models\User', 'id', 'note_created');
    }
}
