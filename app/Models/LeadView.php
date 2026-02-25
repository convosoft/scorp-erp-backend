<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadView extends Model
{
    protected $table = 'leads_view';

    // Views don't have timestamps in the traditional sense
    public $timestamps = false;

    // View is read-only
    protected $guarded = ['*'];

    // Cast attributes
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'stage_entered_at' => 'datetime',
        'days_at_stage' => 'integer',
        'activity_count' => 'integer',
        'discussion_count' => 'integer',
        'call_count' => 'integer',
        'email_count' => 'integer',
        'file_count' => 'integer',
        'is_converted' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Accessor for tags as array
    // public function getTagsAttribute()
    // {
    //     if (!$this->tag_names) {
    //         return [];
    //     }

    //     $names = explode(', ', $this->tag_names);
    //     $ids = explode(',', $this->tag_ids_list);

    //     return collect($names)->map(function($name, $index) use ($ids) {
    //         return [
    //             'id' => $ids[$index] ?? null,
    //             'name' => $name,
    //         ];
    //     });
    // }

    public function getTagsAttribute()
    {
        return LeadTag::whereRaw("FIND_IN_SET(id, ?)", [$this->tag_ids])->get();
    }

    // If you still need the actual relationships for updates
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'id');
    }
}
