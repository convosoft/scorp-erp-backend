<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Course;
use App\Models\Deal;
use App\Models\DealApplication;
use App\Models\InstituteCategory;
use App\Models\Stage;
use App\Models\ToolkitInstallmentPayOut;
use App\Models\ToolkitLevel;
use App\Models\ToolkitTeam;
use App\Models\ToolkitPaymentType;
use App\Models\University;
use App\Models\Homeuniversity;
use App\Models\User;
use App\Models\Utility;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UniversityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
      public function getUniversities(Request $request)
    {
        // Check permission
        if (! Auth::user()->type == 'super admin' && ! Gate::check('show university') && ! Gate::check('manage university')) {
            return response()->json([
                'status' => false,
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // European countries for filtering
           $europe_country = Utility::getValByName('europe_country');
        $europeEMEA = array_filter(
            array_map('trim', explode(',', $europe_country))
        );
        $europeanCountries = $europeEMEA;

        // Pagination control
        $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
        $page = $request->input('page', 1);

        $caseCountry = "
                COALESCE(
                    CASE
                        WHEN universities.country REGEXP '^[0-9]+$'
                            THEN c_id.name
                        ELSE c_name.name
                    END,
                    universities.country
                )
            ";

        // Build query
        $query = University::query()
            ->leftJoin('countries as c_id', 'universities.country', '=', 'c_id.id')
            ->leftJoin('countries as c_name', 'universities.country', '=', 'c_name.name')
            ->select('universities.*')
            ->selectRaw("$caseCountry AS resolved_country_name");

        // Filters
        if ($request->filled('name')) {
            $query->where('universities.name', 'like', '%'.$request->name.'%');
        }
        if ($request->filled('is_active')) {
            if($request->is_active === '1' || $request->is_active === '0'){
               $query->where('universities.uni_status', $request->is_active);
            }
        }

        if ($request->filled('team_id')) {
            $query->where('universities.team_id', $request->team_id);
        }


        if ($request->filled('channel_id')) {
            $query->where('universities.channel_id', $request->channel_id);
        }

        // if ($request->filled('is_refund')) {
        //     $query->where('universities.is_refund', $request->is_refund);
        // }

        if ($request->filled('city')) {
            $query->where('campuses', 'like', '%'.$request->city.'%');
        }

        if ($request->filled('country')) {

            if ($request->country === 'Europe') {
                $placeholders = implode(',', array_fill(0, count($europeanCountries), '?'));
                $query->whereRaw("$caseCountry IN ($placeholders)", $europeanCountries);
            }else  if ($request->country === 'UK Home') {
                $country = Country::find(216);

                if (! empty($country)) {
                    $query->whereRaw("$caseCountry LIKE ?", ['%'.$country->name.'%']);
                } else {
                    $query->whereRaw("$caseCountry LIKE ?", ['%United Kingdom%']);
                }

                $query->where('home_status', '=', 1);
            } else {
                $country = Country::find($request->country);

                if (! empty($country)) {
                    $query->whereRaw("$caseCountry LIKE ?", ['%'.$country->name.'%']);
                } else {
                    $query->whereRaw("$caseCountry LIKE ?", ['%'.$request->country.'%']);
                }
            }
        }

        if ($request->filled('rank_id')) {
            $query->where('rank_id', 'like', '%'.$request->rank_id.'%');
        }

        if ($request->filled('intake_months')) {
            $query->where(function ($subQuery) use ($request) {
                foreach ($request->intake_months as $month) {
                    $subQuery->orWhereRaw('FIND_IN_SET(?, intake_months)', [trim($month)]);
                }
            });
        }
        if ($request->filled('territory_id')) {
            $ids = $request->territory_id;
            $ids[] = 253;
            $territoryNames = \DB::table('countries')
                ->whereIn('id', $ids)
                ->pluck('name', 'id')
                ->toArray();
            $query->where(function ($sub) use ($ids, $territoryNames) {
                foreach ($ids as $id) {
                    $name = $territoryNames[$id] ?? null;
                    $sub->orWhere(function ($q) use ($id, $name) {
                        $q->whereRaw("FIND_IN_SET(?, territory)", [$id]);
                        if ($name) {
                            $q->orWhere('territory', 'LIKE', '%' . $name . '%');
                        }
                    });

                }
            });
        }

        // Retrieve paginated data

        $universities = $query->orderBy('name', 'ASC')
            ->paginate($perPage, ['*'], 'page', $page);

        // University statistics grouped by country
        $universityStatsByCountries = University::selectRaw('count(id) as total_universities, country')->where('uni_status', '0')
            ->groupBy('country')
            ->get();

        $statuses = [];
        foreach ($universityStatsByCountries as $u) {
            $statuses[$u->country] = [
                'country_code' => $u->country_code,
                'count' => $u->total_universities,
            ];
        }

        $customOrder = [
            'United States', 'Canada', 'United Kingdom', 'Australia',
            'United Arab Emirates', 'Hungary', 'Ireland', 'Malta',
            'Poland', 'Germany', 'Holand', 'China', 'Malaysia',
            'Turkey', 'Samoa,Djibouti',
        ];

        // Reorder statuses
        $sortedStatuses = [];
        foreach ($customOrder as $country) {
            if (isset($statuses[$country])) {
                $sortedStatuses[$country] = $statuses[$country];
            }
        }

        // Final response
        return response()->json([
            'status' => 'success',
            'message' => 'University list retrieved successfully.',
            'data' => [
                'number_of_tiles' => 15,
                'statuses' => $sortedStatuses,
                'universities' => $universities->items(),
                'current_page' => $universities->currentPage(),
                'last_page' => $universities->lastPage(),
                'total_records' => $universities->total(),
                'per_page' => $universities->perPage(),
            ],
        ]);
    }

    public function getPublicUniversities(Request $request)
    {
        // Fetch universities with country_name accessor
        $universities = University::select([
            'id',
            'name',
            'country',
            'city',
            'intake_months',
            'campuses',
            'website_link',
            'territory',
            'commission',
            'pak_commission',
            'notes',
            'created_by',
            'rank_id',
            'level_id',
            'payment_type_id',
            'global_payment_type_id',
            'pay_out_id',
            'team_id',
        ])->where('uni_status', '0')
        ->where('international_status', '1')
            ->with([
                'createdBy:id,name',
                'rank:id,name',
                'ToolkitLevel:id,name',
                'PaymentType:id,name',
                'GlobalPaymentType:id,name',
                'InstallmentPayOut:id,name',
                'ToolkitTeam:id,name',
            ])
            ->get()
            ->append('country_name');

        // University statistics grouped by country (use actual column 'country')
        $universityStatsByCountries = University::query()->where('uni_status', '0')->where('international_status', '1')
            ->selectRaw("
                        COUNT(universities.id) AS total_universities,

                        COALESCE(
                            CASE
                                WHEN universities.country REGEXP '^[0-9]+$'
                                    THEN c_id.name
                                ELSE c_name.name
                            END,
                            universities.country
                        ) AS resolved_country_name,

                        COALESCE(
                            CASE
                                WHEN universities.country REGEXP '^[0-9]+$'
                                    THEN c_id.country_code
                                ELSE c_name.country_code
                            END,
                            NULL
                        ) AS resolved_country_code
                    ")
            ->leftJoin('countries AS c_id', function ($join) {
                $join->on('c_id.id', '=', DB::raw('CAST(universities.country AS UNSIGNED)'));
            })
            ->leftJoin('countries AS c_name', function ($join) {
                $join->on(DB::raw('LOWER(c_name.name)'), '=', DB::raw('LOWER(universities.country)'));
            })
            ->groupBy('resolved_country_name', 'resolved_country_code')
            ->get();

        // Build statuses array keyed by country name
        $statuses = [];
        foreach ($universityStatsByCountries as $u) {

            // dd($u,$u->resolved_country_name,$u->total_universities);

            $countryName = $u->resolved_country_name;  // accessor returns full country name
            $statuses[$countryName] = [
                'country_code' => $u->resolved_country_code, // estore original cod test
                'count' => $u->total_universities,
            ];

        }

        $general_country = Utility::getValByName('general_country');

        // Custom display order
        $customOrder = array_filter(
            array_map('trim', explode(',', $general_country))
        );

        // dd($customOrder);

        // -------------------------------
        // EMEA REGION LISTS FOR COUNTING
        // -------------------------------

        $europe_country = Utility::getValByName('europe_country');
        $europeEMEA = array_filter(
            array_map('trim', explode(',', $europe_country))
        );

        $middle_east_country = Utility::getValByName('middle_east_country');

        $middleEastEMEA = array_filter(
            array_map('trim', explode(',', $middle_east_country))
        );

        // Fast lookup arrays for counting
        $europeMap = array_flip($europeEMEA);
        $middleEastMap = array_flip($middleEastEMEA);

        $europeCount = 0;
        $middleEastCount = 0;

        // Single loop to compute totals for Europe & Middle East
        foreach ($statuses as $countryName => $data) {
            $count = $data['count'];

            if (isset($europeMap[$countryName])) {
                $europeCount += $count;
            }

            if (isset($middleEastMap[$countryName])) {
                $middleEastCount += $count;
            }
        }

        $statuses['Europe'] = [
            'country_code' => 'eu', // estore original cod test
            'count' => $europeCount,
        ];
        $statuses['Middle East'] = [
            'country_code' => 'me', // estore original cod test
            'count' => $middleEastCount,
        ];

        // Reorder statuses
        $sortedStatuses = [];
        foreach ($customOrder as $country) {
            if (isset($statuses[$country])) {
                $sortedStatuses[$country] = $statuses[$country];
            }
        }

        $sortedStatuses['Middle East'] = $statuses['Middle East'];
        $sortedStatuses['Europe'] = $statuses['Europe'];

        // Supporting dropdowns
        $payOuts = ToolkitInstallmentPayOut::pluck('name', 'id')->toArray();
        $ToolkitPaymentTypes = ToolkitPaymentType::pluck('name', 'id')->toArray();
        $toolkitLevels = ToolkitLevel::pluck('name', 'id')->toArray();
        $ToolkitTeam = ToolkitTeam::pluck('name', 'id')->toArray();
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        // Final Response
        return response()->json([
            'status' => 'success',
            'message' => 'University list retrieved successfully.',
            'data' => [
                'number_of_tiles' => 15,
                'statuses' => $sortedStatuses,
                'universities' => $universities,
                'europe_total' => $europeCount,
                'middle_east_total' => $middleEastCount,
                'payOuts' => $payOuts,
                'ToolkitPaymentTypes' => $ToolkitPaymentTypes,
                'toolkitLevels' => $toolkitLevels,
                'ToolkitTeam' => $ToolkitTeam,
                'general_country' => $general_country.',Europe',
                'middle_east_country' => $middle_east_country,
                'europe_country' => $europe_country,
                'Country' => $Country,
            ],
        ]);
    }

    public function getPublicUniversitiesTiles(Request $request)
    {

        // University statistics grouped by country (use actual column 'country')
        $universityStatsByCountries = University::query()
            ->selectRaw("
                        COUNT(universities.id) AS total_universities,

                        COALESCE(
                            CASE
                                WHEN universities.country REGEXP '^[0-9]+$'
                                    THEN c_id.name
                                ELSE c_name.name
                            END,
                            universities.country
                        ) AS resolved_country_name,

                        COALESCE(
                            CASE
                                WHEN universities.country REGEXP '^[0-9]+$'
                                    THEN c_id.country_code
                                ELSE c_name.country_code
                            END,
                            NULL
                        ) AS resolved_country_code
                    ")
            ->leftJoin('countries AS c_id', function ($join) {
                $join->on('c_id.id', '=', DB::raw('CAST(universities.country AS UNSIGNED)'));
            })
            ->leftJoin('countries AS c_name', function ($join) {
                $join->on(DB::raw('LOWER(c_name.name)'), '=', DB::raw('LOWER(universities.country)'));
            })
            ->where('uni_status', '0')
            ->groupBy('resolved_country_name', 'resolved_country_code')
            ->get();

            // University statistics grouped by country (use actual column 'country')
        $homeuniversityStatsByCountries = University::query()
            ->selectRaw("
                        COUNT(universities.id) AS total_universities,

                        COALESCE(
                            CASE
                                WHEN universities.country REGEXP '^[0-9]+$'
                                    THEN c_id.name
                                ELSE c_name.name
                            END,
                            universities.country
                        ) AS resolved_country_name,

                        COALESCE(
                            CASE
                                WHEN universities.country REGEXP '^[0-9]+$'
                                    THEN c_id.country_code
                                ELSE c_name.country_code
                            END,
                            NULL
                        ) AS resolved_country_code
                    ")
            ->leftJoin('countries AS c_id', function ($join) {
                $join->on('c_id.id', '=', DB::raw('CAST(universities.country AS UNSIGNED)'));
            })
            ->leftJoin('countries AS c_name', function ($join) {
                $join->on(DB::raw('LOWER(c_name.name)'), '=', DB::raw('LOWER(universities.country)'));
            })
            ->where('home_status', 1)
            ->where('uni_status', '0')
            ->groupBy('resolved_country_name', 'resolved_country_code')
            ->get();

        // Build statuses array keyed by country name
        $statuses = [];
        foreach ($universityStatsByCountries as $u) {

            // dd($u,$u->resolved_country_name,$u->total_universities);

            $countryName = $u->resolved_country_name;  // accessor returns full country name
            $statuses[$countryName] = [
                'country_code' => $u->resolved_country_code, // estore original cod test
                'count' => $u->total_universities,
            ];

        }
        // Build statuses array keyed by country name
        $homestatuses = [];
        foreach ($homeuniversityStatsByCountries as $u) {

            // dd($u,$u->resolved_country_name,$u->total_universities);

            $countryName = $u->resolved_country_name;  // accessor returns full country name
            $homestatuses[$countryName] = [
                'country_code' => $u->resolved_country_code, // estore original cod test
                'count' => $u->total_universities,
            ];

        }

        $general_country = Utility::getValByName('general_country');

        // Custom display order
        $customOrder = array_filter(
            array_map('trim', explode(',', $general_country))
        );

        // dd($customOrder);

        // -------------------------------
        // EMEA REGION LISTS FOR COUNTING
        // -------------------------------

        $europe_country = Utility::getValByName('europe_country');
        $europeEMEA = array_filter(
            array_map('trim', explode(',', $europe_country))
        );

        $middle_east_country = Utility::getValByName('middle_east_country');

        $middleEastEMEA = array_filter(
            array_map('trim', explode(',', $middle_east_country))
        );

        // Fast lookup arrays for counting
        $europeMap = array_flip($europeEMEA);
        $middleEastMap = array_flip($middleEastEMEA);

        $europeCount = 0;
        $middleEastCount = 0;

        // Single loop to compute totals for Europe & Middle East
        foreach ($statuses as $countryName => $data) {
            $count = $data['count'];

            if (isset($europeMap[$countryName])) {
                $europeCount += $count;
            }

        }
        // Single loop to compute totals for Europe & Middle East
        foreach ($homestatuses as $countryName => $data) {
            $count = $data['count'];



            if (isset($middleEastMap[$countryName])) {
                $middleEastCount += $count;
            }
        }

        $statuses['Europe'] = [
            'country_code' => 'eu', // estore original cod test
            'count' => $europeCount,
        ];
        $statuses['UK Home'] = [
            'country_code' => 'GB', // estore original cod test
            'count' => $middleEastCount,
        ];

        // Reorder statuses
        $sortedStatuses = [];
        foreach ($customOrder as $country) {
            if (isset($statuses[$country])) {
                $sortedStatuses[$country] = $statuses[$country];
            }
        }

        $sortedStatuses['UK Home'] = $statuses['UK Home'];
        $sortedStatuses['Europe'] = $statuses['Europe'];

        // Supporting dropdowns
        $payOuts = ToolkitInstallmentPayOut::pluck('name', 'id')->toArray();
        $ToolkitPaymentTypes = ToolkitPaymentType::pluck('name', 'id')->toArray();
        $toolkitLevels = ToolkitLevel::pluck('name', 'id')->toArray();
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        // Final Response
        return response()->json([
            'status' => 'success',
            'message' => 'University list retrieved successfully.',
            'data' => [
                'number_of_tiles' => 15,
                'statuses' => $sortedStatuses,
                'europe_total' => $europeCount,
                'middle_east_total' => $middleEastCount,
                'payOuts' => $payOuts,
                'ToolkitPaymentTypes' => $ToolkitPaymentTypes,
                'toolkitLevels' => $toolkitLevels,
                'general_country' => $general_country.',Europe,UK Home',
                'middle_east_country' => $middle_east_country,
                'europe_country' => $europe_country,
                'Country' => $Country,
            ],
        ]);
    }

    public function download()
    {

        $universities = University::when(! empty($_GET['name']), function ($query) {
            return $query->where('name', 'like', '%'.$_GET['name'].'%');
        })
            ->when(! empty($_GET['country']), function ($query) {
                return $query->where('country', 'like', '%'.$_GET['country'].'%');
            })
            ->when(! empty($_GET['city']), function ($query) {
                return $query->where('city', 'like', '%'.$_GET['city'].'%');
            })
            ->when(! empty($_GET['note']), function ($query) {
                return $query->where('note', 'like', '%'.$_GET['note'].'%');
            })
            ->when(! empty($_GET['created_by']), function ($query) {
                return $query->where('created_by', 'like', '%'.$_GET['created_by'].'%');
            })
            ->get();

        $users = User::get()->pluck('name', 'id');
        $universityStatsByCountries = University::selectRaw('count(id) as total_universities, country')
            ->groupBy('country')
            ->get();

        // dd($universities);

        $header = [
            'Sr.No.',
            'Institutes',
            'Campuse',
            'Intake Months',
            'Territory',
            'Band',
            'Resource',
            'Application Method',
        ];

        $data = [];
        foreach ($universities as $key => $university) {
            $data[] = [
                $key + 1,
                $university->name,
                $university->campuses,
                $university->intake_months,
                $university->territory,
                $users[$university->company_id] ?? '',
                $university->resource_drive_link,
                $university->application_method_drive_link,
            ];
        }
        downloadCSV($header, $data, 'toolkit.csv');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        if (\Auth::user()->can('create university')) {

            // getting countries
            $countries = countries();

            // months
            $months = months();

            // getting companies
            $companies = FiltersBrands();

            $categories = InstituteCategory::pluck('name', 'id')->prepend('Select Category', 0);

            $data = [
                'countries' => $countries,
                'companies' => $companies,
                'months' => $months,
                'categories' => $categories,
            ];

            return view('university.create', $data);
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function addUniversities(Request $request)
    {
        if (! Auth::user()->can('create university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:200',
            'country' => 'required|array|min:1',
            'country.*' => 'required|max:200',
            'city' => 'nullable|max:200',
            'months' => 'required|array|min:1',
            'months.*' => 'required|string',
            'territory' => 'required|array|min:1',
            'territory.*' => 'required|string',
            'company_id' => 'nullable|exists:users,id',
            // 'rank_id' => 'required|exists:university_ranks,id',
            'phone' => 'nullable|max:20',
            'note' => 'nullable|string',
            'institution_link' => 'nullable|string',
            'resource_drive_link' => 'nullable|string',
            'application_method_drive_link' => 'nullable|string',
            'category_id' => 'nullable|exists:institute_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $university = new University;
        $university->status = '1';
        $university->name = $request->name;
        $university->company_id = 1;
        $university->country = implode(',', $request->country);
        $university->city = $request->city;
        $university->campuses = $request->campuses;
        //  $university->rank_id = $request->rank_id;
        $university->phone = $request->phone;
        $university->institution_link = $request->institution_link;
        $university->note = $request->note;
        $university->intake_months = implode(',', $request->months);
        if ($request->territory != '') {
            $university->territory = implode(',', $request->territory);
        }

        $university->company_id = $request->company_id;
        $university->resource_drive_link = $request->resource_drive_link;
        $university->application_method_drive_link = $request->application_method_drive_link;
        $university->institute_category_id = $request->category_id;
        $university->created_by = Auth::user()->id;
        $university->save();

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $university->name.' international  created',
                'message' => $university->name.' international  created',
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Created',
        ]);

        $homeuniversity = new Homeuniversity;
        $homeuniversity->main_uni_id = $university->id;
        $homeuniversity->status = '1';
        $homeuniversity->name = $request->name;
        $homeuniversity->company_id = 1;
        $homeuniversity->country = implode(',', $request->country);
        $homeuniversity->city = $request->city;
        $homeuniversity->campuses = $request->campuses;
        //  $homeuniversity->rank_id = $request->rank_id;
        $homeuniversity->phone = $request->phone;
        $homeuniversity->institution_link = $request->institution_link;
        $homeuniversity->note = $request->note;
        $homeuniversity->intake_months = implode(',', $request->months);
        if ($request->territory != '') {
            $homeuniversity->territory = implode(',', $request->territory);
        }

        $homeuniversity->company_id = $request->company_id;
        $homeuniversity->resource_drive_link = $request->resource_drive_link;
        $homeuniversity->application_method_drive_link = $request->application_method_drive_link;
        $homeuniversity->institute_category_id = $request->category_id;
        $homeuniversity->created_by = Auth::user()->id;
        $homeuniversity->save();

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $homeuniversity->name.' home  created',
                'message' => $homeuniversity->name.' home  created',
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Created',
        ]);



        return response()->json([
            'status' => 'success',
            'message' => 'University created successfully.',
            'data' =>  $university->fresh(),
        ]);
    }

    public function updateUniversities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            // 'rank_id' => 'required|exists:university_ranks,id', //
            'name' => 'required|max:200',
            'country' => 'required|array|min:1',
            'country.*' => 'required|string|max:200',
            'city' => 'nullable|max:200',
            'months' => 'required|array|min:1',
            'months.*' => 'required|string',
            'territory' => 'required|array|min:1',
            'territory.*' => 'required|string',
            'company_id' => 'nullable|exists:users,id',
            'phone' => 'nullable|max:20',
            'note' => 'nullable|string',
            'institution_link' => 'nullable|string',
            'resource_drive_link' => 'nullable|string',
            'application_method_drive_link' => 'nullable|string',
            'category_id' => 'nullable|exists:institute_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

        $university = University::where('id', $request->id)->first();
        $originalData = $university->toArray();

        // Update fields
        $university->name = $request->name;
        $university->country = implode(',', $request->country);
        $university->city = $request->city;
        // $university->campuses = $request->city;
        // $university->rank_id = $request->rank_id;
        $university->phone = $request->phone;
        $university->institution_link = $request->institution_link;
        $university->note = $request->note;
        $university->intake_months = implode(',', $request->months);
        $university->territory = implode(',', $request->territory);
        $university->company_id = $request->company_id;
        $university->resource_drive_link = $request->resource_drive_link;
        $university->application_method_drive_link = $request->application_method_drive_link;
        $university->institute_category_id = $request->category_id;
        $university->destination_id = $request->destination_id;
        $university->latitude = $request->latitude;
        $university->longitude = $request->longitude;
        $university->save();

        // Log changed fields only
        $changes = [];
        $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($oldValue != $university->$field) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field,
                ];
                $updatedFields[] = $field;
            }
        }

        if (! empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $university->name.' updated ',
                    'message' => 'Fields updated: '.implode(', ', $updatedFields),
                    'changes' => $changes,
                ]),
                'module_id' => $university->id,
                'module_type' => 'university',
                'notification_type' => 'University Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'University updated successfully.',
            'data' => $university
        ]);
    }

    public function updateUniversitiesByKey(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Permission check
        if (! Auth::user()->can('edit scorp commission')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

        // Get university

         // Find university
        if ($request->type == 1) {
            $university = University::find($request->id);
        } else {
            $university = Homeuniversity::where('main_uni_id', $request->id)->first();;
        }

         $typetext = $request->type == 1 ? 'international' : 'home';

        $originalData = $university->toArray();

        // Exclude ID from update
        $metaData = $request->except('id');

        // Map frontend keys to DB columns (snake_case)
        $mapping = [
            'contract_start' => 'contract_start',
            'contract_end'   => 'contract_end',
            'contract_file'  => 'contract_file', // DB column
            'months'         => 'intake_months',
        ];

        // Handle Contract_file separately
        if ($request->hasFile('contract_file')) {
            $image = $request->file('contract_file');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            // Move uploaded file to public/EmployeeDocument
            $image->move(public_path('EmployeeDocument'), $imageName);

            // Save relative path to database
            $university->contract_file = 'EmployeeDocument/' . $imageName;
        }

        // Update other fields dynamically
        foreach ($metaData as $key => $newValue) {

            // Skip Contract_file as we already handled
            if ($key === 'contract_file') continue;

            // Map frontend key to DB column
            if (isset($mapping[$key])) {
                $key = $mapping[$key];
            }

            // Handle array fields
            if (in_array($key, ['country', 'territory', 'intake_months'])) {
                $newValue = implode(',', (array) $newValue);
            }

            // Assign only if column exists in table
            if (\Schema::hasColumn('universities', $key)) {
                $university->$key = $newValue;
            }
        }

        // Save updated university
        $university->save();

        // Detect changes
        $changes = [];
        $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) continue;

            if ($oldValue != $university->$field) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field,
                ];
                $updatedFields[] = $field;
            }
        }

        // Log changes if any
        if (! empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' =>  $typetext . ' ' . $university->name.' updated',
                    'message' => 'Fields updated: '.implode(', ', $updatedFields),
                    'changes' => $changes,
                ]),
                'module_id' => $university->id,
                'module_type' => 'university',
                'notification_type' => 'University Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'University updated successfully.',
            'data' => $university,
        ]);
    }

    public function updateAboutUniversity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'territory' => 'required|array|min:1',
            'agency' => 'required|string',
            'website_link' => 'string',
            // 'product_coordinator_id' => 'nullable|exists:users,id',
            'territory.*' => 'required|string',
            'campuses' => 'required|array|min:1',
            'campuses.*' => 'required|string',
             'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

         // Find university
        if ($request->type == 1) {
            $university = University::find($request->id);
        } else {
            $university = Homeuniversity::where('main_uni_id', $request->id)->first();;
        }

         $typetext = $request->type == 1 ? 'international' : 'home';

        $originalData = $university->toArray();

        // Update fields
        $university->territory = implode(',', $request->territory);
        $university->campuses = implode(',', $request->campuses);
        $university->agency = $request->agency;
        $university->website_link = $request->website_link;
        // $university->is_refund = $request->is_refund;
        // $university->refund_detail = $request->refund_detail;
        if ($request->product_coordinator_id != 'N/A') {
            $university->product_coordinator_id = $request->product_coordinator_id;
        }

        $university->save();

        // Log changed fields only
        $changes = [];
        $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at', 'rank', 'created_by'])) {
                continue;
            }
            if ($oldValue != $university->$field) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field,
                ];

                $updatedFields[] = $field;
            }
        }

        if (! empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' =>  $typetext . ' ' . $university->name.' updated ',
                    'message' => 'Fields updated: '.implode(', ', $updatedFields),
                    'changes' => $changes,
                ]),
                'module_id' => $university->id,
                'module_type' => 'university',
                'notification_type' => 'University Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'University about updated successfully.',
            'data' => $university,
        ]);
    }

    public function deleteUniversities(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (! \Auth::user()->can('delete university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find the university
        $university = University::find($request->id);

        // // Log the deletion
        // $logData = [
        //     'type' => 'info',
        //     'note' => json_encode([
        //         'title' => 'University Deleted',
        //         'message' => 'University deleted successfully.',
        //         'changes' => $university
        //     ]),
        //     'module_id' => $university->id,
        //     'module_type' => 'university',
        //     'notification_type' => 'University Deleted'
        // ];
        // addLogActivity($logData);

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $university->name.'  deleted',
                'message' => $university->name.'  deleted',
                'changes' => $university,
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University deleted',
        ]);

        $homeuniversity = Homeuniversity::where('main_uni_id', $university->id)->first();

        if ($homeuniversity) {
            $homeuniversity->delete();
        }
        // Delete the record
        $university->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully deleted!'),
        ], 200);
    }

    public function universityDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
        }

        $id = $request->id;

        if($request->type == 1){
            $university = University::findOrFail($id);
        }else{
             $university2 = Homeuniversity::where('main_uni_id', $id)->firstOrFail();

              $university = Homeuniversity::findOrFail($university2->id);
        }


        // related applications
        $applications = []; // DealApplication::where('university_id', $id)->get();

        // related admissions
        $deals = []; // Deal::where('university_id', $id)->get();

        $stages = Stage::pluck('name', 'id')->toArray();
        $organizations = User::where('type', 'organization')->pluck('name', 'id')->toArray();

        $course = Course::where('university_id', $id)->where('type', $request->type);

        if (\Auth::user()->type == 'super admin') {
            $courses = $course->get();
        } else {
            $courses = $course->where('created_by', \Auth::user()->id)->get();
        }

        return response()->json([
            'status' => 'success',
            'university' => $university,
            'baseurl' =>  asset('/'),
            'applications' => $applications,
            'deals' => $deals,
            'stages' => $stages,
            'organizations' => $organizations,
            'courses' => $courses,
        ]);
    }


    public function getUniversityCourseStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
        }

        $id = $request->id;

        if ($request->type == 1) {
                $university = University::select('status')->findOrFail($id);
            } else {
                $university = Homeuniversity::select('status')
                    ->where('main_uni_id', $id)
                    ->firstOrFail();
            }


        // related applications
        $applications = []; // DealApplication::where('university_id', $id)->get();

        // related admissions
        $deals = []; // Deal::where('university_id', $id)->get();

        $stages = Stage::pluck('name', 'id')->toArray();
        $organizations = User::where('type', 'organization')->pluck('name', 'id')->toArray();

        $course = Course::where('university_id', $id)->where('type', $request->type);

        if (\Auth::user()->type == 'super admin') {
            $courses = $course->get();
        } else {
            $courses = $course->where('created_by', \Auth::user()->id)->get();
        }

        return response()->json([
            'status' => 'success',
            'university' => $university,
            'baseurl' =>  asset('/'),
            'applications' => $applications,
            'deals' => $deals,
            'stages' => $stages,
            'organizations' => $organizations,
            'courses' => $courses,
        ]);
    }

    // public function getIntakeMonthByUniversity()
    // {
    //     $id = $_POST['id'];
    //     $university = University::where('id', $id)->first();
    //     $courses = Course::where('university_id', $id)->get();

    //     $monthNames = [
    //         'JAN' => 'JAN',
    //         'FEB' => 'FEB',
    //         'MAR' => 'MAR',
    //         'APR' => 'APR',
    //         'MAY' => 'MAY',
    //         'JUN' => 'JUN',
    //         'JUL' => 'JUL',
    //         'AUG' => 'AUG',
    //         'SEP' => 'SEP',
    //         'OCT' => 'OCT',
    //         'NOV' => 'NOV',
    //         'DEC' => 'DEC'
    //     ];

    //     // Process intake months
    //     $intake_months = [];
    //     if ($university && $university->intake_months) {
    //         $intake_months = collect(explode(',', $university->intake_months))
    //             ->map(function ($monthAbbr) use ($monthNames) {
    //                 $trimmed = trim($monthAbbr);
    //                 return $monthNames[$trimmed] ?? $trimmed;
    //             })
    //             ->filter()
    //             ->unique()
    //             ->values()
    //             ->toArray();
    //     }

    //     // Process courses
    //     $course_options = [];
    //     foreach ($courses as $course) {
    //         $course_options[] = [
    //             $course->id => $course->name . ' - ' . $course->campus . ' - ' . $course->intake_month . ' - ' . $course->intakeYear . ' (' . $course->duration . ')'
    //         ];
    //     }

    //     // Process intake years
    //     $intake_years = [];
    //     $years = intakeYear() ?? [];
    //     foreach ($years as $year) {
    //         $intake_years[] = [
    //             $year => $year
    //         ];
    //     }

    //     return [
    //         'status' => 'success',
    //         'university_status' => $university->status ?? null,
    //         'intake_months' => $intake_months,
    //         'courses' => $course_options,
    //         'intake_years' => $intake_years
    //     ];
    // }
    public function getIntakeMonthByUniversity(Request $request)
    {
       // $id = $_POST['id'];
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
        }

        $id = $request->id;
        $university = University::where('id', $id)->first();
        $courses = Course::where('university_id', $id)->where('type',$request->type)->get();

        $monthNames = [
            'JAN' => 'JAN',
            'FEB' => 'FEB',
            'MAR' => 'MAR',
            'APR' => 'APR',
            'MAY' => 'MAY',
            'JUN' => 'JUN',
            'JUL' => 'JUL',
            'AUG' => 'AUG',
            'SEP' => 'SEP',
            'OCT' => 'OCT',
            'NOV' => 'NOV',
            'DEC' => 'DEC',
        ];

        // Process intake months - already in pluck-like format
        $intake_months = [];
        if ($university && $university->intake_months) {
            $intake_months = collect(explode(',', $university->intake_months))
                ->mapWithKeys(function ($monthAbbr) use ($monthNames) {
                    $trimmed = trim($monthAbbr);
                    $value = $monthNames[$trimmed] ?? $trimmed;

                    return [$value => $value];
                })
                ->filter()
                ->unique()
                ->toArray();
        }

        // Process courses in pluck-like format
        $course_options = $courses->mapWithKeys(function ($course) {
            $label = $course->name.' - '.$course->campus.' - '.$course->intake_month.' - '.$course->intakeYear.' ('.$course->duration.')';

            return [$course->id => $label];
        })->toArray();

        // Process intake years in pluck-like format
        $intake_years = collect(intakeYear() ?? [])->mapWithKeys(function ($year) {
            return [$year => $year];
        })->toArray();

        return [
            'status' => 'success',
            'university_status' => $university->status ?? null,
            'intake_months' => $intake_months,
            'courses' => $course_options,
            'intake_years' => $intake_years,
        ];
    }

    public function SaveToggleCourse(Request $request)
    {

         $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
        }


         // Find university
        if ($request->type == 1) {
            $University = University::find($request->id);
        } else {
            $University = Homeuniversity::where('main_uni_id', $request->id)->first();;
        }

         $typetext = $request->type == 1 ? 'international' : 'home';
        if ($University) {
            $University->status = $request->status;
            $University->save();


             // Log activity
        $statusText = $request->status == 1 ? 'Active' : 'Inactive';

        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' =>  $typetext . ' ' . $University->name.' course status updated to '.$statusText,
                'message' =>  $typetext . ' ' . $University->name.' course status updated to '.$statusText,
            ]),
            'module_id' => $University->id,
            'module_type' => 'university',
            'notification_type' => 'University Updated',
        ];
        addLogActivity($logData);

            return json_encode([
                'status' => 'success',
                'message' => 'Courses successfully updated!',
            ]);
        }
    }

    public function updateUniversityStatus(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'status' => 'required|in:0,1', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (! \Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find university
        $university = University::find($request->university_id);

        if (! $university) {
            return response()->json([
                'status' => 'error',
                'message' => __('University not found.'),
            ], 404);
        }

        // Update status
        $university->uni_status = "$request->status";
        $university->save();

        // Log activity
        $statusText = $request->status == 0 ? 'Active' : 'Inactive';

        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => $university->name.' status updated to '.$statusText,
                'message' => $university->name.' status updated to '.$statusText,
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Updated',
        ];
        addLogActivity($logData);

        // Success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully updated!'),
        ], 200);
    }

    public function updateUniversityCourseStatus(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'status' => 'required|in:0,1', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (! \Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find university
        $university = University::find($request->university_id);

        if (! $university) {
            return response()->json([
                'status' => 'error',
                'message' => __('University not found.'),
            ], 404);
        }

        // Update status
        $university->uni_status = $request->status;
        $university->save();

        // Log activity
        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'University course Status Updated',
                'message' => 'University status changed to '.$request->status,
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Updated',
        ];
        addLogActivity($logData);

        // Success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully updated!'),
        ], 200);
    }

    public function updateUniversityMOIStatus(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'status' => 'required|in:0,1', // Assuming 0 or 1 as status values
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (! \Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find university
        if ($request->type == 1) {
            $university = University::find($request->university_id);
        } else {
            $university = Homeuniversity::find($request->university_id);
        }

         $typetext = $request->type == 1 ? 'international' : 'home';


        if (! $university) {
            return response()->json([
                'status' => 'error',
                'message' => __('University not found.'),
            ], 404);
        }

        // Update status
        $university->moi_status = $request->status;
        $university->save();

        $statusText = $request->status == 1 ? 'active' : 'inactive';


        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => $typetext . ' '.$university->name.' MOI status updated to '.$statusText,
                'message' => $typetext . ' '.$university->name.' MOI status updated to '.$statusText,
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Updated',
        ];
        addLogActivity($logData);

        // Success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully updated!'),
        ], 200);
    }

    public function updateUniversityInternationalStatus(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'status' => 'required|in:0,1', // Assuming 0 or 1 as status values
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (! \Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find university
        if ($request->type == 1) {
            $university = University::find($request->university_id);
        } else {
            $university = Homeuniversity::find($request->university_id);
        }

         $typetext = $request->type == 1 ? 'international' : 'home';


        if (! $university) {
            return response()->json([
                'status' => 'error',
                'message' => __('University not found.'),
            ], 404);
        }

        // Update status
        $university->international_status = $request->status;
        $university->save();

        $statusText = $request->status == 1 ? 'active' : 'inactive';


        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => $typetext . ' '.$university->name.' international status updated to '.$statusText,
                'message' => $typetext . ' '.$university->name.' international status updated to '.$statusText,
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Updated',
        ];
        addLogActivity($logData);

        // Success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully updated!'),
        ], 200);
    }
    public function updateUniversityhomeStatus(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'status' => 'required|in:0,1', // Assuming 0 or 1 as status values
            'type' => 'required|in:1,2', // Assuming 0 or 1 as status values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (! \Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // Find university
        if ($request->type == 1) {
            $university = University::find($request->university_id);
        } else {
            $university = Homeuniversity::find($request->university_id);
        }

         $typetext = $request->type == 1 ? 'international' : 'home';


        if (! $university) {
            return response()->json([
                'status' => 'error',
                'message' => __('University not found.'),
            ], 404);
        }

        // Update status
        $university->home_status = $request->status;
        $university->save();

        $statusText = $request->status == 1 ? 'active' : 'inactive';


        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => $typetext . ' '.$university->name.' home status updated to '.$statusText,
                'message' => $typetext . ' '.$university->name.' home status updated to '.$statusText,
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Updated',
        ];
        addLogActivity($logData);

        // Success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully updated!'),
        ], 200);
    }

    public function pluckInstitutes(Request $request)
    {
        $University = University::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $University,

        ]);
    }

    public function get_course_campus()
    {
        $id = $_POST['id'];
        // Fetch campus details
        $campus = Course::where('id', $id)
            ->whereNotNull('campus')
            ->pluck('campus')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString));
            })
            ->first();

        if (empty($campus)) {
            return response()->json([
                'status' => 'success',
                'campus' => [],
                'intake_month' => [],
                'intake_year' => null,
            ]);
        }

        // Fetch intake month details

        $intake_month = Course::where('id', $id)
            ->whereNotNull('intake_month')
            ->pluck('intake_month')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString));
            })
            ->map(function ($month) {
                // Convert full month name to 3-letter abbreviation
                return substr(strtoupper($month), 0, 3);
            })
            ->toArray();

        // Then you can compare with your monthNames array
        $monthNames = [
            'JAN' => 'JAN',
            'FEB' => 'FEB',
            'MAR' => 'MAR',
            'APR' => 'APR',
            'MAY' => 'MAY',
            'JUN' => 'JUN',
            'JUL' => 'JUL',
            'AUG' => 'AUG',
            'SEP' => 'SEP',
            'OCT' => 'OCT',
            'NOV' => 'NOV',
            'DEC' => 'DEC',
        ];

        // Filter only valid month abbreviations
        $validMonths = array_filter($intake_month, function ($month) use ($monthNames) {
            return isset($monthNames[$month]);
        });

        // If you need unique values
        $validMonths = implode(',', array_unique($validMonths));

        // Fetch intake year
        $campusfetch = Course::find($id);
        $intake_year = $campusfetch ? $campusfetch->intakeYear : null;

        return response()->json([
            'status' => 'success',
            'campus' => $campus,
            'intake_month' => $validMonths,
            'intake_year' => $intake_year,
        ]);
    }

      public function applicationCountByUniversty(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }


        $data = \DB::table('application_stages as s')
            ->leftJoin('deal_applications as a', 'a.stage_id', '=', 's.id')
            ->select('s.id','s.name as label', \DB::raw('COUNT(a.id) as value'))
            ->groupBy('s.id','s.name')
            ->orderBy('s.id')
            ->where('a.university_id',$request->university_id)
            ->get();





        // Success response
        return response()->json([
            'status' => 'success',
            'application_count' =>  $data ,
        ], 200);
    }


      public function addmissionCountByUniversty(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }




            $data = \DB::table('stages as s')
        ->leftJoin('deals as d', 'd.stage_id', '=', 's.id')
        ->leftJoin('deal_applications as a', 'd.id', '=', 'a.deal_id')
        ->select('s.id','s.name', \DB::raw('COUNT(d.id) as total'))
        ->groupBy('s.id','s.name')
        ->orderBy('s.id')
         ->where('a.university_id',$request->university_id)
        ->get();





        // Success response
        return response()->json([
            'status' => 'success',
            'application_count' =>  $data ,
        ], 200);
    }

    public function brandWiseByUniversity(Request $request)
        {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'university_id' => 'required|exists:universities,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $data = \DB::table('users as u')
                ->leftJoin('deals as d', 'd.brand_id', '=', 'u.id')
                ->leftJoin('deal_applications as a', 'd.id', '=', 'a.deal_id')
                ->select(
                    'u.id',
                    'u.name as name',
                    \DB::raw('COUNT(DISTINCT d.id) as admissions'),
                    \DB::raw('COUNT(DISTINCT a.id) as applications')
                )
                ->where('u.type', 'company')
                ->where('a.university_id', $request->university_id)
                ->groupBy('u.id','u.name')
                ->orderBy('applications','desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'brand_wise' => $data,
            ], 200);
        }



}
