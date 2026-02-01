<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class appraisalremark extends Model
{
    use HasFactory;

    protected $fillable = [
        'appraisal_id',
        'competencies_id',
        'remarks',
    ];

    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class, 'appraisal_id', 'id');
    }
}
