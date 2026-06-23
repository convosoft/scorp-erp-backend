<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionContactDetail extends Model
{
    use HasFactory;

    protected $table = 'admission_contact_details';

    protected $fillable = [
        'deal_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'created_by',
        'is_bogus_email',
    ];

    protected $casts = [
        'is_bogus_email' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the deal associated with these contact details.
     */
    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    /**
     * Get the user who created these contact details.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
