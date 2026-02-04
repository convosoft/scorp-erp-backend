<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\EmailMarkittingFileEmail;
use App\Models\EmailSendingQueue;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\UserEmailTemplate;
use App\Models\Utility;
use Illuminate\Http\Request;
use SplFileObject;
use App\Models\Pipeline;
use App\Models\Region;
use App\Models\SavedFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmailTemplateController extends Controller
{
    public function getEmailTemplatePluck(Request $request)
    {


        $emailTemplates = EmailTemplate::pluck('name', 'id')
                                    ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $emailTemplates
        ], 200);
    }

    public function getEmailTemplates()
    {
        if(!\Auth::user()->can('manage email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $emailTemplates = EmailTemplate::with(['created_by:id,name'])
                                    ->get();

        return response()->json([
            'status' => 'success',
            'data' => $emailTemplates
        ], 200);
    }

     public function getEmailTemplateDetail(Request $request)
    {
        if(!\Auth::user()->can('manage email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // $validator = \Validator::make(
        //     $request->all(),
        //     [
        //         'id' => 'required|exists:email_templates,id'
        //     ]
        // );
        $validator = \Validator::make($request->all(), [
            'id' => ['required', function ($attr, $value, $fail) {
                if ($value == -1) return; // ignore validation for -1
                if (!\DB::table('email_templates')->where('id', $value)->exists()) {
                    $fail('The selected id is invalid.');
                }
            }],
        ]);
        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $emailTemplate = EmailTemplateLang::where('parent_id', '=', $request->id)->where('lang', 'en')->first();

        return response()->json([
            'status' => 'success',
            'data' => $emailTemplate
        ], 200);
    }

    public function addEmailTemplate(Request $request)
    {
        if(!\Auth::user()->can('create email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 200);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string|unique:email_templates,name',
                'subject' => 'required|string',
                'template' => 'required|string',
                'type' => 'required|string'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 200);
        }

        $emailTemplate = new EmailTemplate();
        $emailTemplate->status = 1; // Default status is active
        $emailTemplate->name = $request->name;
        $emailTemplate->subject = $request->subject;
        $emailTemplate->template = $request->template;
        $emailTemplate->type = $request->type ;
        $emailTemplate->created_by = \Auth::user()->creatorId();
        $emailTemplate->save();

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $emailTemplate->name . " email template created",
                'message' => $emailTemplate->name . " email template created",
            ]),
            'module_id' => $emailTemplate->id,
            'module_type' => 'email_template',
            'notification_type' => 'Email Template Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Email template successfully created.'),
            'data' => $emailTemplate
        ], 200);
    }

    public function updateEmailTemplate(Request $request)
    {
        if(!\Auth::user()->can('edit email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 200);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:email_templates,id',
                'name' => 'required|string|unique:email_templates,name,'.$request->id.',id',
                'subject' => 'required|string',
                'template' => 'required|string',
                'variables' => 'nullable|array'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 200);
        }

        $emailTemplate = EmailTemplate::find($request->id);
        $originalData = $emailTemplate->toArray();

        $emailTemplate->name = $request->name;
        $emailTemplate->subject = $request->subject;
        $emailTemplate->template = $request->template;
        $emailTemplate->type = $request->type ;
        $emailTemplate->created_by = \Auth::user()->creatorId();
        $emailTemplate->save();

        $changes = [];
        foreach ($originalData as $key => $value) {
            if ($emailTemplate->$key != $value && !in_array($key, ['created_at', 'updated_at'])) {
                $changes[$key] = [
                    'old' => $value,
                    'new' => $emailTemplate->$key
                ];
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $emailTemplate->name . " email template updated",
                    'message' => 'Fields updated: ' . implode(', ', array_keys($changes)),
                    'changes' => $changes
                ]),
                'module_id' => $emailTemplate->id,
                'module_type' => 'email_template',
                'notification_type' => 'Email Template Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Email template successfully updated.'),
            'data' => $emailTemplate
        ], 200);
    }

    public function deleteEmailTemplate(Request $request)
    {
        if(!\Auth::user()->can('delete email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 200);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:email_templates,id,created_by,' . \Auth::user()->creatorId()
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 200);
        }

        $emailTemplate = EmailTemplate::find($request->id);
        $emailTemplateName = $emailTemplate->name;
        $emailTemplateId = $emailTemplate->id;

        $emailTemplate->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $emailTemplateName . " email template deleted",
                'message' => $emailTemplateName . " email template deleted"
            ]),
            'module_id' => $emailTemplateId,
            'module_type' => 'email_template',
            'notification_type' => 'Email Template Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Email template successfully deleted.')
        ], 200);
    }

    public function email_template_submit_to_queue(Request $request)
    {
        ini_set('memory_limit', '1024M');
        if ($request->type == 'leads') {
            $leadIds = explode(',', $request->Leads);
            $leads = collect(); // Empty collection to merge results
            foreach (array_chunk($leadIds, 1000) as $chunkIds) {
                $chunkLeads = Lead::select(
                    'leads.id',
                    'leads.name',
                    'leads.email',
                    'leads.phone',
                    'lead_stages.name as StageName',
                    'users.name as BrandName',
                    'branches.name as BranchName',
                    'assigned_to.name as AssignedName',
                    'pipelines.name as PipelinesName',
                    'regions.name as RegionName',
                    'leads.subject as LeadSubject',
                    'leads.brand_id',
                    'leads.branch_id',
                    'leads.region_id',
                    'leads.user_id as sender_id',
                    'leads.stage_id',
                    'leads.pipeline_id'
                )
                    ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')
                    ->leftJoin('users', 'users.id', '=', 'leads.brand_id')
                    ->leftJoin('branches', 'branches.id', '=', 'leads.branch_id')
                    ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'leads.user_id')
                    ->leftJoin('pipelines', 'pipelines.id', '=', 'leads.pipeline_id')
                    ->leftJoin('regions', 'regions.id', '=', 'leads.region_id')
                    ->groupBy(
                        'leads.id',
                        'leads.name',
                        'leads.email',
                        'leads.phone',
                        'lead_stages.name',
                        'users.name',
                        'branches.name',
                        'assigned_to.name',
                        'pipelines.name',
                        'regions.name',
                        'leads.subject',
                        'leads.brand_id',
                        'leads.branch_id',
                        'leads.region_id',
                        'leads.user_id',
                        'leads.stage_id',
                        'leads.pipeline_id'
                    )
                    ->whereIn('leads.id', $chunkIds)
                    ->get();

                $leads = $leads->merge($chunkLeads);
            }

            if ($leads->isNotEmpty()) {
                $batchSize = 1000; // Number of records to insert per batch
                $chunks = $leads->chunk($batchSize);

                foreach ($chunks as $chunk) {
                    $insertData = [];

                    foreach ($chunk as $lead) {
                        $replacedHtml = str_replace(
                            ['{lead_name}', '{lead_email}', '{lead_pipeline}', '{lead_stage}', '{lead_subject}', '{sender}', '{student_name}'],
                            [$lead->name, $lead->email, $lead->PipelinesName, $lead->StageName, $lead->LeadSubject, \Auth::user()->name, $lead->name],
                            $request->content
                        );

                        $insertData[] = [
                            'to' => $lead->email,
                            'subject' => $request->subject,
                            'created_by' => \Auth::id(),
                            'brand_id' => $lead->brand_id,
                            'from_email' => $request->from,
                            'branch_id' => $lead->branch_id,
                            'region_id' => $lead->region_id,
                            'sender_id' => \Auth::id(),
                            'content' => $replacedHtml,
                            'stage_id' => $lead->stage_id,
                            'pipeline_id' => $lead->pipeline_id,
                            'template_id' => $request->id,
                            'related_type' => 'lead',
                            'priority' => '3',
                            'related_id' => $lead->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    // Perform the batch insert
                    EmailSendingQueue::insert($insertData);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Your email campaign has been successfully established.'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have any Leads Records.'
                ]);
            }

        } elseif ($request->type == 'agency') {

            $EmailMarkittingAgencyEmails = Agency::whereIn('id', explode(',', $request->Leads))->get();

            if ($EmailMarkittingAgencyEmails->isNotEmpty() || $EmailMarkittingAgencyEmails->count() > 0) {
                foreach ($EmailMarkittingAgencyEmails as $EmailMarkitting) {
                    $replacedHtml = str_replace(
                        ['{lead_name}', '{lead_email}', '{lead_pipeline}', '{lead_stage}', '{lead_subject}'],
                        [$EmailMarkitting->name, $EmailMarkitting->organization_email, $EmailMarkitting->PipelinesName, $EmailMarkitting->StageName, $EmailMarkitting->LeadSubject],
                        $request->content
                    );

                    $insertData[] = [
                        'to' => $EmailMarkitting->organization_email,
                        'subject' => $request->subject,
                        'created_by' => \Auth::id(),
                        'brand_id' => $EmailMarkitting->brand_id,
                        'from_email' => $request->from,
                        'branch_id' => \Auth::user()->branch_id,
                        'region_id' => \Auth::user()->region_id,
                        'sender_id' => \Auth::id(),
                        'content' => $replacedHtml,
                        'stage_id' => $EmailMarkitting->stage_id,
                        'pipeline_id' => $EmailMarkitting->pipeline_id,
                        'template_id' => $request->id,
                        'related_type' => 'organization',
                         'priority' => '3',
                        'related_id' => $EmailMarkitting->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                // Perform the batch insert
                EmailSendingQueue::insert($insertData);
                return json_encode([
                    'status' => 'success',
                    'message' => 'Your email campaign has been successfully established.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'You do not have any Agency Records.'
                ]);
            }
        } elseif ($request->type == 'applications') {









$leadIds = explode(',', $request->Leads);
$users = collect();

foreach (array_chunk($leadIds, 1000) as $chunkIds) {

    $chunkUsers = User::select(
        'users.id',
        'users.name',
        'users.email',
        'users.phone',
        'brands.name as BrandName',
        'branches.name as BranchName',
        'regions.name as RegionName',
        'users.brand_id',
        'users.branch_id',
        'users.region_id',
    )

        // FIXED — changed alias to brands
        ->leftJoin('users as brands', 'brands.id', '=', 'users.brand_id')
        ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
        ->leftJoin('regions', 'regions.id', '=', 'users.region_id')
        ->whereIn('users.id', $chunkIds)
        ->get();

    $users = $users->merge($chunkUsers);
}

if ($users->isNotEmpty()) {

    foreach ($users->chunk(1000) as $chunk) {

        $insertData = [];

        foreach ($chunk as $lead) {

            // FIXED — use $lead not $users
            $replacedHtml = str_replace(
                ['{lead_name}', '{lead_email}', '{lead_pipeline}', '{lead_stage}', '{lead_subject}', '{sender}', '{student_name}'],
                [
                    $lead->name,
                    $lead->email,
                    $lead->PipelinesName,
                    $lead->StageName,
                    $lead->userSubject,
                    auth()->user()->name,
                    $lead->name
                ],
                $request->content
            );

            $insertData[] = [
                'to' => $lead->email,
                'subject' => $request->subject,
                'created_by' => auth()->id(),
                'brand_id' => $lead->brand_id,
                'from_email' => $request->from,
                'branch_id' => $lead->branch_id,
                'region_id' => $lead->region_id,
                'sender_id' => auth()->id(),
                'content' => $replacedHtml,
                'stage_id' => $lead->stage_id,
                'pipeline_id' => $lead->pipeline_id,
                'template_id' => $request->id,
                'related_type' => 'applications',
                'priority' => '3',
                'related_id' => $lead->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        EmailSendingQueue::insert($insertData);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Your email campaign has been successfully established.'
    ]);
}

return response()->json([
    'status' => 'error',
    'message' => 'You do not have any applications Records.'
]);






















        }elseif ($request->type == 'organization') {
            $EmailMarkittingOrgEmails = User::select(['users.*'])->join('organizations', 'organizations.user_id', '=', 'users.id')->where('users.type', 'organization')->whereIn('users.id', explode(',', $request->Leads))->get();
            if ($EmailMarkittingOrgEmails->isNotEmpty() || $EmailMarkittingOrgEmails->count() > 0) {
                foreach ($EmailMarkittingOrgEmails as $EmailMarkitting) {
                    $replacedHtml = str_replace(
                        ['{lead_name}', '{lead_email}', '{lead_pipeline}', '{lead_stage}', '{lead_subject}'],
                        [$EmailMarkitting->name, $EmailMarkitting->email, $EmailMarkitting->PipelinesName, $EmailMarkitting->StageName, $EmailMarkitting->LeadSubject],
                        $request->content
                    );

                    $insertData[] = [
                        'to' => $EmailMarkitting->email,
                        'subject' => $request->subject,
                        'created_by' => \Auth::id(),
                        'brand_id' => $EmailMarkitting->brand_id,
                        'from_email' => $request->from,
                        'branch_id' => \Auth::user()->branch_id,
                        'region_id' => \Auth::user()->region_id,
                        'sender_id' => \Auth::id(),
                        'content' => $replacedHtml,
                        'stage_id' => $EmailMarkitting->stage_id,
                        'pipeline_id' => $EmailMarkitting->pipeline_id,
                        'template_id' => $request->id,
                        'related_type' => 'organization',
                         'priority' => '3',
                        'related_id' => $EmailMarkitting->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                // Perform the batch insert
                EmailSendingQueue::insert($insertData);
                return json_encode([
                    'status' => 'success',
                    'message' => 'Your email campaign has been successfully established.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'You do not have any Organizations Records.'
                ]);
            }
        } elseif ($request->type == 'import') {

            $EmailMarkittingFileEmails = EmailMarkittingFileEmail::where('created_by', \Auth::id())->whereIn('id', explode(',', $request->Leads))->get();
            if ($EmailMarkittingFileEmails->isNotEmpty() || $EmailMarkittingFileEmails->count() > 0) {
                foreach ($EmailMarkittingFileEmails as $EmailMarkitting) {
                    $replacedHtml = str_replace(
                        ['{lead_name}', '{lead_email}', '{lead_pipeline}', '{lead_stage}', '{lead_subject}'],
                        [$EmailMarkitting->name, $EmailMarkitting->email, $EmailMarkitting->PipelinesName, $EmailMarkitting->StageName, $EmailMarkitting->LeadSubject],
                        $request->content
                    );

                    $insertData[] = [
                        'to' => $EmailMarkitting->email,
                        'subject' => $request->subject,
                        'created_by' => \Auth::id(),
                        'brand_id' => $EmailMarkitting->brand_id,
                        'from_email' => $request->from,
                        'branch_id' => \Auth::user()->branch_id,
                        'region_id' => \Auth::user()->region_id,
                        'sender_id' => \Auth::id(),
                        'content' => $replacedHtml,
                        'stage_id' => $EmailMarkitting->stage_id,
                        'pipeline_id' => $EmailMarkitting->pipeline_id,
                        'template_id' => $request->id,
                        'related_type' => 'file_import',
                         'priority' => '3',
                        'related_id' => $EmailMarkitting->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $EmailMarkitting->delete();
                }

                // Perform the batch insert
                EmailSendingQueue::insert($insertData);
                return json_encode([
                    'status' => 'success',
                    'message' => 'Your email campaign has been successfully established.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'You do not have any Import Records.'
                ]);
            }
        }
    }
private function executeLeadQuery()
{
    $usr = \Auth::user();

    // Pagination calculation
    $start = 0;
    if (!empty($_GET['perPage'])) {
        $num_results_on_page = $_GET['perPage'];
    } else {
        $num_results_on_page = env("RESULTS_ON_PAGE", 50);
    }
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
        $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
        $start = ($page - 1) * $num_results_on_page;
    } else {
        $num_results_on_page = isset($_GET['num_results_on_page']) ? $_GET['num_results_on_page'] : $num_results_on_page;
    }

    // Filters
    $filters = $this->leadsFilter();

    if ($usr->can('view email marketing queue') || $usr->can('view email marketing queue') || \Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team') {

        $pipeline = Pipeline::first();

        // Initialize variables
        $companies = FiltersBrands();
        $brand_ids = array_keys($companies);

        // Build the leads query with complete email statistics
        $subquery = \DB::table('email_sending_queues')
            ->select(
                'subject',
                'sender_id',
                \DB::raw('MIN(id) as id'),
                \DB::raw('SUM(CASE WHEN is_send = \'0\' THEN 1 ELSE 0 END) as count_status_0'),
                \DB::raw('SUM(CASE WHEN is_send = \'1\' THEN 1 ELSE 0 END) as count_status_1'),
                // Email engagement statistics
                \DB::raw('COUNT(*) as total_emails'),
                \DB::raw('SUM(CASE WHEN is_send = \'1\' THEN 1 ELSE 0 END) as sent_emails'),
                \DB::raw('SUM(CASE WHEN processed_at IS NOT NULL THEN 1 ELSE 0 END) as processed_emails'),
                \DB::raw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered_emails'),
                \DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_emails'),
                \DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked_emails'),
                \DB::raw('SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced_emails'),
                // Complete engagement rates
                \DB::raw('ROUND((SUM(CASE WHEN processed_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as processing_rate'),
                \DB::raw('ROUND((SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN is_send = \'1\' THEN 1 ELSE 0 END), 0)), 2) as delivery_rate'),
                \DB::raw('ROUND((SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END), 0)), 2) as open_rate'),
                \DB::raw('ROUND((SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END), 0)), 2) as click_rate'),
                \DB::raw('ROUND((SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN is_send = \'1\' THEN 1 ELSE 0 END), 0)), 2) as bounce_rate'),
                \DB::raw('ROUND((SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END), 0)), 2) as click_to_open_rate')
            )
            ->groupBy('subject', 'sender_id');

        // Use DB::raw to wrap the subquery
        $email_sending_queues_query = DB::table(DB::raw("({$subquery->toSql()}) as sq"))
            ->mergeBindings($subquery)
            ->whereExists(function ($q) use ($brand_ids) {
                $q->select(DB::raw(1))
                    ->from('email_sending_queues as esq')
                    ->whereColumn('sq.subject', 'esq.subject')
                    ->whereColumn('sq.sender_id', 'esq.sender_id')
                    ->where('esq.priority', 3);

                if (!empty($_GET['Assigned'])) {
                    $q->whereNotNull('esq.sender_id');
                }
                if (!empty($_GET['Unassigned'])) {
                    $q->whereNull('esq.sender_id');
                }

                // Apply user type-based filtering
                $userType = \Auth::user()->type;
                if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
                    // No additional filtering needed
                } elseif ($userType === 'company') {
                    $q->where('esq.brand_id', \Auth::user()->id);
                } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
                    $q->whereIn('esq.brand_id', $brand_ids);
                } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
                    $q->where('esq.region_id', \Auth::user()->region_id);
                } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || (\Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id))) {
                    $q->where('esq.branch_id', \Auth::user()->branch_id);
                } else {
                    $q->where('esq.sender_id', \Auth::user()->id);
                }

                // Apply dynamic filters
                $filters = $this->leadsFilter();
                foreach ($filters as $column => $value) {
                    switch ($column) {
                        case 'name':
                            $q->whereIn('esq.id', $value);
                            break;
                        case 'brand_id':
                            $q->where('esq.brand_id', $value);
                            break;
                        case 'region_id':
                            $q->where('esq.region_id', $value);
                            break;
                        case 'branch_id':
                            $q->where('esq.branch_id', $value);
                            break;
                        case 'stage_id':
                            $q->whereIn('stage_id', $value);
                            break;
                        case 'lead_assigned_user':
                            if ($value == null) {
                                $q->whereNull('esq.sender_id');
                            } else {
                                $q->where('esq.sender_id', $value);
                            }
                            break;
                        case 'users':
                            $q->whereIn('esq.sender_id', $value);
                            break;
                        case 'status':
                            // FIXED: Check if status column exists, if not, use is_send or another column
                            // Option 1: If status column doesn't exist, remove this filter
                            // Option 2: Use is_send column instead
                            if (Schema::hasColumn('email_sending_queues', 'status')) {
                                if ($value == 'nonprocessed') {
                                    $q->where('esq.status', '1');
                                    $q->where('esq.is_send', '0');
                                } else if ($value == 'failed') {
                                    $q->where('esq.status', '1');
                                    $q->where('esq.is_send', '2');
                                } else {
                                    $q->where('esq.status', $value);
                                }
                            }
                            break;
                        case 'nonprocessed':
                            // FIXED: Use is_send column instead of status
                            $q->where('esq.is_send', '0');
                            break;
                        case 'failed':
                            // FIXED: Use is_send column instead of status
                            $q->where('esq.is_send', '2');
                            break;
                        case 'created_at_from':
                            $q->whereDate('esq.created_at', '>=', $value);
                            break;
                        case 'created_at_to':
                            $q->whereDate('esq.created_at', '<=', $value);
                            break;
                        case 'search':
                            $q
                                ->where(function($qq) use ($value) {
                                    $qq->where('esq.subject', 'like', "%{$value}%")
                                        ->orWhere('esq.content', 'like', "%{$value}%")
                                        ->orWhere('esq.to', 'like', "%{$value}%");
                                });
                            break;
                        case 'tag':
                            $q->whereRaw('FIND_IN_SET(?, esq.tag_ids)', [$value]);
                            break;
                        // Add email engagement filters
                        case 'delivery_status':
                            if ($value === 'delivered') {
                                $q->whereNotNull('esq.delivered_at');
                            } elseif ($value === 'not_delivered') {
                                $q->whereNull('esq.delivered_at')->where('esq.is_send', '1');
                            }
                            break;
                        case 'open_status':
                            if ($value === 'opened') {
                                $q->whereNotNull('esq.opened_at');
                            } elseif ($value === 'not_opened') {
                                $q->whereNull('esq.opened_at')->whereNotNull('esq.delivered_at');
                            }
                            break;
                        case 'bounce_status':
                            if ($value === 'bounced') {
                                $q->whereNotNull('esq.bounced_at');
                            } elseif ($value === 'not_bounced') {
                                $q->whereNull('esq.bounced_at')->where('esq.is_send', '1');
                            }
                            break;
                        case 'processing_status':
                            if ($value === 'processed') {
                                $q->whereNotNull('esq.processed_at');
                            } elseif ($value === 'not_processed') {
                                $q->whereNull('esq.processed_at');
                            }
                            break;
                    }
                }
            })
            ->select(
                'sq.id',
                'sq.subject',
                'sq.sender_id',
                'sq.total_emails',
                'sq.sent_emails',
                'sq.processed_emails',
                'sq.delivered_emails',
                'sq.opened_emails',
                'sq.clicked_emails',
                'sq.bounced_emails',
                'sq.processing_rate',
                'sq.delivery_rate',
                'sq.open_rate',
                'sq.click_rate',
                'sq.bounce_rate',
                'sq.click_to_open_rate'
            );

        if (!empty($_GET['Assigned'])) {
            $email_sending_queues_query->whereNotNull('esq.sender_id');
        }
        if (!empty($_GET['Unassigned'])) {
            $email_sending_queues_query->whereNull('esq.sender_id');
        }

        // Apply user type-based filtering
        $userType = \Auth::user()->type;
        if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
            // No additional filtering needed
        } elseif ($userType === 'company') {
            $email_sending_queues_query->where('esq.brand_id', \Auth::user()->id);
        } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
            $email_sending_queues_query->whereIn('esq.brand_id', $brand_ids);
        } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
            $email_sending_queues_query->where('esq.region_id', \Auth::user()->region_id);
        } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || (\Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id))) {
            $email_sending_queues_query->where('esq.branch_id', \Auth::user()->branch_id);
        } else {
            $email_sending_queues_query->where('esq.sender_id', \Auth::user()->id);
        }

         $email_sending_queues_query->where('esq.priority', '3');

        // Apply dynamic filters
        foreach ($filters as $column => $value) {
            switch ($column) {
                case 'name':
                    $email_sending_queues_query->whereIn('esq.id', $value);
                    break;
                case 'brand_id':
                    $email_sending_queues_query->where('esq.brand_id', $value);
                    break;
                case 'region_id':
                    $email_sending_queues_query->where('esq.region_id', $value);
                    break;
                case 'branch_id':
                    $email_sending_queues_query->where('esq.branch_id', $value);
                    break;
                case 'stage_id':
                    $email_sending_queues_query->whereIn('stage_id', $value);
                    break;
                case 'lead_assigned_user':
                    if ($value == null) {
                        $email_sending_queues_query->whereNull('esq.sender_id');
                    } else {
                        $email_sending_queues_query->where('esq.sender_id', $value);
                    }
                    break;
                case 'users':
                    $email_sending_queues_query->whereIn('esq.sender_id', $value);
                    break;
                case 'status':

                    if ($value == 'nonprocessed') {
                        $email_sending_queues_query->where('esq.status', '1');
                        $email_sending_queues_query->where('esq.is_send', '0');
                    } else if ($value == 'failed') {
                        $email_sending_queues_query->where('esq.status', '1');
                        $email_sending_queues_query->where('esq.is_send', '2');
                    } else {
                        $email_sending_queues_query->where('esq.status', $value);
                    }
                    break;
                case 'nonprocessed':
                    $email_sending_queues_query->where('esq.status', '1');
                    $email_sending_queues_query->where('esq.is_send', '0');
                    break;
                case 'failed':
                    $email_sending_queues_query->where('esq.status', '1');
                    $email_sending_queues_query->where('esq.is_send', '2');
                    break;
                case 'created_at_from':
                    $email_sending_queues_query->whereDate('esq.created_at', '>=', $value);
                    break;
                case 'created_at_to':
                    $email_sending_queues_query->whereDate('esq.created_at', '<=', $value);
                    break;
                case 'search':
                    $email_sending_queues_query
                        ->where(function($q) use ($value) {
                            $q->where('esq.subject', 'like', "%{$value}%")
                              ->orWhere('esq.content', 'like', "%{$value}%")
                              ->orWhere('esq.to', 'like', "%{$value}%");
                        });
                    break;
                case 'tag':
                    $email_sending_queues_query->whereRaw('FIND_IN_SET(?, esq.tag_ids)', [$value]);
                    break;
                // Add email engagement filters
                case 'delivery_status':
                    if ($value === 'delivered') {
                        $email_sending_queues_query->whereNotNull('esq.delivered_at');
                    } elseif ($value === 'not_delivered') {
                        $email_sending_queues_query->whereNull('esq.delivered_at')->where('esq.is_send', '1');
                    }
                    break;
                case 'open_status':
                    if ($value === 'opened') {
                        $email_sending_queues_query->whereNotNull('esq.opened_at');
                    } elseif ($value === 'not_opened') {
                        $email_sending_queues_query->whereNull('esq.opened_at')->whereNotNull('esq.delivered_at');
                    }
                    break;
                case 'bounce_status':
                    if ($value === 'bounced') {
                        $email_sending_queues_query->whereNotNull('esq.bounced_at');
                    } elseif ($value === 'not_bounced') {
                        $email_sending_queues_query->whereNull('esq.bounced_at')->where('esq.is_send', '1');
                    }
                    break;
                case 'processing_status':
                    if ($value === 'processed') {
                        $email_sending_queues_query->whereNotNull('esq.processed_at');
                    } elseif ($value === 'not_processed') {
                        $email_sending_queues_query->whereNull('esq.processed_at');
                    }
                    break;
            }
        }

        // Count total records and retrieve paginated email_sending_queues
        $total_records = $email_sending_queues_query->paginate($num_results_on_page)->total();
        $email_sending_queues = $email_sending_queues_query->orderBy('esq.created_at', 'desc')
            ->skip($start)
            ->limit($num_results_on_page)
            ->get();

        return [
            'total_records' => $total_records,
            'email_sending_queues' => $email_sending_queues,
            'companies' => $companies,
            'pipeline' => $pipeline,
            'num_results_on_page' => $num_results_on_page
        ];
    }
}
        private function leadsFilter()
    {
        $filters = [];
        if (isset($_GET['name']) && !empty($_GET['name'])) {
            $filters['name'] = $_GET['name'];
        }


        if (isset($_GET['stages']) && !empty($_GET['stages'])) {
            $filters['stage_id'] = $_GET['stages'];
        }

        if (isset($_GET['users']) && !empty($_GET['users'])) {
            $filters['users'] = $_GET['users'];
        }

        if (isset($_GET['lead_assigned_user']) && !empty($_GET['lead_assigned_user'])) {
            $filters['lead_assigned_user'] = $_GET['lead_assigned_user'];
        }

        if (isset($_GET['subject']) && !empty($_GET['subject'])) {
            $filters['subject'] = $_GET['subject'];
        }



        // if(isset($_GET['lead_assigned_user']) && !empty($_GET['lead_assigned_user']) && $_GET['lead_assigned_user'] != 'null'){
        if (isset($_GET['brand']) && !empty($_GET['brand'])) {
            $filters['brand_id'] = $_GET['brand'];
        }

        if (isset($_GET['region_id']) && !empty($_GET['region_id'])) {
            $filters['region_id'] = $_GET['region_id'];
        }

        if (isset($_GET['branch_id']) && !empty($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
        }
        if (isset($_GET['tag']) && !empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }
        //}
        if (isset($_GET['lead_assigned_user']) && $_GET['lead_assigned_user'] == 'null') {
            unset($filters['brand_id']);
            unset($filters['region_id']);
            unset($filters['branch_id']);
        }


        if (isset($_GET['created_at_from']) && !empty($_GET['created_at_from'])) {
            $filters['created_at_from'] = $_GET['created_at_from'];
        }

        if (isset($_GET['created_at_to']) && !empty($_GET['created_at_to'])) {
            $filters['created_at_to'] = $_GET['created_at_to'];
        }

        if (isset($_GET['tag']) && !empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }

         if ($_GET['status'] != '') {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        return $filters;
    }
    public function email_marketing_queue(Request $request)
    {
        try {
            if (\Auth::user()->type == 'Agent') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission Denied.'
                ], 403);
            }

            // Page and pagination setup
            $current_page = $request->input('page', 1);
            $per_page = $request->input('perPage', 50);

            // Fetch executed data
            $executed_data = $this->executeLeadQuery();

            // ✅ Get total records directly from query (accurate count)
            $total_records = (int) $executed_data['total_records'];
            $emailQueues = collect($executed_data['email_sending_queues']);

            // ✅ Calculate last page using total records (not sliced data)
            $last_page = max(1, ceil($total_records / $per_page));

            return response()->json([
                'status' => 'success',
                'data' => $emailQueues->values(),
                'total_records' => $total_records,
                'current_page' => (int) $current_page,
                'last_page' => (int) $last_page,
                'perPage' => (int) $per_page,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function email_marketing_queue_detail(Request $request)
    {
        try {
            // Fetch single email queue record
            $emailQueue = EmailSendingQueue::select(
                    'email_sending_queues.id as id',
                    'email_sending_queues.status as status',
                    'email_sending_queues.rejectapprovecoment as reason',
                    'email_sending_queues.brand_id',
                    'email_sending_queues.created_at',
                    'email_sending_queues.updated_at',
                    'email_sending_queues.content',
                    'email_sending_queues.to',
                    'email_sending_queues.branch_id',
                    'email_sending_queues.region_id',
                    'email_sending_queues.sender_id',
                    'email_sending_queues.stage_id',
                    'brands.name as brand_name',
                    'branches.name as branch_name',
                    'regions.name as region_name',
                    'email_sending_queues.from_email',
                    'assigned_to.name as sender_name',
                    'lead_stages.name as stage_name'
                )
                ->leftJoin('lead_stages', 'email_sending_queues.stage_id', '=', 'lead_stages.id')
                ->leftJoin('users', 'users.id', '=', 'email_sending_queues.brand_id')
                ->leftJoin('branches', 'branches.id', '=', 'email_sending_queues.branch_id')
                ->leftJoin('regions', 'regions.id', '=', 'email_sending_queues.region_id')
                ->leftJoin('users as brands', 'brands.id', '=', 'email_sending_queues.brand_id')
                ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'email_sending_queues.sender_id')
                ->where('email_sending_queues.id', $request->id)
                ->first();

            // If not found
            if (!$emailQueue) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email queue not found.',
                ], 404);
            }

            // Return formatted JSON response
            return response()->json([
                'status' => 'success',
                'data' =>  $emailQueue,
            200]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function email_marketing_approved_reject(Request $request)
    {
        try {
            $id = $request->id; // Get the ID from request

            // Fetch single email queue record
            $record = \DB::table('email_sending_queues')->where('id', $id)->first();

            // If not found
            if (!$record) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email queue not found.',
                ], 404);
            }

            // Update all related records with same subject and sender_id
            \DB::table('email_sending_queues')
                ->where('subject', $record->subject)
                ->where('sender_id', $record->sender_id)
                ->update([
                    'status' => $request->status,
                    'rejectapprovecoment' => $request->reason
                ]);


            // Return formatted JSON response
            return response()->json([
                'status' => 'success',
                'data' => [],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    private function excelSheetDataSaved($request, $file)
    {
        $usr = \Auth::user();
        $column_arr = [];
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $key = 0;
        $createdIds = []; // 🆕 store created record IDs

        // Extract column mapping
        foreach ($worksheet->getRowIterator() as $line) {
            if ($key == 0) {
                foreach ($line->getCellIterator() as $column_key => $cell) {
                    $column_value = trim(preg_replace('/[^\x20-\x7E]/', '', $cell->getValue()));

                    // Check if header name exists in POST
                    if (empty($_POST['columns'][$column_value])) {

                        // Auto-detect email header
                        $column_lower = strtolower($column_value);
                        if (
                            str_contains($column_lower, 'email') ||
                            str_contains($column_lower, 'e-mail') ||
                            str_contains($column_lower, 'mail')
                        ) {
                            $column_arr[$column_key] = 'email';
                        }

                        continue;
                    }

                    // Keep your original logic
                    $column_arr[$column_key] = $_POST['columns'][$column_value];
                }
                $key++;
                continue;
            }

            $EmailMarkittingFileEmail = new EmailMarkittingFileEmail();
            $test = [];

            foreach ($line->getCellIterator() as $column_key => $cell) {
                $column_value = trim(preg_replace('/[^\x20-\x7E]/', '', $cell->getValue()));

                // Keep old logic but include auto-detected email columns
                if (!empty($column_arr[$column_key]) && $column_arr[$column_key] === 'email') {
                    $test['email'] = str_replace('"', '', $column_value);
                }
            }

            if (filter_var($test['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $EmailMarkittingFileEmail_exist = EmailMarkittingFileEmail::where('email', $test['email'])->first();
                if ($EmailMarkittingFileEmail_exist) {
                    continue;
                }
                $EmailMarkittingFileEmail->email = $test['email'];
            } else {
                $EmailMarkittingFileEmail->email = 'N/A';
            }

            if (!empty($test['email'])) {
                $EmailMarkittingFileEmail->created_by = $usr->id;
                $EmailMarkittingFileEmail->save();

                // 🆕 collect ID after save
                $createdIds[] = $EmailMarkittingFileEmail->id;
            }
        }

        // 🆕 Return comma-separated IDs only
        return implode(',', $createdIds);
    }

    function getFileDelimiter($file, $checkLines = 2)
    {
        $file = new SplFileObject($file);
        $delimiters = array(
            ",",
            "\t",
            ";",
            "|",
            ":"
        );
        $results = array();
        $i = 0;
        while ($file->valid() && $i <= $checkLines) {
            $line = $file->fgets();
            foreach ($delimiters as $delimiter) {
                $regExp = '/[' . $delimiter . ']/';
                $fields = preg_split($regExp, $line);
                if (count($fields) > 1) {
                    if (!empty($results[$delimiter])) {
                        $results[$delimiter]++;
                    } else {
                        $results[$delimiter] = 1;
                    }
                }
            }
            $i++;
        }
        $results = array_keys($results, max($results));
        return $results[0];
    }
    private function csvSheetDataSaved($request, $file)
    {
        $usr = \Auth::user();
        $column_arr = [];
        $handle = fopen($file->getPathname(), 'r');
        $key = 0;
        $createdIds = []; // <-- Store created record IDs here

        while ($line = fgets($handle)) {

            // Remove BOM
            if (substr($line, 0, 3) == pack('CCC', 0xEF, 0xBB, 0xBF)) {
                $line = substr($line, 3);
            }

            // Clean encoding & special chars
            $clean_line = str_replace("\x00", '', $line);
            $clean_line = utf8_encode(utf8_decode($clean_line));
            $clean_line = str_replace('??', '', $clean_line);

            $delimiter = $this->getFileDelimiter($file, 1);
            $line = explode($delimiter, $clean_line);

            // First row: map column headers
            if ($key == 0) {
                foreach ($line as $column_key => $column) {
                    $column = trim(preg_replace('/[^\x20-\x7E]/', '', $column));
                    $column_lower = strtolower($column);

                    // Normalize possible email column names
                    if (
                        str_contains($column_lower, 'email') ||
                        str_contains($column_lower, 'e-mail') ||
                        str_contains($column_lower, 'mail')
                    ) {
                        $column_arr[$column_key] = 'email';
                    }
                }
                $key++;
                continue;
            }

            $EmailMarkittingFileEmail = new EmailMarkittingFileEmail();
            $test = [];

            // Read actual data rows
            foreach ($line as $column_key => $column) {
                $column = trim(preg_replace('/[^\x20-\x7E]/', '', $column));

                if (!empty($column_arr[$column_key]) && $column_arr[$column_key] === 'email') {
                    $test['email'] = str_replace('"', '', $column);
                }
            }

            // Validate and save email
            if (!empty($test['email'])) {
                $emailValue = trim($test['email']);

                if (filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                    $exists = EmailMarkittingFileEmail::where('email', $emailValue)->exists();
                    if ($exists) {
                        continue;
                    }
                    $EmailMarkittingFileEmail->email = $emailValue;
                } else {
                    $EmailMarkittingFileEmail->email = 'N/A';
                }

                $EmailMarkittingFileEmail->created_by = $usr->id;
                $EmailMarkittingFileEmail->save();

                // Collect newly created ID
                $createdIds[] = $EmailMarkittingFileEmail->id;
            }
        }

        fclose($handle);

        // Return all IDs as a comma-separated string
        return implode(',', $createdIds);
    }


    public function fetchColumns(Request $request)
    {
        $usr = \Auth::user();

            $file = $request->file('leads_file');

            $column_arr = [];

            $file = $request->file('leads_file');
            $extension = $file->getClientOriginalExtension();
            if ($extension == 'csv') {
                $response = $this->csvSheetDataSaved($request, $file);
            } else {
                $response =  $this->excelSheetDataSaved($request, $file);
            }

            if (!empty($response)) {
                // Check if contains IDs (like 43,5566,767)
                if (preg_match('/\d+(,\d+)*/', $response)) {
                    return response()->json([
                        'status' => 'success',
                        'LeadsId' => $response,
                        'message' => 'Import successfully created!',
                    ], 200);
                } else {
                    // Any other unexpected value
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Something went wrong.',
                    ], 500);
                }
            } else {
                // Empty response → already exist
                return response()->json([
                    'status' => 'error',
                    'message' => 'Import already exists!',
                ], 200);
            }

    }
}
