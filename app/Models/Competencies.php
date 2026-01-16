<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competencies extends Model
{
    protected $fillable = [
        'name',
        'type',
        'created_by',
    ];
    protected $appends = ['role_names'];


    public function performance()
    {
        return $this->hasOne('Spatie\Permission\Models\Role', 'id', 'type');
    }

    // add
    public function getRoleNamesAttribute()
    {
        // Decode type field as JSON array
        $ids = json_decode($this->type, true);

        // Ensure it's an array
        if (!is_array($ids)) {
            return '';
        }

        $roles = \Spatie\Permission\Models\Role::whereIn('id', $ids)->pluck('name')->toArray();
        return implode(', ', $roles);
    }

}