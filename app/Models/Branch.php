<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Region;

class Branch extends Model
{
    protected $fillable = [
        "name",
        "region_id",
        "branch_manager_id",
        "google_link",
        "social_media_link",
        "phone",
        "email",
        "brands",
        "created_by",
        "created_at",
        "updated_at",
        "longitude",
        "latitude",
        "timezone",
        "shift_time",
        "end_time",
        "is_sat_off",
        "start_time"
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'branch_manager_id');
    }
    public function brand()
    {
        return $this->belongsTo(User::class, 'brands');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
}
