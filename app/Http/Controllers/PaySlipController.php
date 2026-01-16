<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PaySlip;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaySlipController extends Controller
{
    public function index(Request $request)
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);
        // Fetch employees and prepare month/year options
        $jobsQuery = PaySlip::select(
            'pay_slips.*',
            'brand.name as brandname',   // Renamed for better readability
            'brand.avatar as avatar',   // Renamed for better readability
            'regions.name as regionname', // Renamed for better readability
            'branches.name as branchname', // Renamed for better readability
            'employees.name as username',
        )
        ->with([
            'employees',
            'created_by:id,name',
            'employee.salaryType',
            'employee.user' // Assuming `employee` is a relationship; corrected casing
        ])
        ->leftJoin('employees', 'employees.id', '=', 'pay_slips.employee_id')
        ->leftJoin('users', 'users.id', '=', 'employees.user_id') // Corrected column relation
        ->leftJoin('users as brand', 'brand.id', '=', 'users.brand_id') // Referring to employees' brand_id
        ->leftJoin('regions', 'regions.id', '=', 'users.region_id')
        ->leftJoin('branches', 'branches.id', '=', 'users.branch_id') ;

            $userType = \Auth::user()->type;
            if (in_array($userType, ['super admin', 'Admin Team'])) {
                // No additional filtering needed
            } elseif ($userType === 'company') {
                $jobsQuery->where('pay_slips.brand_id', \Auth::user()->id);
            } elseif (in_array($userType, ['Project Director', 'Project Manager'])) {
                $jobsQuery->whereIn('pay_slips.brand_id', array_keys(FiltersBrands()));
            } elseif (($userType === 'Region Manager') && !empty(\Auth::user()->region_id)) {
                $jobsQuery->where('pay_slips.region_id', \Auth::user()->region_id);
            } elseif (($userType === 'Branch Manager' || in_array($userType, ['Careers Consultant', 'Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) && !empty(\Auth::user()->branch_id)) {
                $jobsQuery->where('pay_slips.branch_id', \Auth::user()->branch_id);
            } else {
                $jobsQuery->where('pay_slips.employee_id', \Auth::user()?->employee?->id);
            }

            if ($request->filled('brand')) {
                $jobsQuery->where('users.brand_id', $request->brand);
            }

            if ($request->filled('region_id')) {
                $jobsQuery->where('users.region_id', $request->region_id);
            }

            if ($request->filled('branch_id')) {
                $jobsQuery->where('users.branch_id', $request->branch_id);
            }

            if ($request->filled('employee_id')) {
                $jobsQuery->where('users.id', $request->employee_id);
            }

            if ($request->filled('created_at')) {
                $jobsQuery->whereDate('users.created_at', '=', $request->created_at);
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $jobsQuery->where(function ($query) use ($search) {
                    $query->where('brand.name', 'like', "%$search%")
                        ->orWhere('regions.name', 'like', "%$search%")
                        ->orWhere('branches.name', 'like', "%$search%")
                        ->orWhere('users.name', 'like', "%$search%")
                        ->orWhere('pay_slips.net_payble', 'like', "%$search%");
                });
            }


           if ($request->filled('tag_ids')) {
                $tagIds = explode(',', $request->input('tag_ids')); // "6,4"

                $jobsQuery->where(function ($query) use ($tagIds) {
                    foreach ($tagIds as $tagId) {
                        $query->orWhereRaw("FIND_IN_SET(?, users.tag_ids)", [$tagId]);
                    }
                });
            }

           // dd($jobsQuery->toSql(), $jobsQuery->getBindings());



                if ($request->input('download_csv')) {
                    $pay_slips = $jobsQuery->get();
                    $csvFileName = 'pay_slips_' . time() . '.csv';
                    $headers = [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="' . $csvFileName . '"',
                    ];

                    $callback = function () use ($pay_slips) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, [
                            'Brand',
                            'Region',
                            'Branch',
                            'User',
                            'Salary Month',
                            'Basic Salary'
                        ]);
                        foreach ($pay_slips as $pay_slip) {
                            fputcsv($file, [
                                $pay_slip->brandname,
                                $pay_slip->regionname,
                                $pay_slip->branchname,
                                $pay_slip->username,
                                $pay_slip->salary_month,
                                $pay_slip->basic_salary,
                            ]);
                        }
                        fclose($file);
                    };

                    return response()->stream($callback, 200, $headers);
                }
                // Pagination
                $payslips = $jobsQuery
                    ->orderBy('pay_slips.created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'status' => 'success',
                    'data' => $payslips->items(),
                    'baseurl' => asset('/'),
                    'current_page' => $payslips->currentPage(),
                    'last_page' => $payslips->lastPage(),
                    'total_records' => $payslips->total(),
                    'per_page' => $payslips->perPage(),
                    'message' => __('Payslips retrieved successfully'),
                ]);
    }
    public function CreatePaySlips(Request $request)
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validation rules
        $rules = [
            'month'     => 'required|string|size:2|in:' . implode(',', array_keys($this->getMonths())),
            'year'      => 'required|string|size:4|regex:/^\d{4}$/',
            'brand_id'  => 'required',
            'region_id' => 'required',
            'branch_id' => 'required',
        ];

        // Apply conditions based on user type
        $userType = Auth::user()->type;
        if (in_array($userType, ["Project Manager", "Project Director", "Admin Team", "Super Admin", "super admin"])) {
            unset($rules['region_id'], $rules['branch_id']);
        } elseif ($userType === "Region Manager") {
            unset($rules['branch_id']);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $formattedMonthYear = $request->year . '-' . $request->month;
        $employeeID = $request->singleUserID
            ? User::findOrFail($request->singleUserID)->employee->id
            : 0;

        // ðŸ”¹ Inline getExistingPayslips
        $existingPayslipsQuery = PaySlip::where('salary_month', $formattedMonthYear);
        if ($request->has(['brand_id', 'region_id', 'branch_id'])) {
            $existingPayslipsQuery->where('brand_id', $request->brand_id)
                ->where('region_id', $request->region_id)
                ->where('branch_id', $request->branch_id);
        }
        if ($request->singleUserID) {
            $existingPayslipsQuery->where('employee_id', $request->singleUserID);
        }
        $existingPayslips = $existingPayslipsQuery->pluck('employee_id');

        // ðŸ”¹ Inline getEligibleEmployees
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $usersQuery = User::whereNotIn('type', $excludedTypes);

        if (!empty($request->brand_id)) {
            $usersQuery->where('brand_id', $request->brand_id);
        }
        if (!empty($request->region_id)) {
            $usersQuery->where('region_id', $request->region_id);
        }
        if (!empty($request->branch_id)) {
            $usersQuery->where('branch_id', $request->branch_id);
        }

        $user = Auth::user();
        if ($request->singleUserID) {
            $usersQuery->where('id', $request->singleUserID);
        } elseif ($user->type == 'super admin') {
            // no filter
        } elseif ($user->type == 'company') {
            $usersQuery->where('brand_id', $user->id);
        } else {
            $usersQuery->where('brand_id', $user->brand_id);
        }

        $userIds = $usersQuery->pluck('id');

        if ($request->singleUserID) {
            $eligibleEmployees = Employee::whereNotNull('salary')
                ->whereIn('user_id', $userIds)
                ->with('user')
                ->get();
        } else {
            $eligibleEmployees = Employee::where('company_doj', '<=', now()->endOfMonth())
                ->whereNotIn('id', $existingPayslips)
                ->whereNotNull('salary')
                ->whereIn('user_id', $userIds)
                ->with('user')
                ->get();
        }

        // âœ… FIX: Correct error messages
        if ($eligibleEmployees->isEmpty()) {
            if (!empty($request->singleUserID)) {
                if ($existingPayslips->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Sorry, payslip cannot be created because this user\'s salary missing in information.')
                    ], 400);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Payslips have already been created.')
                    ], 400);
                }
            } else {
                if ($existingPayslips->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Sorry, no eligible employees found for payslip generation.')
                    ], 400);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Payslips have already been created.')
                    ], 400);
                }
            }
        }

        // ðŸ”¹ Inline generatePayslips
        $anyNewCreated = false; // track if any new record was created

        foreach ($eligibleEmployees as $employee) {
            $user = User::find($employee->user_id);
            $payslip = PaySlip::firstOrCreate([
                'employee_id' => $employee->id,
                'salary_month' => $formattedMonthYear,
                'created_by' => Auth::id() ?? 0,
                'brand_id' => $user?->brand_id,
                'region_id' => $user?->region_id,
                'branch_id' => $user?->branch_id
            ], [
                'net_payble' => $employee->get_net_salary(),
                'status' => 0,
                'basic_salary' => $employee->salary ?? 0,
                'allowance' => Employee::allowance($employee->id),
                'commission' => Employee::commission($employee->id),
                'loan' => Employee::loan($employee->id),
                'saturation_deduction' => Employee::saturation_deduction($employee->id),
                'other_payment' => Employee::other_payment($employee->id),
                'overtime' => Employee::overtime($employee->id),
            ]);

            // ðŸ”¹ check if new created
            if ($payslip->wasRecentlyCreated) {
                $anyNewCreated = true;
            }

            $additionalTags = ['salary_month' => $formattedMonthYear];
            $templateId = null;
            $ccchecklist = [
                'is_branch_manager'   => 'yes',
                'is_region_manager'   => 'yes',
                'is_project_manager'  => 'yes',
                'is_scrop_attendance' => 'yes'
            ];

            addToEmailQueue(
                $user->id,
                'pay_slip_email_template',
                $templateId,
                $ccchecklist,
                [],
                $additionalTags
            );

            $typeoflog = 'payslip';
            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => $user->name . ' ' . $typeoflog . ' created',
                    'message' => $user->name . ' ' . $typeoflog . ' created'
                ]),
                'module_id' => $payslip->id,
                'module_type' => 'employee',
                'notification_type' => ' ' . $typeoflog . ' Created',
            ]);
            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => $user->name . ' ' . $typeoflog . ' created',
                    'message' => $user->name . ' ' . $typeoflog . ' created'
                ]),
                'module_id' => $user->id,
                'module_type' => 'employeeprofile',
                'notification_type' => ' ' . $typeoflog . ' Created',
            ]);
        }

        // ðŸ”¹ Inline sendNotifications
        $settings = Utility::settings(Auth::id());
        if (!empty($settings['payslip_notification'])) {
            Utility::send_slack_msg("Payslip generated for $formattedMonthYear.");
        }
        if (!empty($settings['telegram_payslip_notification'])) {
            Utility::send_telegram_msg("Payslip generated for $formattedMonthYear.");
        }

        // Fetch and return generated payslips
        $payslips = PaySlip::where('salary_month', $formattedMonthYear)->get();

        if ($anyNewCreated) {
            return response()->json([
                'status' => 'success',
                'message' => __('Payslips successfully created.'),
                'data' => $payslips,
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Payslips have already been created.'),
                'data' => $payslips,
            ], 400);
        }
    }

    // public function store(Request $request)
    // {
    //     // Check user permissions
    //     if (!$this->canManagePaySlips()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => __('Permission denied.')
    //         ], 403);
    //     }

    //     // Validate request input
    //     $validator = Validator::make($request->all(), [
    //         'month' => 'required|string|size:2|in:' . implode(',', array_keys($this->getMonths())),
    //         'year' => 'required|string|size:4|regex:/^\d{4}$/',
    //     ]);

    //     if ($validator->fails()) {
    //         return $this->errorResponse($validator->errors()->first(), 422);
    //     }

    //     $formattedMonthYear = $request->year . '-' . $request->month;
    //     if($request->singleUserID){
    //         $employeeID = User::findOrFail($request->singleUserID)->employee->id;
    //     }else{
    //         $employeeID = 0;
    //     }


    //     // Check for existing payslips
    //     $existingPayslips = $this->getExistingPayslips($formattedMonthYear,$employeeID);



    //     // Get eligible employees
    //     $eligibleEmployees = $this->getEligibleEmployees($formattedMonthYear, $existingPayslips, $request->input('singleUserID', 0));


    //     if ($eligibleEmployees->isEmpty()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => __('Payslips have already been created.')
    //         ], 400);
    //     }

    //     // Generate payslips and send notifications
    //     $this->generatePayslips($eligibleEmployees, $formattedMonthYear);
    //     $this->sendNotifications($formattedMonthYear);

    //     // Fetch and return generated payslips
    //     $payslips = $this->fetchPayslips($formattedMonthYear);
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => __('Payslips successfully created.'),
    //         'data' => $payslips,
    //     ], 201);
    // }
    public function store(Request $request)
        {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validation rules
        $rules = [
            'month'     => 'required|string|size:2|in:' . implode(',', array_keys($this->getMonths())),
            'year'      => 'required|string|size:4|regex:/^\d{4}$/',
            'brand_id'  => 'required',
            'region_id' => 'required',
            'branch_id' => 'required',
        ];

        // Apply conditions based on user type
        $userType = Auth::user()->type;
        if (in_array($userType, ["Project Manager", "Project Director", "Admin Team", "Super Admin", "super admin"])) {
            unset($rules['region_id'], $rules['branch_id']);
        } elseif ($userType === "Region Manager") {
            unset($rules['branch_id']);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $formattedMonthYear = $request->year . '-' . $request->month;
        $employeeID = $request->singleUserID
            ? User::findOrFail($request->singleUserID)->employee->id
            : 0;

        // ðŸ”¹ Inline getExistingPayslips
        $existingPayslipsQuery = PaySlip::where('salary_month', $formattedMonthYear);
        if ($request->has(['brand_id', 'region_id', 'branch_id'])) {
            $existingPayslipsQuery->where('brand_id', $request->brand_id)
                ->where('region_id', $request->region_id)
                ->where('branch_id', $request->branch_id);
        }
        if ($request->singleUserID) {
            $existingPayslipsQuery->where('employee_id', $request->singleUserID);
        }
        $existingPayslips = $existingPayslipsQuery->pluck('employee_id');

        // ðŸ”¹ Inline getEligibleEmployees
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $usersQuery = User::whereNotIn('type', $excludedTypes);

        if (!empty($request->brand_id)) {
            $usersQuery->where('brand_id', $request->brand_id);
        }
        if (!empty($request->region_id)) {
            $usersQuery->where('region_id', $request->region_id);
        }
        if (!empty($request->branch_id)) {
            $usersQuery->where('branch_id', $request->branch_id);
        }

        $user = Auth::user();
        if ($request->singleUserID) {
            $usersQuery->where('id', $request->singleUserID);
        } elseif ($user->type == 'super admin') {
            // no filter
        } elseif ($user->type == 'company') {
            $usersQuery->where('brand_id', $user->id);
        } else {
            $usersQuery->where('brand_id', $user->brand_id);
        }

        $userIds = $usersQuery->pluck('id');

        if ($request->singleUserID) {
            $eligibleEmployees = Employee::whereNotNull('salary')
                ->whereIn('user_id', $userIds)
                ->with('user')
                ->get();
        } else {
            $eligibleEmployees = Employee::where('company_doj', '<=', now()->endOfMonth())
                ->whereNotIn('id', $existingPayslips)
                ->whereNotNull('salary')
                ->whereIn('user_id', $userIds)
                ->with('user')
                ->get();
        }

        // âœ… FIX: Correct error messages
        if ($eligibleEmployees->isEmpty()) {
            if (!empty($request->singleUserID)) {
                if ($existingPayslips->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Sorry, payslip cannot be created because this user\'s salary missing in information.')
                    ], 400);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Payslips have already been created.')
                    ], 400);
                }
            } else {
                if ($existingPayslips->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Sorry, no eligible employees found for payslip generation.')
                    ], 400);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Payslips have already been created.')
                    ], 400);
                }
            }
        }

        // ðŸ”¹ Inline generatePayslips
        $anyNewCreated = false; // track if any new record was created

        foreach ($eligibleEmployees as $employee) {
            $user = User::find($employee->user_id);
            $payslip = PaySlip::firstOrCreate([
                'employee_id' => $employee->id,
                'salary_month' => $formattedMonthYear,
                'created_by' => Auth::id() ?? 0,
                'brand_id' => $user?->brand_id,
                'region_id' => $user?->region_id,
                'branch_id' => $user?->branch_id
            ], [
                'net_payble' => $employee->get_net_salary(),
                'status' => 0,
                'basic_salary' => $employee->salary ?? 0,
                'allowance' => Employee::allowance($employee->id),
                'commission' => Employee::commission($employee->id),
                'loan' => Employee::loan($employee->id),
                'saturation_deduction' => Employee::saturation_deduction($employee->id),
                'other_payment' => Employee::other_payment($employee->id),
                'overtime' => Employee::overtime($employee->id),
            ]);

            // ðŸ”¹ check if new created
            if ($payslip->wasRecentlyCreated) {
                $anyNewCreated = true;
            }

            $additionalTags = ['salary_month' => $formattedMonthYear];
            $templateId = null;
            $ccchecklist = [
                'is_branch_manager'   => 'yes',
                'is_region_manager'   => 'yes',
                'is_project_manager'  => 'yes',
                'is_scrop_attendance' => 'yes'
            ];

            addToEmailQueue(
                $user->id,
                'pay_slip_email_template',
                $templateId,
                $ccchecklist,
                [],
                $additionalTags
            );

            $typeoflog = 'payslip';
            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => $user->name . ' ' . $typeoflog . ' created',
                    'message' => $user->name . ' ' . $typeoflog . ' created'
                ]),
                'module_id' => $payslip->id,
                'module_type' => 'employee',
                'notification_type' => ' ' . $typeoflog . ' Created',
            ]);
            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => $user->name . ' ' . $typeoflog . ' created',
                    'message' => $user->name . ' ' . $typeoflog . ' created'
                ]),
                'module_id' => $user->id,
                'module_type' => 'employeeprofile',
                'notification_type' => ' ' . $typeoflog . ' Created',
            ]);
        }

        // ðŸ”¹ Inline sendNotifications
        $settings = Utility::settings(Auth::id());
        if (!empty($settings['payslip_notification'])) {
            Utility::send_slack_msg("Payslip generated for $formattedMonthYear.");
        }
        if (!empty($settings['telegram_payslip_notification'])) {
            Utility::send_telegram_msg("Payslip generated for $formattedMonthYear.");
        }

        // Fetch and return generated payslips
        $payslips = PaySlip::where('salary_month', $formattedMonthYear)->get();

        if ($anyNewCreated) {
            return response()->json([
                'status' => 'success',
                'message' => __('Payslips successfully created.'),
                'data' => $payslips,
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Payslips have already been created.'),
                'data' => $payslips,
            ], 400);
        }
    }
    // {
    //     // Check user permissions
    //     if (!$this->canManagePaySlips()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => __('Permission denied.')
    //         ], 403);
    //     }

    //     $rules = [
    //         'month'     => 'required|string|size:2|in:' . implode(',', array_keys($this->getMonths())),
    //         'year'      => 'required|string|size:4|regex:/^\d{4}$/',
    //         'brand_id'  => 'required',   // default required
    //         'region_id' => 'required',   // define first
    //         'branch_id' => 'required',   // define first
    //     ];

    //     // Apply conditions based on user type
    //     $userType = Auth::user()->type;

    //     if (in_array($userType, ["Project Manager", "Project Director", "Admin Team", "Super Admin"])) {
    //         unset($rules['region_id'], $rules['branch_id']);
    //     } elseif ($userType === "Region Manager") {
    //         unset($rules['branch_id']);
    //     }

    //     $validator = Validator::make($request->all(), $rules);


    //     if ($validator->fails()) {
    //         return $this->errorResponse($validator->errors()->first(), 422);
    //     }

    //     $formattedMonthYear = $request->year . '-' . $request->month;
    //     if($request->singleUserID){
    //         $employeeID = User::findOrFail($request->singleUserID)->employee->id;
    //     }else{
    //         $employeeID = 0;
    //     }


    //     // Check for existing payslips
    //     $existingPayslips = $this->getExistingPayslips($formattedMonthYear,$employeeID,$request->input('singleUserID', 0),$request->input('brand_id', 0),$request->input('region_id', 0),$request->input('branch_id', 0));



    //     // Get eligible employees
    //     $eligibleEmployees = $this->getEligibleEmployees($formattedMonthYear, $existingPayslips, $request->input('singleUserID', 0),$request->input('brand_id', 0),$request->input('region_id', 0),$request->input('branch_id', 0));


    //     if ($eligibleEmployees->isEmpty()) {
    //         if (!empty($request->input('singleUserID'))) {
    //             if ($existingPayslips->isEmpty()) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => __('Sorry, payslip cannot be created because this user\'s salary missing in information.')
    //                 ], 400);
    //             } else {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => __('Payslips have already been created.')
    //                 ], 400);
    //             }
    //         } else {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => __('Payslips have already been created.')
    //             ], 400);
    //         }
    //     }


    //     // Generate payslips and send notifications
    //     $this->generatePayslips($eligibleEmployees, $formattedMonthYear,$request->input('brand_id', 0),$request->input('region_id', 0),$request->input('branch_id', 0));
    //     $this->sendNotifications($formattedMonthYear);

    //     // Fetch and return generated payslips
    //     $payslips = $this->fetchPayslips($formattedMonthYear);
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => __('Payslips successfully created.'),
    //         'data' => $payslips,
    //     ], 201);
    // }

    public function destroy($id)
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Find and delete payslip
        $payslip = PaySlip::where('id', $id)
            ->where('created_by', Auth::id())
            ->first();

        if (!$payslip) {
            return response()->json([
                'status' => 'error',
                'message' => "Payslip not found.",
            ], 404);
        }

         //    =================== delete ===========

            $employee = Employee::find($payslip->employee_id);
            $typeoflog = 'payslip';
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $employee->user->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $employee->user->name .  ' '.$typeoflog.'  deleted '
                    ]),
                    'module_id' => $payslip->id,
                    'module_type' => 'payslip',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);



                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $employee->user->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $employee->user->name .  ' '.$typeoflog.'  deleted '
                    ]),
                    'module_id' => $employee->user->id,
                    'module_type' => 'employeeprofile',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);


        $payslip->delete();
        return response()->json([
            'status' => 'success',
            'message' => "Payslip deleted.",
        ], 200);
    }

    public function searchJson(Request $request)
    {
        // Validate request input
        $validator = Validator::make($request->all(), [
            'datePicker' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        // Fetch payslips for the given month
        $payslips = $this->fetchPayslips($request->datePicker);
        return response()->json([
            'status' => 'success',
            'data' => $payslips,
        ], 201);
    }

    // Helper methods

    private function canManagePaySlips()
    {
        return Auth::user()->can('manage pay slip');
    }

    private function errorResponse($message, $statusCode)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }

    private function getMonths()
    {
        return [
            '01' => 'JAN',
            '02' => 'FEB',
            '03' => 'MAR',
            '04' => 'APR',
            '05' => 'MAY',
            '06' => 'JUN',
            '07' => 'JUL',
            '08' => 'AUG',
            '09' => 'SEP',
            '10' => 'OCT',
            '11' => 'NOV',
            '12' => 'DEC',
        ];
    }

    private function getYears()
    {
        return range(date('Y'), date('Y') - 9);
    }

    private function getExistingPayslips($formattedMonthYear, $singleUserID = 0)
    {
        $query = PaySlip::where('salary_month', $formattedMonthYear);

        // Always filter by brand/region/branch for consistency
        if (request()->has(['brand_id', 'region_id', 'branch_id'])) {
            $query->where('brand_id', request('brand_id'))
                ->where('region_id', request('region_id'))
                ->where('branch_id', request('branch_id'));
        }

        // If a single user ID is provided, filter by that user as well
        if ($singleUserID != 0) {
            $query->where('employee_id', $singleUserID);
        }

        return $query->pluck('employee_id');
    }

    // private function getEligibleEmployees($formattedMonthYear, $existingPayslips)
    // {
    //     return Employee::where('company_doj', '<=', date($formattedMonthYear . '-t'))
    //         ->whereNotIn('id', $existingPayslips)
    //         ->whereNotNull('salary')
    //         ->whereNotNull('salary_type')
    //         ->get();
    // }

    private function getEligibleEmployees($formattedMonthYear, $existingPayslips,$singleUserID =0)
    {
    $excludedTypes = ['super admin', 'company', 'team', 'client'];

    // Get the base user query
    $usersQuery = User::whereNotIn('type', $excludedTypes);

    // Apply filters from request
    if (!empty(request('brand_id'))) {
        $usersQuery->where('brand_id', request('brand_id'));
    }
    if (!empty(request('region_id'))) {
        $usersQuery->where('region_id', request('region_id'));
    }
    if (!empty(request('branch_id'))) {
        $usersQuery->where('branch_id', request('branch_id'));
    }
    if (!empty(request('Name'))) {
        $usersQuery->where('name', 'like', '%' . request('Name') . '%');
    }
    if (!empty(request('Designation'))) {
        $usersQuery->where('type', 'like', '%' . request('Designation') . '%');
    }
    if (!empty(request('phone'))) {
        $usersQuery->where('phone', 'like', '%' . request('phone') . '%');
    }

    // Apply user type-specific filtering
    $user = \Auth::user();

    if ($singleUserID!= 0) {
        $usersQuery->where('id', $singleUserID);
    }elseif ($user->type == 'super admin') {
        // No additional filtering
    } elseif ($user->type == 'company') {
        $usersQuery->where('brand_id', $user->id);
    } else {
        $usersQuery->where('brand_id', $user->brand_id);
    }

    // Get the filtered user IDs
    $userIds = $usersQuery->pluck('id');




     if ($singleUserID!= 0) {


          // Fetch employees with conditions and related users
    return Employee::whereNotNull('salary')
        ->whereIn('user_id', $userIds)
        ->with('user') // Load related user data
        ->get();


    }else{
           // Fetch employees with conditions and related users
    return Employee::where('company_doj', '<=', now()->endOfMonth())
        ->whereNotIn('id', $existingPayslips)
        ->whereNotNull('salary')
        ->whereIn('user_id', $userIds)
        ->with('user') // Load related user data
        ->get();


    }

}

    private function generatePayslips($employees, $formattedMonthYear,$brand_id,$region_id,$branch_id)
    {
        foreach ($employees as $employee) {
          $user=User::find($employee->user_id);
          $payslip =  PaySlip::firstOrCreate([
                'employee_id' => $employee->id,
                'salary_month' => $formattedMonthYear,
                'created_by' => Auth::id() ?? 0,
                'brand_id' => $user?->brand_id,
                'region_id' => $user?->region_id,
                'branch_id' => $user?->branch_id
            ], [
                'net_payble' => $employee->get_net_salary(),
                'status' => 0,
                'basic_salary' => $employee->salary ?? 0,
                'allowance' => Employee::allowance($employee->id),
                'commission' => Employee::commission($employee->id),
                'loan' => Employee::loan($employee->id),
                'saturation_deduction' => Employee::saturation_deduction($employee->id),
                'other_payment' => Employee::other_payment($employee->id),
                'overtime' => Employee::overtime($employee->id),
            ]);

                 //  ========== add ============
                $user = User::find($employee->user_id);

                 $additionalTags = [
                        'salary_month' => $formattedMonthYear,
                    ];

        $templateId = null;
        $ccchecklist = [
                    'is_branch_manager'   => 'yes',
                    'is_region_manager'   => 'yes',
                    'is_project_manager'  => 'yes',
                    'is_scrop_attendance' => 'yes'
                ];

        addToEmailQueue(
            $user->id,
                'pay_slip_email_template',
                $templateId,
                $ccchecklist,
                array() ,
                $additionalTags
            );
                $typeoflog = 'payslip';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $user->name. ' '.$typeoflog.' created',
                        'message' => $user->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $payslip->id,
                    'module_type' => 'employee',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $user->name. ' '.$typeoflog.'  created',
                        'message' => $user->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $user->id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

        }
    }

    private function sendNotifications($formattedMonthYear)
    {
        $settings = Utility::settings(Auth::id());

        if (!empty($settings['payslip_notification'])) {
            Utility::send_slack_msg("Payslip generated for $formattedMonthYear.");
        }

        if (!empty($settings['telegram_payslip_notification'])) {
            Utility::send_telegram_msg("Payslip generated for $formattedMonthYear.");
        }
    }

    private function fetchPayslips($formattedMonthYear)
    {
        return PaySlip::with('employees', 'employee.salaryType')
            ->where('salary_month', $formattedMonthYear)
            ->where('created_by', Auth::id())
            ->get();
    }


    public function updateEmployeeSalary(Request $request, $id)
    {
        // Validate the request data
        $validator = \Validator::make($request->all(), [
            'salary_type' => 'required|integer', // Ensure it's an integer
            'salary' => 'required|numeric',      // Ensure it's numeric
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Find the employee or return a 404 error if not found
        $employee = Employee::where('user_id',$id)->first();
              $originalData = $employee->toArray();
        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee not found.',
            ], 404);
        }

        // Update the employee's salary details
        $employee->salary_type = $request->salary_type;
        $employee->salary = $request->salary;
        $employee->save();



     // ============ edit ============





           // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($employee->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $employee->$field
                ];
                $updatedFields[] = $field;
            }
        }
        $user = User::find($employee->user_id);
         $typeoflog = 'set salary';

        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name .  ' '.$typeoflog.'  updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $employee->user_id,
                    'module_type' => 'setsalary',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }


        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name .  ' '.$typeoflog.' updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $employee->user_id,
                    'module_type' => 'employeeprofile',
                    'notification_type' =>  ' '.$typeoflog.' Updated'
                ]);
            }

        // Return a success response
        return response()->json([
            'status' => 'success',
            'message' => 'Employee salary updated successfully.',
            'data' => $employee,
        ], 200);
    }

    public function Payslip_fetch(Request $request)
    {
        // Validate the request
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        try {
            // Retrieve the employee ID from the user
            $userId = User::findOrFail($request->id)->employee->id;
            if (!$userId) {
                return response()->json([
                    'error' => 'User not found for the specified employee.'
                ], 404);
            }
            // Retrieve the payslip based on employee ID
            $payslip = PaySlip::where('employee_id', $userId)->get();

            if (!$payslip) {
                return response()->json([
                    'error' => 'Payslip not found for the specified employee.'
                ], 404);
            }

            // Retrieve the employee data
            $employee = Employee::find($userId);

            if (!$employee) {
                return response()->json([
                    'error' => 'Employee not found.'
                ], 404);
            }

            // Retrieve payslip details using a utility function
            $payslipDetail = Utility::employeePayslipDetail($userId);

            return response()->json([
                'payslip' => $payslip,
                'employee' => $employee,
                'payslipDetail' => $payslipDetail
            ]);

        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'error' => 'An error occurred while fetching the payslip.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteBulkPayslip(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'ids' => 'required|string', // Expecting comma-separated IDs
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Parse and Delete Leads
        $leadIds = array_filter(explode(',', $request->ids));

        if (empty($leadIds)) {
            return response()->json([
                'status' => 'error',
                'message' => __('At least select one PaySlip.')
            ], 400);
        }

        $deletedCount = PaySlip::whereIn('id', $leadIds)->delete();

        if ($deletedCount > 0) {
            // Log Activity
            addLogActivity([
                'type' => 'warning',
                'note' => json_encode([
                    'title' => 'PaySlip Deleted',
                    'message' => count($leadIds) . ' PaySlip deleted successfully'
                ]),
                'module_id' => null,
                'module_type' => 'lead',
                'notification_type' => 'PaySlip Deleted'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('PaySlip deleted successfully.'),
                'deleted_count' => $deletedCount
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('No PaySlip were deleted. Please check the IDs.')
            ], 404);
        }
    }

    private function getEligibleEmployeeGenerateEachMonth($formattedMonthYear, $existingPayslips,$singleUserID =0)
    {
    $excludedTypes = ['super admin', 'company', 'team', 'client'];

    // Get the base user query
    $usersQuery = User::whereNotIn('type', $excludedTypes);

    // Apply filters from request
    if (!empty(request('brand_id'))) {
        $usersQuery->where('brand_id', request('brand_id'));
    }
    if (!empty(request('region_id'))) {
        $usersQuery->where('region_id', request('region_id'));
    }
    if (!empty(request('branch_id'))) {
        $usersQuery->where('branch_id', request('branch_id'));
    }
    if (!empty(request('Name'))) {
        $usersQuery->where('name', 'like', '%' . request('Name') . '%');
    }
    if (!empty(request('Designation'))) {
        $usersQuery->where('type', 'like', '%' . request('Designation') . '%');
    }
    if (!empty(request('phone'))) {
        $usersQuery->where('phone', 'like', '%' . request('phone') . '%');
    }
    // Get the filtered user IDs
    $userIds = $usersQuery->pluck('id');
    return Employee::where('company_doj', '<=', now()->endOfMonth())
        ->whereNotIn('id', $existingPayslips)
        ->whereNotNull('salary')
        ->whereIn('user_id', $userIds)
        ->with('user') // Load related user data
        ->get();
}
    public function PayslipAutoGenerateEachMonth(Request $request)
    {
        $month = $request->input('month', now()->format('m'));
        $year  = $request->input('year', now()->format('Y'));
        $formattedMonthYear = $year . '-' . $month;
        $existingPayslips = $this->getExistingPayslips(
            $formattedMonthYear,
            0,
            0,
            0,
            0,
            0
        );

        // Get eligible employees (no brand/region/branch filter)
        $eligibleEmployees = $this->getEligibleEmployeeGenerateEachMonth(
            $formattedMonthYear,
            $existingPayslips,
            0,
            0,
            0,
            0
        );

        if ($eligibleEmployees->isEmpty()) {

        }
        $this->generatePayslips($eligibleEmployees, $formattedMonthYear, 0, 0, 0);
        return true;
    }

}
