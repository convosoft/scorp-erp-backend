<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAdvice extends Model
{
    protected $table = 'student_advice';

    protected $fillable = [
        'admission_id',
        'document_link',
        'comments',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
