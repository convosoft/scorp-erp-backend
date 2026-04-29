<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSendingQueue extends Model
{
    use HasFactory;
     protected $fillable = [
        'is_send',
        'status',
        'mailerror',
    ];

     public function brand()
    {
        return $this->hasOne('App\Models\User', 'id', 'brand_id');
    }
}
