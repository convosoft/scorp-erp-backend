<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationMeta extends Model
{
    use HasFactory;
    protected $fillable = [
        'destination_id',
        'created_by',
        'meta_key',
        'meta_value',
    ];

    public function destination()
    {
        return $this->belongsTo(destination::class);
    }
}
