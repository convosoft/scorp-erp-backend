<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationReport extends Model
{
    protected $table = 'deal_applications_view_v2';
    public $timestamps = false; // since view may not have updated_at

    protected $casts = [
        'deposit_created_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
