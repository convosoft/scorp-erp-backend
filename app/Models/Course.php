<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;


    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function courselevel()
    {
        return $this->belongsTo(CourseLevel::class);
    }

    public function courseduration()
    {
        return $this->belongsTo(CourseDuration::class);
    }

    public function instalments()
    {
        return $this->hasMany(Instalment::class, 'course_id', 'id');
    }
}
