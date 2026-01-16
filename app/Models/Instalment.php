<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instalment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee',
        'course_id',
    ];

    public function course_by()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
