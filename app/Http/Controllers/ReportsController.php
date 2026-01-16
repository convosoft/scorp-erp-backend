<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Stage;
use App\Models\Utility;
use App\Models\Pipeline;
use App\Models\ClientDeal;
use App\Models\University;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\ApplicationNote;
use App\Models\ApplicationStage;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\Deal;
use App\Models\SavedFilter;
use App\Models\StageHistory;
use App\Models\ApplicationReport;
use Illuminate\Http\Request;
use App\Models\DealApplication;
use App\Models\DealTask;
use App\Models\LeadTag;
use Illuminate\Support\Facades\Validator;
use Session;

class ReportsController extends Controller
{
    /**
     * Visa Analysis Report
     */

    public function visaAnalysis(Request $request)
    {
        DB::enableQueryLog(); // ✅ Enable query logging

        $brandIds          = $request->input('brand_ids', []);
        $regionIds         = $request->input('region_ids', []);
        $branchIds         = $request->input('branch_ids', []);
        $instituteIds      = $request->input('institute_ids', []);
        $intake            = $request->input('intake');
        $intakeYear        = $request->input('intakeYear');

        $visaStages        = $request->input('visa_stage_ids');
        $depositVisaStages = $request->input('deposit_visa_stage_ids');

        $startDate         = $request->input('start_date');
        $endDate           = $request->input('end_date');
        $depositStartDate  = $request->input('deposit_start_date');
        $depositEndDate    = $request->input('deposit_end_date');

        // -------------------------------
        // Base query with all filters
        // -------------------------------
        $query = ApplicationReport::query();

        if (!empty($brandIds)) {
            $query->whereIn('brand_id', $brandIds);
        }
        if (!empty($regionIds)) {
            $query->whereIn('region_id', $regionIds);
        }
        if (!empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (!empty($instituteIds)) {
            $query->whereIn('university_id', $instituteIds);
        } else {
            $query->whereNotNull('university_id');
        }

        if (!empty($intakeYear)) {
            $query->where('intakeYear', $intakeYear);
        } else {
            $query->whereNotNull('intakeYear');
        }

        if (!empty($intake)) {
            $query->where('intake', $intake);
        } else {
            $query->whereNotNull('intake');
        }

        if (!empty($depositVisaStages)) {
            $query->whereIn('deposit_stage_id', $depositVisaStages);
        }


        $query->whereNotNull('university_name');

        
        

        if (!empty($visaStages)) {
            $query->whereIn('stage_id', $visaStages);
        }
        

        if ($startDate && $endDate && $depositStartDate && $depositEndDate) {
            $query->where(function ($q) use ($startDate, $endDate, $depositStartDate, $depositEndDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                ->orWhereBetween('deposit_created_at', [$depositStartDate, $depositEndDate]);
            });
        } elseif ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($depositStartDate && $depositEndDate) {
            $query->whereBetween('deposit_created_at', [$depositStartDate, $depositEndDate]);
        }

        // -------------------------------
        // Queries
        // -------------------------------
        $totalBrands      = (clone $query)->distinct('brand_id')->count('brand_id');
        $totalVisas       = (clone $query)->distinct('id')->count('id');
        $brandVisaCounts  = (clone $query)
            ->select('brand_name', DB::raw('COUNT(DISTINCT id) as visa_count'))
            ->groupBy('brand_id', 'brand_name')
            ->get();

        $intakeVisaCounts = (clone $query)
            ->select('intake', 'intakeYear', DB::raw('COUNT(DISTINCT id) as visa_count'))
            ->groupBy('intake', 'intakeYear')
            ->get();

        $instituteCounts  = (clone $query)
            ->select('university_name', DB::raw('COUNT(DISTINCT id) as application_count'))
            ->groupBy('university_name')
            ->having('application_count', '>', 0)
            ->get();

        $monthWiseVisas   = (clone $query)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(DISTINCT id) as visa_count'))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->get();

        // -------------------------------
        // Print All Queries
        // -------------------------------
        $queries = DB::getQueryLog(); // ✅ get all executed queries
        // You can dd() to see them or include them in response
        // dd($queries);

        return response()->json([
            'queries'          => $queries,   // 👈 include executed SQL queries
            'total_brands'     => $totalBrands,
            'total_visas'      => $totalVisas,
            'brand_visa_counts'=> $brandVisaCounts,
            'institutes'       => $instituteCounts,
            'intakeVisaCounts' => $intakeVisaCounts,
            'month_wise_visas' => $monthWiseVisas,
        ]);
    }




    /**
     * Deposit Analysis Report
     */
    public function depositAnalysis(Request $request)
    {
        $brandIds  = $request->input('brand_ids', []);
        $regionIds = $request->input('region_ids', []);
        $branchIds = $request->input('branch_ids', []);
        $startDate = $request->input('start_date', '2025-01-01');
        $endDate   = $request->input('end_date', now()->toDateString());

        // Deposit stage IDs
        $depositStages = [5, 6, 7, 8, 9, 10, 11];

        // Base query
        $query = ApplicationReport::query();

        if (!empty($brandIds)) {
            $query->whereIn('brand_id', $brandIds);
        }
        if (!empty($regionIds)) {
            $query->whereIn('region_id', $regionIds);
        }
        if (!empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        // Get unique application IDs that moved to deposit stages
        $depositStageApplications = StageHistory::select('type_id')
            ->where('type', 'application')
            ->whereIn('stage_id', $depositStages)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->pluck('type_id')
            ->unique()
            ->toArray();

        $query->whereIn('id', $depositStageApplications);

        // Total deposits
        $totalDeposits = $query->distinct('id')->count('id');

        // Brand-wise deposits
        $brandDepositCounts = (clone $query)
            ->select('brand_id', DB::raw('count(distinct id) as deposit_count'))
            ->groupBy('brand_id')
            ->get();

        return response()->json([
            'total_deposits'       => $totalDeposits,
            'brand_deposit_counts' => $brandDepositCounts,
            'filters' => [
                'brands'    => $brandIds,
                'regions'   => $regionIds,
                'branches'  => $branchIds,
                'start_date'=> $startDate,
                'end_date'  => $endDate,
            ]
        ]);
    }
}
