<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use App\Models\AllowanceOption;
use App\Models\Commission;
use App\Models\DeductionOption;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanOption;
use App\Models\OtherPayment;
use App\Models\Overtime;
use App\Models\PayslipType;
use App\Models\SaturationDeduction;
use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SetSalaryController extends Controller
{
    public function getSetSalaries(Request $request)
    {
        // Permission Check
        if (!Auth::user()->can('manage set salary')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'perPage'   => 'nullable|integer|min:1',
            'page'      => 'nullable|integer|min:1',
            'brand_id'  => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Pagination setup
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Base query
        $query = Employee::select(
            'employees.*',
            'regions.name as region',
            'branches.name as branch',
            'BrandUsers.name as brand'
        )
        ->with(['salary_type'])
        ->leftJoin('users', 'users.id', '=', 'employees.user_id')
        ->leftJoin('users as BrandUsers', 'BrandUsers.id', '=', 'users.brand_id')
        ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
        ->leftJoin('regions', 'regions.id', '=', 'users.region_id')->whereNotNull('BrandUsers.name');

        // Apply role-based filtering
        $query = RoleBaseTableGet($query, 'users.brand_id', 'users.region_id', 'users.branch_id', 'users.created_by');

        // Apply filters
        if ($request->filled('created_at')) {
            $query->whereDate('users.created_at', $request->created_at);
        }
        if ($request->filled('brand_id')) {
            $query->where('users.brand_id', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $query->where('users.region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('users.branch_id', $request->branch_id);
        }
        if ($request->filled('employee_id')){
            $query->where('employees.user_id', $request->employee_id);
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('users.created_at', 'like', "%$search%")
                    ->orWhere('regions.name', 'like', "%$search%")
                    ->orWhere('branches.name', 'like', "%$search%")
                    ->orWhere('employees.name', 'like', "%$search%")
                    ->orWhere('BrandUsers.name', 'like', "%$search%");
            });
        }
         
           if ($request->filled('tag_ids')) {
                $tagIds = explode(',', $request->input('tag_ids')); // "6,4"

                $query->where(function ($q) use ($tagIds) {
                    foreach ($tagIds as $tagId) {
                        $q->orWhereRaw("FIND_IN_SET(?, users.tag_ids)", [$tagId]);
                    }
                });
            }
        // Get total count before pagination
        $totalRecords = $query->count();

        // Paginate results
        $employees = $query->orderBy('employees.id', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return JSON response
        return response()->json([
            'status' => 'success',
            'data' => $employees->items(),
            'current_page' => $employees->currentPage(),
            'last_page' => $employees->lastPage(),
            'total_records' => $totalRecords,
            'per_page' => $employees->perPage(),
        ], 200);
    }


    public function edit($id)
    {
        if(\Auth::user()->can('edit set salary'))
        {
            $payslip_type      = PayslipType::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $allowance_options = AllowanceOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $loan_options      = LoanOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $deduction_options = DeductionOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            if(\Auth::user()->type == 'employee')
            {
                $currentEmployee      = Employee::where('user_id', '=', \Auth::user()->id)->first();
                $allowances           = Allowance::where('employee_id', $currentEmployee->id)->get();
                $commissions          = Commission::where('employee_id', $currentEmployee->id)->get();
                $loans                = Loan::where('employee_id', $currentEmployee->id)->get();
                $saturationdeductions = SaturationDeduction::where('employee_id', $currentEmployee->id)->get();
                $otherpayments        = OtherPayment::where('employee_id', $currentEmployee->id)->get();
                $overtimes            = Overtime::where('employee_id', $currentEmployee->id)->get();
                $employee             = Employee::where('user_id', '=', \Auth::user()->id)->first();

                return view('setsalary.employee_salary', compact('employee', 'payslip_type', 'allowance_options', 'commissions', 'loan_options', 'overtimes', 'otherpayments', 'saturationdeductions', 'loans', 'deduction_options', 'allowances'));

            }
            else
            {
                $allowances           = Allowance::where('employee_id', $id)->get();
                $commissions          = Commission::where('employee_id', $id)->get();
                $loans                = Loan::where('employee_id', $id)->get();
                $saturationdeductions = SaturationDeduction::where('employee_id', $id)->get();
                $otherpayments        = OtherPayment::where('employee_id', $id)->get();
                $overtimes            = Overtime::where('employee_id', $id)->get();
                $employee             = Employee::find($id);

                return view('setsalary.edit', compact('employee', 'payslip_type', 'allowance_options', 'commissions', 'loan_options', 'overtimes', 'otherpayments', 'saturationdeductions', 'loans', 'deduction_options', 'allowances'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show($id)
    {
        $payslip_type      = PayslipType::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $allowance_options = AllowanceOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $loan_options      = LoanOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $deduction_options = DeductionOption::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        if(\Auth::user()->type == 'employee')
        {
            $currentEmployee      = Employee::where('user_id', '=', \Auth::user()->id)->first();
            $allowances           = Allowance::where('employee_id', $currentEmployee->id)->get();
            $commissions          = Commission::where('employee_id', $currentEmployee->id)->get();
            $loans                = Loan::where('employee_id', $currentEmployee->id)->get();
            $saturationdeductions = SaturationDeduction::where('employee_id', $currentEmployee->id)->get();
            $otherpayments        = OtherPayment::where('employee_id', $currentEmployee->id)->get();
            $overtimes            = Overtime::where('employee_id', $currentEmployee->id)->get();
            $employee             = Employee::where('user_id', '=', \Auth::user()->id)->first();

            foreach ( $allowances as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);

                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $commissions as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $loans as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $saturationdeductions as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $otherpayments as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            return view('setsalary.employee_salary', compact('employee', 'payslip_type', 'allowance_options', 'commissions', 'loan_options', 'overtimes', 'otherpayments', 'saturationdeductions', 'loans', 'deduction_options', 'allowances'));


        }
        else
        {
            $allowances           = Allowance::where('employee_id', $id)->get();
            $commissions          = Commission::where('employee_id', $id)->get();
            $loans                = Loan::where('employee_id', $id)->get();
            $saturationdeductions = SaturationDeduction::where('employee_id', $id)->get();
            $otherpayments        = OtherPayment::where('employee_id', $id)->get();
            $overtimes            = Overtime::where('employee_id', $id)->get();
            $employee             = Employee::find($id);

            foreach ( $allowances as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $commissions as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $loans as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $saturationdeductions as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            foreach ( $otherpayments as  $value) {
                if(  $value->type == 'percentage' )
                {
                    $employee          = Employee::find($value->employee_id);
                    $empsal  = $value->amount * $employee->salary / 100;
                    $value->tota_allow = $empsal;
                }
            }

            return view('setsalary.employee_salary', compact('employee', 'payslip_type', 'allowance_options', 'commissions', 'loan_options', 'overtimes', 'otherpayments', 'saturationdeductions', 'loans', 'deduction_options', 'allowances'));
        }

    }


    public function employeeUpdateSalary(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'salary_type' => 'required',
                               'salary' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }
        $employee = Employee::findOrFail($id);
        $employee->salary_type = $request->salary_type;
        $employee->salary = $request->salary;
        $employee->save();
        return redirect()->back()->with('success', 'Employee Salary Updated.');
    }

    public function employeeSalary()
    {
        if(\Auth::user()->type == "employee")
        {
            $employees = Employee::where('user_id', \Auth::user()->id)->get();
            return view('setsalary.index', compact('employees'));
        }
    }

    public function employeeBasicSalary($id)
    {

        $payslip_type = PayslipType::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $employee     = Employee::find($id);

        return view('setsalary.basic_salary', compact('employee', 'payslip_type'));
    }


}
