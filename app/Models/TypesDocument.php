<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypesDocument extends Model
{
    use HasFactory;

    protected $table = 'types_document';

    protected $fillable = [
        'name',
        'type',
        'created_by'
    ];
}
