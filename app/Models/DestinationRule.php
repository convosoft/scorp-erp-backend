<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'destination_id',
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
