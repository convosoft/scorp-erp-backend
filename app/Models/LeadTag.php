<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadTag extends Model
{
    use HasFactory;

     protected $fillable = [
        'tag',
        'brand',
        'region_id',
        'branch_id',
        'created_by',
    ];

}
