<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaDocument extends Model
{

    protected $fillable = [
        'TypesDocumentID',
        'type_id',
        'admission_id',
        'application_id',
        'type',
        'document_link',
        'comments',
        'created_by',
    ];
    protected $appends = ['deal'];
    protected $with = ['uploadedby:id,name', 'user:id,name', 'documentType:id,name']; // Always eager load this relationship

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee_id');
    }
    public function uploadedby()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
    public function documentType()
    {
        return $this->hasOne('App\Models\TypesDocument', 'id', 'TypesDocumentID');
    }
    public function getDealAttribute()
    {
        if ($this->type !== 'admission') {
            return null;
        }

        return \App\Models\Deal::select('id', 'name', 'stage_id')
            ->find($this->admission_id);
    }
}
