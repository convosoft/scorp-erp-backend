<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealApplication extends Model
{
    use HasFactory;
    protected $labels;
    protected $products;
    protected $sources;
    protected $fillable = ['labels','application_key','deal_id', 'university_id', 'course','courses_id','country_id', 'stage_id', 'external_app_id', 'name', 'intake', 'created_by','student_origin_country','student_origin_city','student_previous_university'];
    protected $with = ['stage:id,name','region:id,name','source:id,name','assignedUser:id,name','brand:id,name','branch:id,name','university:id,name','lead','contact']; // Always eager load this relationship

    public function getUniversity($id)
    {
        return University::where('id', $id)->first();
    }
    public static function getDealSummary($deals)
    {
        $total = 0;

        foreach($deals as $deal)
        {
            $total += $deal->price;
        }

        return \Auth::user()->priceFormat($total);
    }
    public function region()
{
    return $this->belongsTo(Region::class, 'region_id');
}
    public function contact()
{
    return $this->belongsTo(User::class, 'contact_id');
}

    public function labels()
    {
        if($this->labels)
        {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }

    public function products()
    {
        if($this->products)
        {
            return ProductService::whereIn('id', explode(',', $this->products))->get();
        }

        return [];
    }
    public function sources()
    {
        if($this->sources)
        {
            return Source::whereIn('id', explode(',', $this->sources))->get();
        }

        return [];
    }
    public function users()
    {
        return $this->belongsToMany('App\Models\User', 'user_deals', 'deal_id', 'user_id');
    }
    public function stage()
    {
        return $this->hasOne('App\Models\ApplicationStage', 'id', 'stage_id');
    }


public function brand()
{
    return $this->belongsTo(User::class, 'brand_id');
}

public function branch()
{
    return $this->belongsTo(Branch::class, 'branch_id');
}

public function source()
{
    return $this->belongsTo(Source::class, 'source_id');
}
public function lead()
{
    return $this->belongsTo(Lead::class);
}

public function assignedUser()
{
    return $this->belongsTo(User::class, 'assigned_to');
}
public function city()
{
    return $this->belongsTo(City::class, 'student_origin_city');
}

public function preinstitute()
{
    return $this->belongsTo(Institute::class, 'student_previous_university');
}

public function country()
{
    return $this->belongsTo(Country::class, 'country_id', 'country_code', 'country_code');
}
public function deal()
{
    return $this->belongsTo(Deal::class, 'deal_id');
}
public function university()
{
    return $this->belongsTo(University::class, 'university_id');
}
public function course()
{
    return $this->belongsTo(Course::class, 'course_id');
}


public function countryName()
{
    return $this->belongsTo(Country::class, 'country_id');
}


}
