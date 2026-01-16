<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Department;
use App\Models\AnnouncementCategory;
use App\Models\User;
use Spatie\Permission\Models\Role;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'branch_id',
        'region_id',
        'brand_id',
        'description',
        'created_by',
    ];

    public function getDepartmentNameAttribute()
    {
        return Department::where('id', $this->department)->value('name');
    }

    public function getCategoryNameAttribute()
    {
        return AnnouncementCategory::where('id', $this->category_id)->value('name');
    }

    public function getBrandNamesAttribute()
    {
        if (!$this->brand_id) return [];
        $ids = explode(',', $this->brand_id);
        return User::whereIn('id', $ids)->pluck('name');
    }

    public function getRoleNamesAttribute()
    {
        if (!$this->role_id) return [];
        $ids = explode(',', $this->role_id);
        return Role::whereIn('id', $ids)->pluck('name');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->select('id','name');
    }
}
