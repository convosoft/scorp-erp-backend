<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversityMeta extends Model
{
    use HasFactory;
    protected $fillable = [
        'university_id',
        'created_by',
        'type',
        'meta_key',
        'meta_value',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}
