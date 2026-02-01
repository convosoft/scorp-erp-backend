<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationNote extends Model
{
    use HasFactory;

    public function author()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
}
