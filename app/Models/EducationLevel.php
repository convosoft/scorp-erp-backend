<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationLevel extends Model
{
    use HasFactory;

    protected $table = 'education_levels';

    protected $fillable = [
        'name',
        'created_by',
    ];

    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
}
