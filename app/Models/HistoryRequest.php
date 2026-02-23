<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'blocked_by',
        'blocked_status',
        'blocked_reason',
        'block_attachments',
        'unblock_by',
        'unblock_status',
        'unblock_reason',
        'unblock_attachments',
        'admin_action_by',
        'admin_action_status',
        'admin_action_reason',
        'admin_action_attachments',
    ];

}
