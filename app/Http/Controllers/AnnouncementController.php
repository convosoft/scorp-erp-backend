<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
   public function index(Request $request)
    {
        if (!auth()->user()->can('manage announcement')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }

        $query = Announcement::query()->orderByDesc('id');

        // ---------------------------
        // Filters
        // ---------------------------

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('created_at')) {
            $query->whereDate('created_at', $request->created_at);
        }

        if ($request->filled('role_id')) {
            $roleIds = (array) $request->role_id;
            foreach ($roleIds as $rid) {
                $query->where('role_id', 'LIKE', "%$rid%");
            }
        }

        if ($request->filled('brand_id')) {
            $brandIds = (array) $request->brand_id;
            foreach ($brandIds as $bid) {
                $query->where('brand_id', 'LIKE', "%$bid%");
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                ->orWhere('description', 'LIKE', "%$search%");
            });
        }

        // ---------------------------
        // Pagination
        // ---------------------------
        $perPage = $request->per_page ?? env("RESULTS_ON_PAGE", 50);
        $announcements = $query->paginate($perPage);

        // ---------------------------
        // Build API Response
        // --------------------------- 
            $data = $announcements->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'department_id' => $item->department,
                'department_name' => $item->department_name,
                'brand_ids' => explode(',', $item->brand_id),
                'brand_names' => $item->brand_names,
                'role_ids' => explode(',', $item->role_id),
                'role_names' => $item->role_names,
                'created_by' => $item->creator?->name,
                'created_at' => $item->created_at,
            ];
        });

        return response()->json([
            'status' => "success",
            'message' => 'Announcements retrieved successfully.', 
                'total' => $announcements->total(),
                'per_page' => $announcements->perPage(),
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
           
            'data' => $data,
        ], 200);
    }

    public function addAnnouncement(Request $request)
    {
        // Permission Check
        if (!auth()->user()->can('create announcement') && auth()->user()->type != 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.',
            ], 403);
        }
 

            

     // Validate input
        $validator = Validator::make($request->all(),  [
            'title'          => 'required',
            'department_id'  => 'required|exists:departments,id',
            'category_id'    => 'required|exists:announcement_categories,id',
            'reminder_date'  => 'required|date',
            'description'    => 'required',
            'announcement_file' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Fetch category name from ID
        $categoryName = \DB::table('announcement_categories')->where('id', $request->category_id)->value('name');
         $rules['role_id'] = [];
        if ($categoryName == 'Brand_Specific') {
            $rules['brand_id'] = [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    if (empty(array_filter($value))) {
                        $fail('At least one brand must be selected.');
                    }
                },
            ];
        }

        if ($categoryName == 'Role_Specific') {
            $rules['role_id'] = [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    if (empty(array_filter($value))) {
                        $fail('At least one role must be selected.');
                    }
                },
            ];
        }
        // $request->merge([
        //     'reminder_date' => \Carbon\Carbon::createFromFormat('d-m-Y', $request->reminder_date)->format('Y-m-d')
        // ]);
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        // File Upload
        $fileName = null;
        if ($request->hasFile('announcement_file')) {
            $fileName = time() . '_' . uniqid() . '.' . $request->announcement_file->extension();
            $request->announcement_file->move(public_path('announcement_file'), $fileName);
        }

        // Save Announcement
        $announcement = new Announcement();
        $announcement->title = $request->title;
        $announcement->announcement_file = $fileName;
        $announcement->category_id = $request->category_id;
        $announcement->description = $request->description;
        $announcement->reminder_date = date('Y-m-d', strtotime($request->reminder_date));
        $announcement->department = $request->department_id;
        $announcement->announcement_counter = 0;
        $announcement->created_by = auth()->id();

        // Brand Specific
        $announcement->brand_id = ($categoryName == 'Brand_Specific')
            ? implode(',', $request->brand_id)
            : null;

        // Role Specific
        $announcement->role_id = ($categoryName == 'Role_Specific')
            ? implode(',', $request->role_id)
            : null;

        $announcement->save();


        /*
        |--------------------------------------------------------------------------
        | 🔥 ACTIVITY LOG (as your provided example)
        |--------------------------------------------------------------------------
        */
        $typeoflog = 'announcement: '.$announcement->title;

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title'   => 'New '.$typeoflog.' created',
                'message' => 'Announcement "'.$announcement->title.'" has been created'
            ]),
            'module_id'        => $announcement->id,
            'module_type'      => 'announcement',
            'notification_type'=> ucfirst($typeoflog).' Created',
            'created_by'       => auth()->id(),
        ]);


        /*
        |--------------------------------------------------------------------------
        | 🔔 Notifications
        |--------------------------------------------------------------------------
        */
        $this->sendAnnouncementNotifications($announcement, $categoryName, $request);


        return response()->json([
            'status'  => 'success',
            'message' => 'Announcement successfully created.',
            'data'    => $announcement
        ], 201);
    }

    public function updateAnnouncement(Request $request)
{
    // Permission Check
    if (!auth()->user()->can('edit announcement') && auth()->user()->type != 'super admin') {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission denied.',
        ], 403);
    }

    // Fetch the announcement
 

    

     // Validate input
        $validator = Validator::make($request->all(), [
        'title'          => 'required',
        'department_id'  => 'required|exists:departments,id',
        'id'  => 'required|exists:announcements,id',
        'category_id'    => 'required|exists:announcement_categories,id',
        'reminder_date'  => 'required|date',
        'description'    => 'required',
        'announcement_file' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048'
    ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

       $announcement = Announcement::find($request->id);
    if (!$announcement) {
        return response()->json([
            'status' => 'error',
            'message' => 'Announcement not found.',
        ], 404);
    }

    // Fetch category name from ID
    $categoryName = \DB::table('announcement_categories')->where('id', $request->category_id)->value('name');

    if ($categoryName == 'Brand_Specific') {
        $rules['brand_id'] = [
            'required',
            'array',
            function ($attribute, $value, $fail) {
                if (empty(array_filter($value))) {
                    $fail('At least one brand must be selected.');
                }
            },
        ];
    }

    if ($categoryName == 'Role_Specific') {
        $rules['role_id'] = [
            'required',
            'array',
            function ($attribute, $value, $fail) {
                if (empty(array_filter($value))) {
                    $fail('At least one role must be selected.');
                }
            },
        ];
    }

    // Fix date format if sent as d-m-Y
 
    // if ($request->filled('reminder_date')) {
    //     $request->merge([
    //         'reminder_date' => \Carbon\Carbon::createFromFormat('d-m-Y', $request->reminder_date)->format('Y-m-d')
    //     ]);
    // } 

    $validator = \Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors(),
        ], 422);
    }

    // File Upload (replace existing file if new one uploaded)
    if ($request->hasFile('announcement_file')) {
        $fileName = time() . '_' . uniqid() . '.' . $request->announcement_file->extension();
        $request->announcement_file->move(public_path('announcement_file'), $fileName);
        $announcement->announcement_file = $fileName;
    }

    // Update Announcement
    $announcement->title = $request->title;
    $announcement->category_id = $request->category_id;
    $announcement->description = $request->description;
    $announcement->reminder_date =  date('Y-m-d', strtotime($request->reminder_date));;
    $announcement->department = $request->department_id;

    // Brand Specific
    $announcement->brand_id = ($categoryName == 'Brand_Specific')
        ? implode(',', $request->brand_id)
        : null;

    // Role Specific
    $announcement->role_id = ($categoryName == 'Role_Specific')
        ? implode(',', $request->role_id)
        : null;

    $announcement->save();

    // Activity Log
    $typeoflog = 'announcement updated: '.$announcement->title;
    addLogActivity([
        'type' => 'warning',
        'note' => json_encode([
            'title'   => ucfirst($typeoflog),
            'message' => 'Announcement "'.$announcement->title.'" has been updated'
        ]),
        'module_id'        => $announcement->id,
        'module_type'      => 'announcement',
        'notification_type'=> ucfirst($typeoflog),
        'created_by'       => auth()->id(),
    ]);

    // Notifications
    $this->sendAnnouncementNotifications($announcement, $categoryName, $request);

    return response()->json([
        'status'  => 'success',
        'message' => 'Announcement successfully updated.',
        'data'    => $announcement
    ], 200);
}

public function deleteAnnouncement(Request $request)
{
    // Permission Check
    if (!auth()->user()->can('delete announcement') && auth()->user()->type != 'super admin') {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission denied.',
        ], 403);
    }

    // Validate input
    $validator = \Validator::make($request->all(), [
        'id' => 'required|exists:announcements,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    // Fetch the announcement
    $announcement = Announcement::find($request->id);
    if (!$announcement) {
        return response()->json([
            'status' => 'error',
            'message' => 'Announcement not found.',
        ], 404);
    }

    $title = $announcement->title; // Store title for logging

    // Delete the announcement file if exists
    if ($announcement->announcement_file && file_exists(public_path('announcement_file/'.$announcement->announcement_file))) {
        @unlink(public_path('announcement_file/'.$announcement->announcement_file));
    }

    // Delete the record
    $announcement->delete();

    // Activity Log
    $typeoflog = 'announcement deleted: '.$title;
    addLogActivity([
        'type' => 'danger',
        'note' => json_encode([
            'title'   => ucfirst($typeoflog),
            'message' => 'Announcement "'.$title.'" has been deleted'
        ]),
        'module_id'        => $request->id,
        'module_type'      => 'announcement',
        'notification_type'=> ucfirst($typeoflog),
        'created_by'       => auth()->id(),
    ]);

    return response()->json([
        'status'  => 'success',
        'message' => 'Announcement successfully deleted.',
    ], 200);
}

public function announcementDetail(Request $request)
{
    // Permission Check
    if (!auth()->user()->can('view announcement') && auth()->user()->type != 'super admin') {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission denied.',
        ], 403);
    }

    // Validate input
    $validator = \Validator::make($request->all(), [
        'id' => 'required|exists:announcements,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    // Fetch announcement
    $announcement = Announcement::find($request->id);

    if (!$announcement) {
        return response()->json([
            'status' => 'error',
            'message' => 'Announcement not found.',
        ], 404);
    }

    // Build response data
    $data = [
        'id'              => $announcement->id,
        'title'           => $announcement->title,
        'description'     => $announcement->description,
        'category_id'     => $announcement->category_id,
        'category_name'   => $announcement->category_name,
        'department_id'   => $announcement->department,
        'department_name' => $announcement->department_name,
        'brand_ids'       => $announcement->brand_id ? explode(',', $announcement->brand_id) : [],
        'brand_names'     => $announcement->brand_names,
        'role_ids'        => $announcement->role_id ? explode(',', $announcement->role_id) : [],
        'role_names'      => $announcement->role_names,
        'reminder_date'   => date('d-m-Y', strtotime($announcement->reminder_date)),
        'announcement_file' => $announcement->announcement_file ? url('announcement_file/'.$announcement->announcement_file) : null,
        'created_by'      => $announcement->creator?->name,
        'created_at'      => $announcement->created_at,
        'updated_at'      => $announcement->updated_at,
    ];

    return response()->json([
        'status'  => 'success',
        'message' => 'Announcement details retrieved successfully.',
        'data'    => $data,
    ], 200);
}






   private function sendAnnouncementNotifications($announcement, $categoryName, $request)
{
    if ($categoryName == 'Brand_Specific') {
        $users = User::whereIn('brand_id', $request->brand_id)->pluck('id')->toArray();
        $this->pushAnnouncementNotification($users, $announcement);
    }

    if ($categoryName == 'General') {
        $users = User::pluck('id')->toArray();
        $this->pushAnnouncementNotification($users, $announcement);
    }

    if ($categoryName == 'Role_Specific') {
        $users = User::leftJoin('roles', 'roles.name', '=', 'users.type')
            ->whereIn('roles.id', $request->role_id)
            ->pluck('users.id')->toArray();

        $this->pushAnnouncementNotification($users, $announcement);
    }
}

private function pushAnnouncementNotification($users, $announcement)
{
    foreach ($users as $userId) {

        if (auth()->id() == $userId) continue;

        $notification = [
            'type'        => 'Announcements',
            'data_type'   => 'Announcements_Created',
            'sender_id'   => auth()->id(),
            'receiver_id' => $userId,
            'data'        => 'New Announcement Created',
            'is_read'     => 0,
            'related_id'  => $announcement->id,
            'created_by'  => auth()->id(),
            'created_at'  => now(),
        ];

        addNotifications($notification);
    }
}
 
}
