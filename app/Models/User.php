<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;


use Carbon\Carbon;
use App\Models\CompanyPermission;
use App\Models\DealTask;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles;



    // protected $appends = ['profile']; 

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'plan',
        'plan_expire_date',
        'requested_plan',
        'type', 
        'lang',
        'mode',
        'created_by',
        'default_pipeline',
        'delete_status',
        'is_active',
        'remember_token',
        'last_login_at',
        'messenger_color',
        'dark_mode',
        'active_status',
        'drive_link',
        'branch_id',
        'passport_number',
        'phone',
        'date_of_birth',
        'brand_id',
        'address',
        'domain_link',
        'website_link',
        'project_director_id',
        'project_manager_id',
        'region_id',
        'emerg_name',
        'emerg_phone',
        'admission',
        'application',
        'deposit',
        'visa',
        'approved_status',
        'agent_assign_to',
        'national_id',
        'isloginrestrickted',
        'isloginanywhere',
        'longitude',
        'latitude',
        'blocked_by',
        'blocked_status',
        'blocked_reason',
        'employee_block_status',
        'employee_block_date',
        'employee_block_reason',
        'block_attachments',
        'unblock_by',
        'unblock_status',
        'unblock_reason',
        'unblock_attachments',
        'admin_action_by',
        'admin_action_status',
        'admin_action_reason',
        'admin_action_attachments',
        'tag_ids',
        "department_id",
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
     protected $with = ['designation','department'];
    public $settings;

  public function getTagNamesAttribute()
{
    if (empty($this->tag_ids)) {
        return '';
    }

    $ids = array_filter(array_map('intval', explode(',', $this->tag_ids)));
    
    if (empty($ids)) {
        return '';
    }

    $tags = Tag::whereIn('id', $ids)->pluck('name')->toArray();
    return implode(', ', $tags);
}
    public function creatorId()
    {
        if ($this->type == 'team' || $this->type == 'company' || $this->type == 'super admin') {
            return $this->id;
        } else {
            return $this->created_by;
        }
    }

    // public function manager()
    // {
    //     return $this->hasOne('App\Models\User', 'id', 'project_manager_id');
    // }   
     public function designation()
    {
        return $this->hasOne('App\Models\Designation', 'id', 'designation_id');
    }
    public function department()
    {
        return $this->hasOne('App\Models\Department', 'id', 'department_id');
    }
    // public function branch()
    // {
    //     return $this->hasOne('App\Models\Branch', 'id', 'branch_id');
    // }
    // public function region()
    // {
    //     return $this->hasOne('App\Models\Region', 'id', 'region_id');
    // }
    // public function brand()
    // {
    //     return $this->hasOne('App\Models\User', 'id', 'brand_id');
    // }

            public function manager()
        {
            return $this->belongsTo(User::class, 'project_manager_id');
        }

        public function director()
        {
            return $this->belongsTo(User::class, 'project_director_id');
        }

        public function branch()
        {
            return $this->belongsTo(Branch::class, 'branch_id');
        }

        public function region()
        {
            return $this->belongsTo(Region::class, 'region_id');
        }

        public function brand()
        {
            return $this->belongsTo(User::class, 'brand_id');
        }

    // public function director()
    // {
    //     return $this->hasOne('App\Models\User', 'id', 'project_director_id');
    // }
    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

    public function companyPermissions()
    {
        //return CompanyPermission::where('permitted_company_id', '=', $this->creatorId())->first();

        return $this->hasMany(CompanyPermission::class, 'user_id', 'id');
    }

    public function organization($id)
    {
       // dd($id);
       return Organization::where('user_id', $id)->first();
    }

    public function organizationLeadContactsList($id)
    {
        $contacts = Lead::join('client_deals as cd', 'leads.is_converted', '=', 'cd.deal_id')
                ->join('users', 'users.id', '=', 'cd.client_id')
                ->where('leads.organization_id', '=', $id)
                ->get();
            return $contacts;
    }


    public function organizationLeadContacts($id)
    {
        $count = Lead::join('client_deals as cd', 'leads.is_converted', '=', 'cd.deal_id')
                ->where('leads.organization_id', '=', $id)
                ->groupBy('leads.organization_id')
                ->count();
            return $count;
    }

    public function organizationLeadFiles($id)
    {
        $count = Lead::join('lead_files', 'leads.id', '=', 'lead_files.lead_id')
                ->where('leads.organization_id', '=', $id)
                ->groupBy('leads.organization_id')
                ->count();
            return $count;
    }


    public function organizationLeadNotes($id)
    {
        $count = OrganizationNote::where('organization_id', $id)->count();
        return $count;
    }

    public function organizationLeadTasks($id)
    {
        $count = DealTask::where('related_to', $id)->count();
        return $count;
    }

    public function organizationLeadNotesList($id)
    {
        $count = OrganizationNote::where('organization_id', $id)->orderBy('created_at', 'DESC')->get();
        return $count;
    }


    public function organizationLeadDiscussions($id)
    {
        $count = OrganizationDiscussion::join('users', 'organization_discussions.created_by', 'users.id')->where(['organization_discussions.organization_id' => $id])->orderBy('organization_discussions.created_at', 'DESC')->count();
        return $count;
    }


    public function organizationOpportunity($id)
    {
        $count = Lead::join('client_deals', 'client_deals.client_id', '=', 'leads.is_converted')->join('deal_applications', 'deal_applications.deal_id', '=', 'client_deals.deal_id')->where('leads.organization_id', $id)->groupBy('leads.organization_id')->count();
        return $count;
    }

    public function organizationTasks($id)
    {
        $count = DealTask::where('organization_id', $id)->groupBy('organization_id')->count();
        return $count;
    }

    public function organizationTasksList($id)
    {
        $count = DealTask::where('organization_id', $id)->get();
        return $count;
    }

    public function organizationOpportunitiesList($id)
    {
        $result = Lead::join('client_deals', 'client_deals.client_id', '=', 'leads.is_converted')->join('deal_applications', 'deal_applications.deal_id', '=', 'client_deals.deal_id')->where('leads.organization_id', $id)->groupBy('leads.organization_id')->get();
        return $result;
    }


    public function organizationActivitiesList($id)
    {
        $list = ActivityLog::join('client_deals', 'client_deals.deal_id', '=', 'activity_logs.deal_id')
        ->join('leads', 'leads.is_converted', '=', 'client_deals.client_id')
        ->get();
        return $list;
    }


    // public function countCompany()
    // {
    //     return User::where('type', '=', 'company')->where('created_by', '=', $this->creatorId())->count();
    // }

    // public function getProfileAttribute()
    // {

    //     if (!empty($this->avatar) && \Storage::exists($this->avatar)) {
    //         return $this->attributes['avatar'] = asset(\Storage::url($this->avatar));
    //     } else {
    //         return $this->attributes['avatar'] = asset(\Storage::url('avatar.png'));
    //     }
    // }

    public function authId()
    {
        return $this->id;
    }



    public function ownerId()
    {
        if ($this->type == 'team' || $this->type == 'company' || $this->type == 'super admin') {
            return $this->id;
        } else {
            return $this->created_by;
        }
    }

    public function deals()
    {
        return $this->belongsToMany('App\Models\Deal', 'user_deals', 'user_id', 'deal_id');
    }

    public function leads()
    {
        return $this->belongsToMany('App\Models\Lead', 'user_leads', 'user_id', 'lead_id');
    }

    public function Tag_ids()
    {
        return $this->belongsToMany('App\Models\Tag', 'id', 'tag_ids');
    }
    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'user_id', 'id');
    }


    public static function userDefaultData()
    {

        // Make Entry In User_Email_Template
        $allEmail = EmailTemplate::all();
        foreach ($allEmail as $email) {
            UserEmailTemplate::create(
                [
                    'template_id' => $email->id,
                    'user_id' => 2,
                    'is_active' => 1,
                ]
            );
        }
    }

    public function userDefaultDataRegister($user_id)
    {

        // Make Entry In User_Email_Template
        $allEmail = EmailTemplate::all();

        foreach ($allEmail as $email) {
            UserEmailTemplate::create(
                [
                    'template_id' => $email->id,
                    'user_id' => $user_id,
                    'is_active' => 1,
                ]
            );
        }
    }

    public static function userDefaultWarehouse()
    {
        warehouse::create(
            [
                'name' => 'North Warehouse',
                'address' => '723 N. Tillamook Street Portland, OR Portland, United States',
                'city' => 'Portland',
                'city_zip' => 97227,
                'created_by' => 2,
            ]
        );
    }

    public function userWarehouseRegister($user_id)
    {
        warehouse::create(
            [
                'name' => 'North Warehouse',
                'address' => '723 N. Tillamook Street Portland, OR Portland, United States',
                'city' => 'Portland',
                'city_zip' => 97227,
                'created_by' => $user_id,
            ]
        );
    }

    //default bank account for new company
    public function userDefaultBankAccount($user_id)
    {
        BankAccount::create(
            [
                'holder_name' => 'cash',
                'bank_name' => '',
                'account_number' => '-',
                'opening_balance' => '0.00',
                'contact_number' => '-',
                'bank_address' => '-',
                'created_by' => $user_id,
            ]
        );
    }

    public function clientDeals()
    {
        return $this->belongsToMany('App\Models\Deal', 'client_deals', 'client_id', 'deal_id');
    }

    public function clientApplications()
    {
        return $this->hasManyThrough(
            \App\Models\DealApplication::class,
            \App\Models\Deal::class,
            'id', // Foreign key on the deals table...
            'deal_id', // Foreign key on the deal_applications table...
            'id', // Local key on the clients table...
            'id' // Local key on the deals table...
        )->join('client_deals', 'client_deals.deal_id', 'deals.id')
        ->whereColumn('client_deals.client_id', 'clients.id');
    }

    public function EmployeeDocument()
    {
        return $this->hasOne('App\Models\EmployeeDocument', 'employee_id', 'id');
    }


      public static  function getEmployeeMeta($user_id,$key = 'all')
    {
        
        $metadata = EmployeeMeta::where('user_id', $user_id);
        if($key != 'all'){
            $metadata = $metadata->where('meta_key', $key);
        }   
        
        $metadata = $metadata->get();

        if ($metadata->isEmpty()) {
            return null; // Return null if no metadata found
        }

        $metas = new \stdClass(); // Create empty object

        foreach ($metadata as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;

            // Handle JSON values if stored as JSON strings
            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return $metas ? $metas : null;
    }

    public function employeeMetas()
    {
        return $this->hasMany(\App\Models\EmployeeMeta::class, 'user_id');
    }
}
