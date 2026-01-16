<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'module_type_id',
        'permission_type_id',
    ];

    public function moduleType()
    {
        return $this->belongsTo(ModuleType::class, 'module_type_id');
    }

    // Relationship to PermissionType
    public function permissionType()
    {
        return $this->belongsTo(PermissionType::class, 'permission_type_id');
    }
}
