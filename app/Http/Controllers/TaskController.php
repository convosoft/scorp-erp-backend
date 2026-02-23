<?php

namespace App\Http\Controllers;

use Session;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Models\Label;
use App\Models\Stage;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Region;
use App\Models\Source;
use App\Models\Country;
use App\Models\Utility;
use App\Models\DealCall;
use App\Models\DealFile;
use App\Models\DealNote;
use App\Models\DealTask;
use App\Models\Pipeline;
use App\Models\UserDeal;
use App\Models\DealEmail;
use App\Models\ClientDeal;
use App\Models\University;
use App\Mail\SendDealEmail;
use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\SavedFilter;
use App\Models\Notification;
use App\Models\StageHistory;
use Illuminate\Http\Request;
use App\Models\DealDiscussion;
use App\Models\ProductService;
use App\Models\TaskDiscussion;
use App\Events\NewNotification;
use App\Models\Agency;
use App\Models\DealApplication;
use App\Models\ApplicationStage;
use App\Models\ClientPermission;
use App\Models\CompanyPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\ApplicationNote;
use App\Models\instalment;
use App\Models\LeadTag;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{


    private function TasksFilter()
    {
        $filters = [];
        if (isset($_POST['subjects']) && !empty($_POST['subjects'])) {
            $filters['subjects'] = $_POST['subjects'];
        }

        if (isset($_POST['assigned_to']) && !empty($_POST['assigned_to'])) {
            $filters['assigned_to'] = $_POST['assigned_to'];
        }

        if (isset($_POST['brand']) && !empty($_POST['brand'])) {
            $filters['brand_id'] = $_POST['brand'];
        }

        if (isset($_POST['region_id']) && !empty($_POST['region_id'])) {
            $filters['region_id'] = $_POST['region_id'];
        }

        if (isset($_POST['branch_id']) && !empty($_POST['branch_id'])) {
            $filters['branch_id'] = $_POST['branch_id'];
        }

        if (isset($_POST['due_date']) && !empty($_POST['due_date'])) {
            $filters['due_date'] = $_POST['due_date'];
        }
        if (isset($_POST['status']) && $_POST['status'] !== '') {
            if ($_POST['status'] != '0') {
                $filters['status'] = $_POST['status'];
            } else {
                $filters['status'] = 0;
            }
        }

        if (isset($_POST['created_at_from']) && !empty($_POST['created_at_from'])) {
            $filters['created_at_from'] = $_POST['created_at_from'];
        }

        if (isset($_POST['created_at_to']) && !empty($_POST['created_at_to'])) {
            $filters['created_at_to'] = $_POST['created_at_to'];
        }

        if (isset($_POST['university_id']) && !empty($_POST['university_id'])) {
            $filters['university_id'] = $_POST['university_id'];
        }

        return $filters;
    }
    public function GetScorpTasks()
    {
        $brandId = 3751; // Assuming 3751 is the brand ID to filter

        if (\Auth::user()->can('view task') || \Auth::user()->can('manage task') || \Auth::user()->type == 'super admin' || \Auth::user()->type == 'company') {
            $tasks = DealTask::select(
                    'deal_applications.university_id',
                    'deal_tasks.stage_request',
                    'deal_tasks.name',
                    'deal_tasks.brand_id',
                    'deal_tasks.id',
                    'deal_tasks.due_date',
                    'deal_tasks.status',
                    'deal_tasks.assigned_to'
                )
                ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
                ->join('users as brand', 'brand.id', '=', 'deal_tasks.brand_id')
                ->leftJoin('deal_applications', function ($join) {
                    $join->on('deal_applications.id', '=', 'deal_tasks.related_to')
                         ->where('deal_tasks.related_type', '=', 'application');
                })
                ->where('deal_tasks.brand_id', $brandId)
                ->where('deal_tasks.created_by', \Auth::user()->id)

            ->leftJoin('universities', 'universities.id', '=', 'deal_applications.university_id');

            // Filter by origin country if provided
            if (isset($_POST['country']) && !empty($_POST['country'])) {
                $country = $_POST['country'];

                // Fetch country details
                $country_code = Country::where('country_code', $country)->first();

                if ($country_code) {
                    $tasks->where('uni_status', '0')
                               ->whereRaw("FIND_IN_SET(?, country)", [$country_code->name]);
                }
            }

            $filters = $this->TasksFilter();

            foreach ($filters as $column => $value) {
                if ($column === 'subjects') {
                    if (is_array($value) && count($value) > 0) {
                        $chunks = array_chunk($value, 500);
                        $tasks->where(function($query) use ($chunks) {
                            foreach ($chunks as $chunk) {
                                $query->orWhereIn('deal_tasks.id', $chunk);
                            }
                        });
                    }
                } elseif ($column === 'assigned_to') {
                    $tasks->where('deal_tasks.assigned_to', $value);
                } elseif ($column === 'brand_id') {
                    $tasks->where('deal_tasks.brand_id', $value);
                } elseif ($column === 'region_id') {
                    $tasks->where('deal_tasks.region_id', $value);
                } elseif ($column === 'university_id') {
                    $tasks->where('deal_applications.university_id', $value);
                } elseif ($column === 'branch_id') {
                    $tasks->where('deal_tasks.branch_id', $value);
                } elseif ($column === 'due_date') {
                    $tasks->whereDate('deal_tasks.due_date', $value);
                } elseif ($column === 'status') {
                    if (is_array($value)) {
                        if (in_array(2, $value)) {
                            $tasks->where('deal_tasks.status', 0)
                                  ->whereDate('deal_tasks.due_date', '<', now());
                        } else {
                            $tasks->whereIn('deal_tasks.status', $value);
                        }
                    } else {
                        if ($value == 2) {
                            $tasks->where('deal_tasks.status', 0)
                                  ->whereDate('deal_tasks.due_date', '<', now());
                        } else {
                            $tasks->where('deal_tasks.status', $value);
                        }
                    }
                } elseif ($column === 'created_at_from') {
                    $tasks->whereDate('deal_tasks.created_at', '>=', $value);
                } elseif ($column === 'created_at_to') {
                    $tasks->whereDate('deal_tasks.created_at', '<=', $value);
                }
            }

            if (!empty($_POST['task_type'])) {
                $tasks->where('deal_tasks.tasks_type', $_POST['task_type']);
            }

            if (!empty($_POST['tasks_type_status'])) {
                $status = $_POST['tasks_type_status'];
                if ($status == '1') {
                    $tasks->where('deal_tasks.tasks_type_status', "1")
                          ->where('deal_tasks.status', 1);
                } elseif ($status == '2') {
                    $tasks->where('deal_tasks.tasks_type_status', "2");
                } else {
                    $tasks->where('deal_tasks.tasks_type_status', "0");
                }
            } elseif (!isset($_POST['status'])) {
                $tasks->where('deal_tasks.status', 0)
                      ->where('deal_tasks.tasks_type_status', "0");
            }

            return $tasks->pluck('deal_tasks.id')->toArray();
        }

        return [];
    }
    public function userTasksGet(Request $request)
    {
        // Pagination setup
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);
        $start = ($page - 1) * $perPage;

        if (\Auth::user()->can('view task') || \Auth::user()->can('manage task') ||
            \Auth::user()->type == 'super admin' || \Auth::user()->type == 'company') {

            // Base query for tasks
            $tasksQuery = DealTask::select(
                'deal_applications.university_id',
                'deal_tasks.stage_request',
                'deal_tasks.name',
                'deal_tasks.brand_id',
                'deal_tasks.id',
                'deal_tasks.due_date',
                'deal_tasks.status',
                'deal_tasks.assigned_to'
            )
            ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
            ->join('users as brand', 'brand.id', '=', 'deal_tasks.brand_id')
            ->leftJoin('deal_applications', function ($join) {
                $join->on('deal_applications.id', '=', 'deal_tasks.related_to')
                     ->where('deal_tasks.related_type', '=', 'application');
            })
            ->leftJoin('universities', 'universities.id', '=', 'deal_applications.university_id');

            // Filter by origin country if provided
            if ($request->has('country') && !empty($request->country)) {
                $country = $request->country;

                // Fetch country details
                $country_code = Country::where('country_code', $country)->first();

                if ($country_code) {
                    $tasksQuery->where('uni_status', '0')
                               ->whereRaw("FIND_IN_SET(?, country)", [$country_code->name]);
                }
            }


            // Apply user type filters
            $FiltersBrands = array_keys(FiltersBrands());
            if (\Auth::user()->type != 'HR') {
                if (\Auth::user()->type == 'super admin' || \Auth::user()->can('level 1')) {
                    $FiltersBrands[] = '3751';
                    $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
                } elseif (\Auth::user()->type == 'company') {
                    $tasksQuery->where('deal_tasks.brand_id', \Auth::user()->id);
                } elseif (\Auth::user()->type == 'Project Director' || \Auth::user()->type == 'Project Manager' || \Auth::user()->can('level 2')) {
                    $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
                } elseif (\Auth::user()->type == 'Region Manager' || (\Auth::user()->can('level 3') && !empty(\Auth::user()->region_id))) {
                    $tasksQuery->where('deal_tasks.region_id', \Auth::user()->region_id);
                } elseif (\Auth::user()->type == 'Branch Manager' || \Auth::user()->type == 'Admissions Officer' ||
                         \Auth::user()->type == 'Career Consultant' || \Auth::user()->type == 'Admissions Manager' ||
                         \Auth::user()->type == 'Marketing Officer' || (\Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id))) {
                    $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
                } elseif (\Auth::user()->type === 'Agent') {
                    $tasksQuery->where(function($q) {
                        $q->where('deal_tasks.assigned_to', \Auth::user()->id)
                          ->orWhere('deal_tasks.created_by', \Auth::user()->id);
                    });
                } else {
                    $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
                }
            }

             // Additional filters
            if ($request->filled('fetcttype')) {
                $fetcttype  =   $request->fetcttype;

                if( $fetcttype=='yourtask'){
                    $tasksQuery->where('deal_tasks.created_by', \Auth::id());
                }

                if( $fetcttype=='assignedbyme'){
                    $tasksQuery->where('deal_tasks.assigned_to', \Auth::id());
                }

                if( $fetcttype=='Quality'){
                    $tasksQuery->where('deal_tasks.tasks_type', 'Quality');
                }

                if( $fetcttype=='Compliance'){
                    $tasksQuery->where('deal_tasks.tasks_type', 'Compliance');
                }



            }

            if ($request->fetcttype === 'agenttask') {
                $tasksQuery->whereNotNull('deal_tasks.agent_id');
            } else {
                $tasksQuery->whereNull('deal_tasks.agent_id');
            }

            // Apply all filters
            $filters = $this->TasksFilter();
            foreach ($filters as $column => $value) {

                if ($column === 'subjects') {
                    if (is_array($value) && count($value) > 0) {
                        $chunks = array_chunk($value, 500);
                        $tasksQuery->where(function($q) use ($chunks) {
                            foreach ($chunks as $chunk) {
                                $q->orWhereIn('deal_tasks.id', $chunk);
                            }
                        });
                    }
                } elseif ($column === 'assigned_to') {
                    $tasksQuery->where('deal_tasks.assigned_to', $value);
                } elseif ($column === 'brand_id') {
                    $tasksQuery->where('deal_tasks.brand_id', $value);
                } elseif ($column === 'region_id') {
                    $tasksQuery->where('deal_tasks.region_id', $value);
                } elseif ($column === 'university_id') {
                    $tasksQuery->where('deal_applications.university_id', $value);
                } elseif ($column === 'branch_id') {
                    $tasksQuery->where('deal_tasks.branch_id', $value);
                } elseif ($column === 'due_date') {
                    $tasksQuery->whereDate('deal_tasks.due_date', $value);
                } elseif ($column === 'status') {
                    if (is_array($value)) {
                        if (in_array(2, $value)) {
                            $tasksQuery->where('deal_tasks.status', 0)
                                      ->whereDate('deal_tasks.due_date', '<', now());
                        } else {
                            $tasksQuery->whereIn('deal_tasks.status', $value);
                        }
                    } else {
                        if ($value == 2) {
                            $tasksQuery->where('deal_tasks.status', 0)
                                      ->whereDate('deal_tasks.due_date', '<', now());
                        } else {
                            $tasksQuery->where('deal_tasks.status', $value);
                        }
                    }
                } elseif ($column === 'created_at_from') {
                    $tasksQuery->whereDate('deal_tasks.created_at', '>=', $value);
                } elseif ($column === 'created_at_to') {
                    $tasksQuery->whereDate('deal_tasks.created_at', '<=', $value);
                }
            }

            // Additional filters
            if ($request->filled('task_type')) {
                $tasksQuery->where('deal_tasks.tasks_type', $request->task_type);
            }

            if ($request->filled('tasks_type_status')) {
                $status = $request->tasks_type_status;
                if ($status == '1') {
                    $tasksQuery->where('deal_tasks.tasks_type_status', "1")
                              ->where('deal_tasks.status', 1);
                } elseif ($status == '2') {
                    $tasksQuery->where('deal_tasks.tasks_type_status', "2");
                } else {
                    $tasksQuery->where('deal_tasks.tasks_type_status', "0");
                }
            } elseif (!$request->has('status')) {
                $tasksQuery->where('deal_tasks.status', 0)
                          ->where('deal_tasks.tasks_type_status', "0");
            }

            if ($request->filled('assigned_by_me') && $request->assigned_by_me == true) {
                $tasksQuery->where('deal_tasks.created_by', \Auth::id());
            }

            // Get Scorp tasks and merge with main tasks
            $scorpTasks = $this->GetScorpTasks();
            $mainTasks = $tasksQuery->pluck('deal_tasks.id')->toArray();
            $mergedResults = array_unique(array_merge($mainTasks, $scorpTasks));

            // Create temporary table for the final query
            $tempTable = 'temp_task_ids_' . uniqid();
            \DB::statement("CREATE TEMPORARY TABLE {$tempTable} (id INT PRIMARY KEY)");

            // Insert IDs in batches
            foreach (array_chunk($mergedResults, 1000) as $chunk) {
                \DB::table($tempTable)->insert(
                    array_map(function($id) { return ['id' => $id]; }, $chunk)
                );
            }

            // Build final query with all joins and selects
            // Build final query with all joins and selects
            $finalQuery = DealTask::select(
                    'deal_applications.university_id',
                    'deal_tasks.stage_request',
                    'deal_tasks.tasks_type',
                    'deal_tasks.tasks_type_status',
                    'deal_tasks.name',
                    'deal_tasks.brand_id',
                    'deal_tasks.id',
                    'deal_tasks.due_date',
                    'deal_tasks.status',
                    'deal_tasks.assigned_to',
                    'brandname.name as brand_name',
                    'users.name as user_name',
                    'deal_tasks.tag_ids',
                )
                ->join($tempTable, 'deal_tasks.id', '=', "{$tempTable}.id")
                ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
                ->join('users as brandname', 'brandname.id', '=', 'deal_tasks.brand_id')
                ->leftJoin('deal_applications', function ($join) {
                    $join->on('deal_applications.id', '=', 'deal_tasks.related_to')
                         ->where('deal_tasks.related_type', '=', 'application');
                });

            // Search functionality
            if (isset($_GET['ajaxCall']) && $_GET['ajaxCall'] == 'true' && isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'];
                $finalQuery->where(function($q) use ($search) {
                    $q->where('deal_tasks.name', 'like', "%{$search}%")
                      ->orWhere('brandname.name', 'like', "%{$search}%")
                      ->orWhere('users.name', 'like', "%{$search}%")
                      ->orWhere('deal_tasks.due_date', 'like', "%{$search}%");
                });
            }



            // Apply sorting
            $status =  $request->fetcttype ?? null;
            $user = \Auth::user();

            if ($status === 'Quality' || $status === 'Compliance') {
                $direction = $user->type === 'Product Coordinator' ? 'ASC' : 'DESC';
                $finalQuery->orderBy('deal_tasks.due_date', $direction);
            } else {
                $direction = $user->branch_id == 262 ? 'ASC' : 'DESC';
                $finalQuery->orderBy('deal_tasks.created_at', $direction);
            }

            $filters = $this->TasksFilter();
            foreach ($filters as $column => $value) {
                if ($column === 'subjects') {
                    if (is_array($value) && count($value) > 0) {
                        $chunks = array_chunk($value, 500);
                        $finalQuery->where(function($q) use ($chunks) {
                            foreach ($chunks as $chunk) {
                                $q->orWhereIn('deal_tasks.id', $chunk);
                            }
                        });
                    }
                } elseif ($column === 'assigned_to') {
                    // $filteredValues = array_filter($value, function ($val) {
                    //     return !empty($val);
                    // });
                    $finalQuery->where('deal_tasks.assigned_to', $value);
                } elseif ($column === 'brand_id') {
                    $finalQuery->where('deal_tasks.brand_id', $value);
                } elseif ($column === 'region_id') {
                    $finalQuery->where('deal_tasks.region_id', $value);
                } elseif ($column === 'university_id') {
                    $finalQuery->whereIn('deal_applications.university_id', $value);
                } elseif ($column === 'branch_id') {
                    $finalQuery->where('deal_tasks.branch_id', $value);
                } elseif ($column === 'due_date') {
                    $finalQuery->whereDate('deal_tasks.due_date', $value);
                } elseif ($column === 'tag_id') {
                    $finalQuery->whereRaw('FIND_IN_SET(?, deal_tasks.tag_ids)', [$value]);
                } elseif ($column === 'status') {
                    if (is_array($value)) {
                        if (in_array(2, $value)) {
                            $finalQuery->where('deal_tasks.status', 0)
                                      ->whereDate('deal_tasks.due_date', '<', now());
                        } else {
                            $finalQuery->whereIn('deal_tasks.status', $value);
                        }
                    } else {
                        if ($value == 2) {
                            $finalQuery->where('deal_tasks.status', 0)
                                      ->whereDate('deal_tasks.due_date', '<', now());
                        } else {
                            $finalQuery->where('deal_tasks.status', $value);
                        }
                    }
                } elseif ($column === 'created_at_from') {
                    $finalQuery->whereDate('deal_tasks.created_at', '>=', $value);
                } elseif ($column === 'created_at_to') {
                    $finalQuery->whereDate('deal_tasks.created_at', '<=', $value);
                }
            }

            // Additional filters
            if (!empty($_GET['tasks_type'])) {
                $finalQuery->where('deal_tasks.tasks_type', $_GET['tasks_type']);
            }

            if (!empty($_GET['tasks_type_status'])) {
                $status = $_GET['tasks_type_status'];
                if ($status == '1') {
                    $finalQuery->where('deal_tasks.tasks_type_status', "1")
                              ;
                } elseif ($status == '2') {
                    $finalQuery->where('deal_tasks.tasks_type_status', "2");
                } else {
                    $finalQuery->where('deal_tasks.tasks_type_status', "0");
                }
            } elseif (!isset($_GET['status'])) {
                $finalQuery->where('deal_tasks.status', 0)
                          ->where('deal_tasks.tasks_type_status', "0");
            }

            // Apply sorting
            if (!empty($_GET['tasks_type_status'])) {
                $status = $_GET['tasks_type_status'];
                if ($status == '1') {
                    $finalQuery->where('deal_tasks.tasks_type_status', "1")
                              ;
                } elseif ($status == '2') {
                    $finalQuery->where('deal_tasks.tasks_type_status', "2");
                } else {
                    $finalQuery->where('deal_tasks.tasks_type_status', "0");
                }
            } elseif (!isset($_GET['status'])) {
                $finalQuery->where('deal_tasks.status', 0)
                          ->where('deal_tasks.tasks_type_status', "0");
            }

            // Paginate results
            $paginatedTasks = $finalQuery->paginate($perPage);

            // Clean up
            \DB::statement("DROP TEMPORARY TABLE IF EXISTS {$tempTable}");

            return response()->json([
                'status' => 'success',
                'data' => $paginatedTasks->items(),
                'current_page' => $paginatedTasks->currentPage(),
                'last_page' => $paginatedTasks->lastPage(),
                'total_records' => $paginatedTasks->total(),
                'per_page' => $paginatedTasks->perPage(),
                'message' => 'Tasks fetched successfully'
            ]);
        }else{
            return response()->json([
                    'status' => 'error',
                    'message' => __('Permission Denied.')
            ], 403);
        }

        return redirect()->back()->with('error', __('Permission Denied.'));
    }

    private function filterStatus($tasks, $value)
    {
        if (is_array($value)) {
            if (in_array(2, $value)) {
                $tasks->where('status', 0)->whereDate('deal_tasks.due_date', '<', now());
            } else {
                $tasks->whereIn('status', $value);
            }
        } else {
            if ($value == 2) {
                $tasks->where('status', 0)->whereDate('deal_tasks.due_date', '<', now());
            } else {
                $tasks->where('status', $value);
            }
        }
    }


    public function createtask(Request $request)
    {
        $usr = \Auth::user();
        $employeeId = !empty(\Auth::user()->id) ? \Auth::user()->id : 0;

        // // Check if the user has permission to create a task
        // if ($usr->can('create task')) {

        // Validation rules and messages
        $rules = [
            'task_name' => 'required',
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'assigned_to' => 'required|integer|min:1',
            'assign_type' => 'required',
            'due_date' => 'required',
            'start_date' => 'required',
        ];

        $messages = [
            'brand_id.min' => 'The brand id must be required',
            'region_id.min' => 'The Region id must be required',
            'branch_id.min' => 'The branch id must be required',
            'assigned_to.min' => 'The Assigned id must be required',
        ];

        // Validate the request
        $validator = \Validator::make($request->all(), $rules, $messages);

        // Return validation error if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        // Create a new DealTask
        $dealTask = new DealTask();
        $dealTask->deal_id = $request->related_to ?? 0;
        $dealTask->related_to = $request->related_to ?? 0;
        $dealTask->related_type = $request->related_type ?? 'task';
        $dealTask->name = $request->task_name;
        $dealTask->branch_id = $request->branch_id;
        $dealTask->region_id = $request->region_id;
        $dealTask->brand_id =  $request->brand_id;
        $dealTask->created_by = $employeeId;
        $dealTask->assigned_to = $request->assigned_to;
        $dealTask->assigned_type = $request->assign_type;
        $dealTask->due_date = $request->due_date ?? '';
        $dealTask->start_date = $request->start_date;
        $dealTask->date = $request->start_date;
        $dealTask->status = $request->status ?? 0;
        $dealTask->remainder_date = $request->remainder_date;
        $dealTask->description = $request->description;
        $dealTask->visibility = $request->visibility;
        $dealTask->priority = 1;
        $dealTask->time = $request->remainder_time ?? '';
        $dealTask->save();

        // Add log activity (optional)
        $remarks = [
            'title' => 'Task Created',
            'message' => 'Task Created successfully'
        ];

        if (isset($dealTask->deal_id) && in_array($dealTask->related_type, ['organization', 'lead', 'deal', 'application', 'toolkit', 'agency', 'task'])) {
            $logData = [
                'type' => 'success',
                'note' => json_encode($remarks),
                'module_id' => $dealTask->related_type == 'task' ? $dealTask->id : $dealTask->deal_id,
                'module_type' => $dealTask->related_type,
                'notification_type' => 'Task created'
            ];
            addLogActivity($logData);
        }



        // Notification data (optional)
        $html = '<p class="mb-0"><span class="fw-bold">
               <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important" onclick="openSidebar(\'/get-task-detail?task_id=' . $dealTask->id . '\')" data-task-id="' . $dealTask->id . '">' . $dealTask->name . '</span></span>
               Created By <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important" onclick="openSidebar(\'/users/' . \Auth::id() . '/user_detail\')">' . User::find(\Auth::id())->name . '</span></p>';

        $notificationData = [
            'type' => 'Tasks',
            'data_type' => 'Task_Created',
            'sender_id' => $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => $html,
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now()
        ];

        // Send notification if the creator is not the assigned user
        if ($dealTask->created_by !== (int)$dealTask->assigned_to) {
            addNotifications($notificationData);
        }

        // Return success response
        return response()->json([
            'status' => 'success',
            'task_id' => $dealTask->id,
            'message' => __('Task successfully created!')
        ], 201);

        // } else {
        //     // Return error response if the user does not have permission
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission Denied.')
        //     ], 403);
        // }
    }



    public function taskUpdate(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('edit task')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validation Rules
        $rules = [
            'task_name' => 'required|string|max:255',
            'task_id' => 'required|integer|min:1',
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'assigned_to' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'start_date' => 'required|date',
            'visibility' => 'required|string',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;

        $dealTask = DealTask::find($id);

        if (!$dealTask) {
            return response()->json([
                'status' => 'error',
                'message' => __('Task not found.')
            ], 404);
        }

        // Track Status Change
        $is_status_change = $dealTask->status !== $request->status;

        // Update Task Details
        $dealTask->related_to = $request->related_to;
        $dealTask->related_type = $request->related_type;
        $dealTask->name = $request->task_name;
        $dealTask->branch_id = $request->branch_id ?? $dealTask->branch_id;
        $dealTask->assigned_to = $request->assigned_to ?? $dealTask->assigned_to;
        $dealTask->brand_id = $request->brand_id ?? $dealTask->brand_id;
        $dealTask->assigned_type = $request->assign_type;
        $dealTask->region_id = $request->region_id ?? $dealTask->region_id;
        $dealTask->due_date = $request->due_date;
        $dealTask->start_date = $request->start_date;
        $dealTask->date = $request->start_date;
        $dealTask->status = $request->status ?? $dealTask->status;
        $dealTask->remainder_date = $request->remainder_date;
        $dealTask->description = $request->description;
        $dealTask->visibility = $request->visibility;
        $dealTask->priority = 1;
        $dealTask->time = $request->remainder_time ?? $dealTask->time;

        $dealTask->save();

        // Log Activity

        ActivityLog::create(
            [
                'user_id' => $user->id,
                'deal_id' => $dealTask->deal_id,
                'log_type' => 'Update Task',
                'remark' => json_encode(['title' => $dealTask->name]),
            ]
        );

        //store Activity Log
        $remarks = [
            'title' => 'Task Update',
            'message' => 'Task updated successfully'
        ];

        $module_id = isset($dealTask->deal_id) && in_array($dealTask->related_type, ['organization', 'lead', 'deal', 'application', 'toolkit', 'agency'])
            ? $dealTask->deal_id
            : $dealTask->id;

        $module_type = isset($dealTask->deal_id) && in_array($dealTask->related_type, ['organization', 'lead', 'deal', 'application', 'toolkit', 'agency'])
            ? $dealTask->related_type
            : 'task';

        $data = [
            'type' => 'info',
            'note' => json_encode($remarks),
            'module_id' => $module_id,
            'module_type' => $module_type,
            'notification_type' => 'Task Update'
        ];
        addLogActivity($data);

        $html = '<p class="mb-0">
    <span class="fw-bold">
       <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important"
    onclick="openSidebar(\'/get-task-detail?task_id=' . $dealTask->id . '\')"
    data-task-id="' . $dealTask->id . '">' . $dealTask->name . '</span>
     </span>
    Task Updated By <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important"
    onclick="openSidebar(\'/users/' . \Auth::id() . '/user_detail\')">
    ' . User::find(\Auth::id())->name ?? '' . '
   </p>';

        $Notification_data = [
            'type' => 'Tasks',
            'data_type' => 'Task_Updated',
            'sender_id' =>  $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => $html,
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now()
        ];
        if ($dealTask->created_by !== (int)$dealTask->assigned_to && (int)$dealTask->assigned_to !== \Auth::id()) {
            addNotifications($Notification_data);
        }

        if ($is_status_change) {
            //store Activity Log
            $remarks = [
                'title' => 'Task Update',
                'message' => 'Task status updated'
            ];

            //store Log
            $data = [
                'type' => 'info',
                'note' => json_encode($remarks),
                'module_id' => $dealTask->id,
                'module_type' => 'task',
                'notification_type' => 'Task Update'
            ];
            addLogActivity($data);
        }

        return response()->json([
            'status' => 'success',
            'task_id' => $dealTask->id,
            'message' => __('Task successfully updated!')
        ], 200);
    }


    public function ShuffleTaskOwnership(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
            'created_by' => 'required|integer|min:1',
            'assigned_to' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;
        if (!empty($id)) {

            $task = DealTask::findOrFail($id);

            $from = User::find($task->assigned_to);
            $to = User::find($request->assigned_to);

            // Ensure $from and $to are valid objects before accessing their properties
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Swaps Tasks',
                    'message' => 'Swaps From ' . ($from->name ?? '') . ' To ' . ($to->name ?? '')
                ]),
                'module_id' => $id,
                'module_type' => 'task',
                'notification_type' => 'Swaps Tasks From ' . ($from->name ?? '') . ' To ' . ($to->name ?? '') . ' Successfully'
            ];

            addLogActivity($data);


            $task->assigned_to = $request->created_by;
            $task->created_by = $request->assigned_to;
            $task->due_date = Carbon::now()->addDay()->format('Y-m-d');
            $task->is_swap = '1';
            $task->save();

            return json_encode([
                'status' => 'success',
                'message' => 'Swap Tasks Successfully',
                'id' => $id,
            ]);
        }
    }



    public function getTaskDetails(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Task Details
        $taskId = $request->task_id;
        $task = DealTask::findOrFail($taskId);

        // Fetch Related Data
        // $branches = Branch::get()->pluck('name', 'id');
        // $users = User::get()->pluck('name', 'id');
        // $stages = Stage::get()->pluck('name', 'id');
        // $universities = University::get()->pluck('name', 'id');
        // $organizations = User::where('type', 'organization')->orderBy('name', 'ASC')->pluck('name', 'id');
        // $leads = Lead::where('branch_id', $task->branch_id)->orderBy('name', 'ASC')->pluck('name', 'id');
        // $deals = Deal::where('branch_id', $task->branch_id)->orderBy('name', 'ASC')->pluck('name', 'id');
        // $toolkits = University::orderBy('name', 'ASC')->pluck('name', 'id');
        // $applications = DealApplication::join('deals', 'deals.id', '=', 'deal_applications.deal_id')
        //     ->where('deals.branch_id', $task->branch_id)
        //     ->orderBy('deal_applications.name', 'ASC')
        //     ->pluck('deal_applications.application_key', 'deal_applications.id');
        // $Agency = \App\Models\Agency::find($task->related_to);

        // Fetch Discussions
        $discussions = TaskDiscussion::select('task_discussions.id', 'task_discussions.comment', 'task_discussions.created_at', 'users.name', 'users.avatar')
            ->join('users', 'task_discussions.created_by', 'users.id')
            ->where(['task_discussions.task_id' => $taskId])
            ->orderBy('task_discussions.created_at', 'DESC')
            ->get();

        // Fetch Log Activities
        $log_activities = getLogActivity($taskId, 'task');

        // Build Response Data
        $response = [
            'status' => 'success',
            'task' => $task,
            // 'branches' => $branches,
            // 'users' => $users,
            // 'stages' => $stages,
            // 'universities' => $universities,
            // 'organizations' => $organizations,
            // 'leads' => $leads,
            // 'deals' => $deals,
            // 'toolkits' => $toolkits,
            // 'applications' => $applications,
            // 'agency' => $Agency,
            'discussions' => $discussions,
            'log_activities' => $log_activities
        ];
        return response()->json([
            'status' => "success",
            'data' => $response,
        ], 200);
    }


    public function TaskDetails(Request $request)
    {
        $rules = [
            'task_id' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Task Details
        $taskId = $request->task_id;

        $task = DealTask::select(
                    'deal_tasks.*',
                    'brandUser.name as brandName',
                    'regions.name as RegionName',
                    'branches.name as BranchName',
                    'assignedUser.name as AssignedName',
                    'assignedByUser.name as assignedByName'
                )
                ->leftJoin('users as brandUser', 'brandUser.id', '=', 'deal_tasks.brand_id')
                ->leftJoin('regions', 'regions.id', '=', 'deal_tasks.region_id')
                ->leftJoin('branches', 'branches.id', '=', 'deal_tasks.branch_id')
                ->leftJoin('users as assignedByUser', 'assignedByUser.id', '=', 'deal_tasks.created_by')
                ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deal_tasks.assigned_to')->find($taskId);


            $applied_meta = \DB::table('meta')
            ->select('meta_key', 'meta_value')
            ->where('parent_id', $task->related_to)
            ->where('stage_id', 6)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })
            ->toArray();

        $applied_meta = array_filter($applied_meta, function ($item) {
            return is_array($item) && isset($item['meta_key']) && isset($item['meta_value']);
        });

        // Generate the HTML in the controller
        $applied_meta_html = '';
        if (!empty($applied_meta)) {
            foreach ($applied_meta as $item) {
                $applied_meta_html .= '<tr>';
                $applied_meta_html .= '<td style="width: 400px; font-size: 14px;border-bottom: 1px solid gray;">' . htmlspecialchars(formatKey($item['meta_key'])) . '</td>';

                // Decode JSON if applicable
                $meta_value = $item['meta_value'];
                if (is_string($meta_value) && is_json($meta_value)) {
                    $meta_value = json_decode($meta_value, true);
                }

                // Check if meta_value is iterable (array or object)
                if (is_iterable($meta_value)) {
                    $applied_meta_html .= '<td class="description-td" style="border-bottom: 1px solid gray;padding-left:52px; width: 550px; text-align: justify; font-size: 14px;">';
                    foreach ($meta_value as $value) {
                        $applied_meta_html .= '<span class="badge bg-primary me-1">' . htmlspecialchars($value) . '</span>';
                    }
                    $applied_meta_html .= '</td>';
                } else {
                    // Fallback: Display meta_value as plain text
                    if ($item['meta_key'] == 'stage_id' && $meta_value == 6) {
                        $applied_meta_html .= '<td class="description-td" style="border-bottom: 1px solid gray;padding-left:52px; width: 550px; text-align: justify; font-size: 14px;">' . htmlspecialchars("Compliance Checks") . '</td>';
                    } else {
                        $applied_meta_html .= '<td class="description-td" style="border-bottom: 1px solid gray;padding-left:52px; width: 550px; text-align: justify; font-size: 14px;">' . htmlspecialchars($meta_value) . '</td>';
                    }
                }

                $applied_meta_html .= '</tr>';
            }
        }

        $deal_details_get = null;
        $FirstApp = null;
        $CourseName = null;

        if ($task->related_type == "application") {
            $FirstApp = DealApplication::find($task->related_to);

            $Course = Course::where('id', $FirstApp->course_id ?? '--')->first();
                        if(!empty($Course)){
                           $CourseName = $Course->name . ' - ' . $Course->campus . ' - ' . $Course->intake_month . ' - ' . $Course->intakeYear . ' (' . $Course->duration . ')';
                        }else{
                           $CourseName = $FirstApp?->course;
                }
            if ($FirstApp) {
                $deal_details_get = \DB::table('deals')
                    ->leftJoin('client_deals', 'client_deals.deal_id', '=', 'deals.id')
                    ->leftJoin('users as clientUser', 'clientUser.id', '=', 'client_deals.client_id')
                    ->leftJoin('users as brandUser', 'brandUser.id', '=', 'deals.brand_id')
                    ->leftJoin('regions', 'regions.id', '=', 'deals.region_id')
                    ->leftJoin('branches', 'branches.id', '=', 'deals.branch_id')
                    ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deals.assigned_to')
                    ->leftJoin('sources', 'sources.id', '=', 'deals.sources')
                    ->where('deals.id', $FirstApp->deal_id)
                    ->select(
                        'deals.id',
                        'deals.name',
                        'clientUser.name as clientUserName',
                        'sources.name as sourceName',
                        'clientUser.id as clientUserID',
                        'clientUser.passport_number as passportnumber',
                        'clientUser.email as clientUserEmail',
                        'clientUser.phone as clientUserPhone',
                        'clientUser.address as clientUserAddress',
                        'brandUser.name as brandName',
                        'regions.name as RegionName',
                        'branches.name as branchName',
                        'assignedUser.name as assignedName',
                        'brandUser.id as brandId',
                        'regions.id as RegionId',
                        'branches.id as branchId',
                        'assignedUser.id as assignedId',
                        'assignedUser.email as assignedUserEmail',
                        'branches.email as branchEmail',
                    )->first();
            }
        }

        $RelatedTo = $this->GetBranchByType($task->related_type,$task->related_to);

        return response()->json([
            'status' => 'success',
            'data' => compact(
                'RelatedTo',
                'task',
                'deal_details_get',
                'FirstApp',
                'CourseName',
                'applied_meta_html',
                'applied_meta',
            )
        ]);
    }




    private function GetBranchByType($type,$id)
    {
        if ($type == 'lead') {
            $data = \App\Models\Lead::where('id', $id)->first();
        } else if ($type == 'organization') {
            $data = User::where('type', 'organization')->where('id',$id)->first();
        } else if ($type == 'deal') {
            $data = Deal::where('id', $id)->first();
        } else if ($type == 'application') {
            $data = DealApplication::join('deals', 'deals.id', '=', 'deal_applications.deal_id')
            ->leftJoin('client_deals', 'client_deals.deal_id', '=', 'deals.id')
            ->leftJoin('users as clientUser', 'clientUser.id', '=', 'client_deals.client_id')
            ->leftJoin('users as brandUser', 'brandUser.id', '=', 'deals.brand_id')
            ->leftJoin('regions', 'regions.id', '=', 'deals.region_id')
            ->leftJoin('branches', 'branches.id', '=', 'deals.branch_id')
            ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deals.assigned_to')
            ->leftJoin('sources', 'sources.id', '=', 'deals.sources')
            ->leftJoin('leads', 'leads.is_converted', '=', 'deals.id')
            ->where('deal_applications.id', $id)
            ->select(
                'deal_applications.name as name',
                'deal_applications.id as dealApplicationId',
                'deals.id as dealId',
                'deals.name as dealName',
                'clientUser.name as clientUserName',
                'sources.name as sourceName',
                'clientUser.id as clientUserID',
                'clientUser.passport_number as passportNumber',
                'clientUser.email as clientUserEmail',
                'clientUser.phone as clientUserPhone',
                'clientUser.address as clientUserAddress',
                'brandUser.name as brandName',
                'regions.name as regionName',
                'branches.name as branchName',
                'assignedUser.name as assignedName',
                'brandUser.id as brandId',
                'regions.id as regionId',
                'branches.id as branchId',
                'assignedUser.id as assignedId',
                'assignedUser.email as assignedUserEmail',
                'branches.email as branchEmail',
                'leads.drive_link as DriveLink'
            )
            ->first();


        } else if ($type == 'toolkit') {
            $data = University::pluck('name')->first();
        } else if ($type == 'agency') {
            $data = User::select('agencies.organization_name as name')->join('agencies','agencies.user_id','users.id')->where('users.id',$id)->first();
        } else {
            $data = User::where('id',$id)->where('type', 'organization')->first();
        }

        return $data;
    }

    public function taskDiscussionStore(Request $request)
    {
        $rules = [
            'task_id' => 'required|integer|min:1',
            'comment' => 'required',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Retrieve task and validate existence
        $dealTask = DealTask::find($request->task_id);
        if (!$dealTask) {
            return response()->json([
                'status' => 'error',
                'message' => __('Task not found!'),
            ], 404);
        }

        // Create a new task discussion
        $discussion = new TaskDiscussion();
        $discussion->fill([
            'comment'    => $request->comment,
            'task_id'    => $request->task_id,
            'created_by' => \Auth::id(),
        ])->save();

        // Prepare notification data
        $Notification_data = [
            'type' => 'Tasks',
            'data_type' => 'Notes_Created',
            'sender_id' => $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => 'Create New Notes',
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now(),
        ];

        // Add notification if applicable
        if (
            $dealTask->created_by !== (int)$dealTask->assigned_to &&
            (int)$dealTask->assigned_to !== \Auth::id()
        ) {
            if (function_exists('addNotifications')) {
                addNotifications($Notification_data);
            } else {
                \Log::error('Notification function not found');
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Message successfully added!'),
        ], 201);
    }

    public function taskDiscussionUpdate(Request $request)
    {
        $rules = [
            'task_id' => 'required|integer|min:1',
            'id' => 'required|integer|min:1',
            'comment' => 'required',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Find the discussion and validate existence
        $discussion = TaskDiscussion::find($request->id);
        if (!$discussion) {
            return response()->json([
                'status' => 'error',
                'message' => __('Discussion not found!'),
            ], 404);
        }

        // Find the task and validate existence
        $dealTask = DealTask::find($request->task_id);
        if (!$dealTask) {
            return response()->json([
                'status' => 'error',
                'message' => __('Task not found!'),
            ], 404);
        }

        // Update the discussion
        $discussion->fill([
            'comment'    => $request->comment,
            'task_id'    => $request->task_id,
            'created_by' => \Auth::id(),
        ])->save();

        // Prepare notification data
        $Notification_data = [
            'type' => 'Tasks',
            'data_type' => 'Notes_Created',
            'sender_id' => $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => 'Update notes',
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now(),
        ];

        // Add notification if applicable
        if (
            $dealTask->created_by !== (int)$dealTask->assigned_to &&
            (int)$dealTask->assigned_to !== \Auth::id()
        ) {
            if (function_exists('addNotifications')) {
                addNotifications($Notification_data);
            } else {
                \Log::error('Notification function not found');
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Message successfully added!'),
        ], 201);
    }

    public function GetTaskDiscussion(Request $request)
    {
        $rules = [
            'task_id' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $id = $request->task_id;
        $discussions = TaskDiscussion::select('task_discussions.id', 'task_discussions.comment', 'task_discussions.created_at', 'users.name', 'users.avatar')
            ->join('users', 'task_discussions.created_by', 'users.id')
            ->where(['task_discussions.task_id' => $id])
            ->orderBy('task_discussions.created_at', 'DESC')
            ->get()
            ->map(function ($discussion) {
                return [
                    'id' => $discussion->id,
                    'text' => htmlspecialchars_decode($discussion->comment),
                    'author' => $discussion->name,
                    'time' => $discussion->created_at->diffForHumans(),
                    'pinned' => false, // Default value as per the requirement
                    'timestamp' => $discussion->created_at->toISOString()
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $discussions
        ], 201);
    }


    public function taskDiscussionDelete(Request $request)
    {

        $rules = [
            'id' => 'required|integer|min:1',
        ];
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $discussions = TaskDiscussion::find($request->id);
        if (!empty($discussions)) {
            $discussions->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Task Discussion deleted!')
        ], 201);
    }

    public function taskDelete(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;
        $task = DealTask::findOrFail($id);
        $notifications = \App\Models\Notification::where('type', 'Tasks')->where('related_id', $id)->first();
        if (!empty($notifications)) {
            $notifications->delete();
        }
        $task->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Task successfully deleted!')
        ], 201);
    }


    public function downloadTasks()
    {
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->can('level 1')) {
            $tasks = DealTask::select(['deal_tasks.*'])->join('users', 'users.id', '=', 'deal_tasks.assigned_to');

            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);

            if (\Auth::user()->type == 'company') {
                $tasks->where('deal_tasks.brand_id', \Auth::user()->id);
            } else if (\Auth::user()->type == 'Project Director' || \Auth::user()->type == 'Project Manager' || \Auth::user()->can('level 2')) {
                $tasks->whereIn('deal_tasks.brand_id', $brand_ids);
            } else if (\Auth::user()->type == 'Regional Manager' || \Auth::user()->can('level 3') && !empty(\Auth::user()->region_id)) {
                $tasks->where('deal_tasks.region_id', \Auth::user()->region_id);
            } else if (\Auth::user()->type == 'Branch Manager' || \Auth::user()->type == 'Admissions Officer' || \Auth::user()->type == 'Marketing Officer' || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
                $tasks->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            } else {
                $tasks->where('deal_tasks.assigned_to', \Auth::user()->id);
            }

            $filters = $this->TasksFilter();

            foreach ($filters as $column => $value) {
                if ($column === 'subjects') {
                    $tasks->whereIn('deal_tasks.name', $value);
                } elseif ($column === 'assigned_to') {
                    $tasks->whereIn('assigned_to', $value);
                } elseif ($column === 'created_by') {
                    $tasks->whereIn('deal_tasks.brand_id', $value);
                } elseif ($column == 'due_date') {
                    $tasks->whereDate('due_date', 'LIKE', '%' . substr($value, 0, 10) . '%');
                } elseif ($column == 'status') {
                    $tasks->where('status', $value);
                }
            }

            if (!isset($_GET['status'])) {
                $tasks->where('status', 0);
            }

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $g_search = $_GET['search'];
                $tasks->Where('deal_tasks.name', 'like', '%' . $g_search . '%');
                $tasks->orWhere('deal_tasks.due_date', 'like', '%' . $g_search . '%');
            }

            $tasks = $tasks->orderBy('created_at', 'DESC')->get();
            $all_users = allUsers();

            // Prepare CSV Data
            $header = ['Sr.No.', 'Subject', 'Assigned to', 'Brand', 'Status'];
            $data = [];
            foreach ($tasks as $key => $task) {
                $data[] = [
                    $key + 1,
                    $task->name,
                    $all_users[$task->assigned_to] ?? '',
                    $all_users[$task->brand_id] ?? '',
                    ($task->status == 1) ? 'Completed' : 'On Going'
                ];
            }

            downloadCSV($header, $data, 'tasks.csv');
            return true;
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }
    }


    public function updateTaskStatus(Request $request, $status)
    {



        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:deal_tasks,id',
            'comment' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $id = $request->input('id');
        $dealTask = DealTask::find($id);
        if (!$dealTask || !in_array(\Auth::user()->type, ['super admin', 'Product Coordinator'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or invalid task'
            ], 403);
        }
        $discussion = new TaskDiscussion();
        $discussion->comment = $request->comment;
        $discussion->task_id = $id;
        $discussion->created_by = \Auth::id();
        $discussion->save();


        $stageRequest = DealTask::where('id', $id)
            ->where('related_type', 'application')->first();
        if ($status == 'approve') {
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Task Approved successfully',
                    'message' => 'Task Approved successfully'
                ]),
                'module_id' => $id,
                'module_type' => 'task',
                'notification_type' => 'Task Approved successfully'
            ];
            addLogActivity($data);
            $stageRequest->tasks_type_status = '1';
        } else {
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Task Rejected successfully',
                    'message' => 'Task Rejected successfully'
                ]),
                'module_id' => $id,
                'module_type' => 'task',
                'notification_type' => 'Task Rejected successfully'
            ];
            addLogActivity($data);
            $stageRequest->tasks_type_status = '2';
            $dealTask->status = 1;
            $dealTask->save();
        }
        $dealTask->save();
        $stageRequest->save();
        if (!$stageRequest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stage request not found'
            ], 404);
        }

        $applicationId = $stageRequest->related_to;
        $dealApplication = DealApplication::find($applicationId);

        if ($dealApplication) {
            $deal = Deal::find($dealApplication->deal_id);
            if ($deal) {
                $highestStageApplication = DealApplication::where('deal_id', $dealApplication->deal_id)
                    ->orderBy('stage_id', 'desc')
                    ->first();

                if (!empty($highestStageApplication)) {
                    if ($highestStageApplication->stage_id == '0') {
                        $deal->stage_id = 0;
                    } elseif ($highestStageApplication->stage_id == '1' || $highestStageApplication->stage_id == '2') {
                        $deal->stage_id = 1;
                    } elseif ($highestStageApplication->stage_id == '3' || $highestStageApplication->stage_id == '4') {

                        $deal->stage_id = 2;
                    } elseif ($highestStageApplication->stage_id == '5' || $highestStageApplication->stage_id == '6') {

                        $deal->stage_id = 3;
                    } elseif ($highestStageApplication->stage_id == '7' || $highestStageApplication->stage_id == '8') {

                        $deal->stage_id = 4;
                    } elseif ($highestStageApplication->stage_id == '9' || $highestStageApplication->stage_id == '10') {

                        $deal->stage_id = 5;
                    } elseif ($highestStageApplication->stage_id == '11') {

                        $deal->stage_id = 6;
                    } elseif ($highestStageApplication->stage_id == '12') {

                        $deal->stage_id = 7;
                    }

                    $deal->save();
                } else {
                    $deal->stage_id = 0;
                    $deal->save();
                }
            }

            $lastStageHistory = StageHistory::where('type', 'application')
                ->where('type_id', $applicationId)
                ->latest()
                ->first();

            if ($status != 'approve') {

                //new code
                if ($stageRequest->tasks_type == 'Quality') {
                    $dealApplication->update(['stage_id' => 0]);
                    addLeadHistory([
                        'stage_id' => 0,
                        'type_id' => $applicationId,
                        'type' => 'application'
                    ]);
                } elseif ($stageRequest->tasks_type == 'Compliance') {
                    $dealApplication->update(['stage_id' => 5]);
                    addLeadHistory([
                        'stage_id' => 5,
                        'type_id' => $applicationId,
                        'type' => 'application'
                    ]);
                }
            } else {
                $dealApplication->update(['stage_id' => $stageRequest->stage_request]);
                addLeadHistory([
                    'stage_id' => $stageRequest->stage_request,
                    'type_id' => $applicationId,
                    'type' => 'application'
                ]);
            }

            // Add Log
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Stage Updated',
                    'message' => 'Application stage updated successfully.'
                ]),
                'module_id' => $applicationId,
                'module_type' => 'application',
                'notification_type' => 'application stage update'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User Task updated successfully'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Application not found'
        ], 404);
    }

    public function RejectTaskStatus(Request $request)
    {
        return $this->updateTaskStatus($request, 'reject');
    }

    public function ApprovedTaskStatus(Request $request)
    {
        return $this->updateTaskStatus($request, 'approve');
    }

    public function GetTaskByRelatedToRelatedType(Request $request)
    {
        // Validate the input
        $validator = Validator::make(
            $request->all(),
            [
                'related_to' => 'required|exists:deal_tasks,related_to',
                'related_type' => 'required|exists:deal_tasks,related_type',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $related_type = $request->related_type;
        $related_to = $request->related_to;

        // Initialize query
        $tasksQuery = DealTask::query()
            ->where('deal_tasks.related_type', $related_type)
            ->where('deal_tasks.related_to', $related_to)
            ->select(
                'users.name as AssignedTo',
                'deal_tasks.name as TaskName',
                'deal_tasks.id',
                'deal_tasks.created_at',
                'deal_tasks.status',
                'createdByUser.name as CreatedByUsers'
            )
            ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
            ->join('users as createdByUser', 'createdByUser.id', '=', 'deal_tasks.created_by')
            ->leftJoin('deal_applications', function ($join) {
                $join->on('deal_applications.id', '=', 'deal_tasks.related_to')
                    ->where('deal_tasks.related_type', '=', 'application');
            })
            ->leftJoin('universities', 'universities.id', '=', 'deal_applications.university_id');

        // Add filters based on user roles
        $FiltersBrands = array_keys(FiltersBrands());

        if (\Auth::user()->type !== 'HR') {
            if (\Auth::user()->type === 'super admin' || \Auth::user()->can('level 1')) {
                $FiltersBrands[] = '3751';
                $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
            } elseif (\Auth::user()->type === 'company') {
                $tasksQuery->where('deal_tasks.brand_id', \Auth::user()->id);
            } elseif (\Auth::user()->type === 'Project Director' || \Auth::user()->type === 'Project Manager' || \Auth::user()->can('level 2')) {
                $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
            } elseif (\Auth::user()->type === 'Region Manager' || (\Auth::user()->can('level 3') && !empty(\Auth::user()->region_id))) {
                $tasksQuery->where('deal_tasks.region_id', \Auth::user()->region_id);
            } elseif (\Auth::user()->type === 'Branch Manager' || \Auth::user()->type === 'Admissions Officer' ||
                    \Auth::user()->type === 'Careers Consultant' || \Auth::user()->type === 'Admissions Manager' ||
                    \Auth::user()->type === 'Marketing Officer' || (\Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id))) {
                $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            } elseif (\Auth::user()->type === 'Agent') {
                $tasksQuery->where(function ($query) {
                    $query->where('deal_tasks.assigned_to', \Auth::user()->id)
                        ->orWhere('deal_tasks.created_by', \Auth::user()->id);
                });
            } else {
                $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            }
        }

        // Fetch tasks and transform them
        $tasks = $tasksQuery->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'text' => htmlspecialchars_decode($task->TaskName),
                'author' => $task->CreatedByUsers,
                'time' => $task->created_at->diffForHumans(),
                'status' => $task->status == 1 ? 'Completed' : 'On Going',
                'timestamp' => $task->created_at->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $tasks
        ], 200);
    }

}
