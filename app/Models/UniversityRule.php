<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversityRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'university_id',
        'position',
        'rule_type',
        'type',
        'created_by',
    ];

    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
}
