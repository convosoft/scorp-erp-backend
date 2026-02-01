<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMeta extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'created_by',
        'meta_key',
        'meta_value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
