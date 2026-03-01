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
use App\Models\City;
use App\Models\instalment;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\LeadTag;
use App\Models\LeadStage;
use App\Models\LogActivity;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Schema;

class GeneralController extends Controller
{


    public function getDefaultFiltersData(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        //$user_type = User::distinct()->pluck('type', 'id')->toArray();
        $access_levels = accessLevel();

        if (!empty($request->brand) || !empty($request->region_id) || !empty($request->branch_id)) {
            $filters = BrandsRegionsBranchesForEdit($request->brand, $request->region_id, $request->branch_id);
        } else {
            $filters = BrandsRegionsBranches();
        }

        $type = \Auth::user()->type;

        $saved_filters = SavedFilter::where('created_by', \Auth::user()->id)->where('module', $request->module)->get();


        return response()->json([
            'status' => 'success',
            'access_levels' => $access_levels,
            'filters' => $filters,
            'type' => $type,
            'saved_filters' => $saved_filters,
            'message' => 'Data fetched successfully'
        ]);
    }


    public function getRegionBrands(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $id = $request->input('id');
        $type = $request->input('type');

        if ($type == 'branch') {
            return  FiltersBranchUsersFORTASK($id);
        } elseif ($type == 'brand') {
            // Fetch regions based on the brand ID
            $regions = Region::where('brands', $id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

            // Return JSON response with regions
            return response()->json([
                'status' => 'success',
                'regions' => $regions,
            ]);
        } elseif ($type == 'region') {
            // Fetch branches based on the region ID
            $branches = Branch::where('region_id', $id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

            // Return JSON response with branches
            return response()->json([
                'status' => 'success',
                'branches' => $branches,
            ]);
        } elseif ($type == 'institute') {
            // Fetch institute details based on the ID
            $institute = University::where('id', $id)->first();

            // If institute exists, get the intake months
            if ($institute) {
                $intake_months = $institute->intake_months ?? '';
                $intake_months = explode(',', $intake_months);

                return response()->json([
                    'status' => 'success',
                    'intake_months' => $intake_months,
                ]);
            } else {
                return response()->json([
                    'status' => 'failure',
                    'message' => 'Institute not found.',
                ]);
            }
        } else {
            // Fetch region details based on the ID
            $region = Region::where('id', $id)->first();
            $brands = [];

            if ($region) {
                $ids = explode(',', $region->brands);
                $brands = User::whereIn('id', $ids)->where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

                // Return JSON response with brands
                return response()->json([
                    'status' => 'success',
                    'brands' => $brands,
                ]);
            } else {
                return response()->json([
                    'status' => 'failure',
                    'message' => 'Region not found.',
                ]);
            }
        }
    }













    public function agentTeamPluck(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'id' => 'required|integer|exists:users,id',
        // ]);


         $authuser = \Auth::user(); // Authenticated user

        if ( $authuser->type !='Agent') {
            return response()->json([
                'status' => 'error',
                'errors' => 'Only Agent can access this.',
            ], 422);
        }



        $user = User::where('agent_id', $authuser->agent_id)->where('is_active', 1)->pluck('name', 'id');

        if (!$user) {
            return response()->json([
                'status' => 'failure',
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'user' => $user, // returns its own id
        ]);
    }



    public function getMultiRegionBrands(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'brand_ids' => 'required|array',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $id = $request->input('brand_ids');
        // print_r($id);
        // die;
        $type = $request->input('type');

        if ($type == 'branch') {
            return  FiltersBranchUsersFORTASK($id);
        } elseif ($type == 'brand') {
            // Fetch regions based on the brand ID
            $regions = Region::with('brand')
                ->whereHas('brand', function ($query) use ($id) {
                    $query->whereIn('id', $id);
                })
                ->orderBy('name', 'ASC')
                ->get()
                ->mapWithKeys(function ($region) {
                    $key = $region->id; // Use the region's ID as the key
                    $value = $region->name . ($region->brand ? '-' . $region->brand->name : ''); // Concatenate region name with brand name
                    return [$key => $value];
                })
                ->toArray();

            // Return JSON response with regions
            return response()->json([
                'status' => 'success',
                'regions' => $regions,
            ]);
        } elseif ($type == 'region') {
            // Fetch branches based on the region ID
            $branches = Branch::with(['brand', 'region']) // Load related brand and region
                ->whereHas('region', function ($query) use ($id) { // Correctly use the 'region' relationship
                    $query->whereIn('id', $id);
                })
                ->orderBy('name', 'ASC')
                ->get()
                ->mapWithKeys(function ($branch) {
                    $key = $branch->id; // Use the branch's ID as the key
                    $value = $branch->name
                            . ($branch->brand ? '-' . $branch->brand->name : '')
                            . ($branch->region ? '-' . $branch->region->name : ''); // Safely concatenate brand and region names
                    return [$key => $value];
                })
                ->toArray();

            // Return JSON response with branches
            return response()->json([
                'status' => 'success',
                'branches' => $branches,
            ]);

        } elseif ($type == 'institute') {
            // Fetch institute details based on the ID
            $institute = University::where('id', $id)->first();

            // If institute exists, get the intake months
            if ($institute) {
                $intake_months = $institute->intake_months ?? '';
                $intake_months = explode(',', $intake_months);

                return response()->json([
                    'status' => 'success',
                    'intake_months' => $intake_months,
                ]);
            } else {
                return response()->json([
                    'status' => 'failure',
                    'message' => 'Institute not found.',
                ]);
            }
        } else {
            // Fetch region details based on the ID
            $region = Region::where('id', $id)->first();
            $brands = [];

            if ($region) {
                $ids = explode(',', $region->brands);
                $brands = User::whereIn('id', $ids)->where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

                // Return JSON response with brands
                return response()->json([
                    'status' => 'success',
                    'brands' => $brands,
                ]);
            } else {
                return response()->json([
                    'status' => 'failure',
                    'message' => 'Region not found.',
                ]);
            }
        }
    }





























    public function getAllBrands(Request $request)
    {



        // Fetch region details based on the ID
        $region = Region::where('id', $id)->first();
        $brands = [];

        $ids = explode(',', $region->brands);
        $brands = User::where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        if ($brands) {


            // Return JSON response with brands
            return response()->json([
                'status' => 'success',
                'brands' => $brands,
            ]);
        } else {
            return response()->json([
                'status' => 'failure',
                'message' => 'Brand not found.',
            ]);
        }
    }


    public function getAllProjectDirectors(Request $request)
    {




        $ProjectDirector = User::where('type', 'Project Director')
                     ->orWhere('id', 3257)
                     ->pluck('name', 'id')
                     ->toArray();

        if ($ProjectDirector) {


            // Return JSON response with brands
            return response()->json([
                'status' => 'success',
                'data' => $ProjectDirector,
            ]);
        } else {
            return response()->json([
                'status' => 'failure',
                'message' => 'Brand not found.',
            ]);
        }
    }


    public function getFilterData(Request $request)
    {
        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|integer|exists:users,id',
            'region_id' => 'required|integer|exists:regions,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'type' => 'required|string|in:lead,tasks',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failure',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Extract validated parameters
        $brand_id = $request->input('brand_id');
        $region_id = $request->input('region_id');
        $branch_id = $request->input('branch_id');
        $type = $request->input('type');

        $data = [];

        if ($type == 'lead') {
            if (!empty($region_id) && !empty($branch_id)) {
                $leads = Lead::where('branch_id', $branch_id)
                    ->where('region_id', $region_id)
                    ->pluck('name', 'id')
                    ->toArray();
            } else {
                $leads = Lead::where('brand_id', $brand_id)
                    ->when($region_id, fn($query) => $query->where('region_id', $region_id))
                    ->when($branch_id, fn($query) => $query->where('branch_id', $branch_id))
                    ->pluck('name', 'id')
                    ->toArray();
            }

            $data = $leads;
        } elseif ($type == 'tasks') {
            if (!empty($region_id) && empty($branch_id)) {
                $tasks = DealTask::where('brand_id', $brand_id)
                    ->where('region_id', $region_id)
                    ->pluck('name', 'id')
                    ->toArray();
            } else {
                $tasks = DealTask::where('brand_id', $brand_id)
                    ->when($region_id, fn($query) => $query->where('region_id', $region_id))
                    ->when($branch_id, fn($query) => $query->where('branch_id', $branch_id))
                    ->pluck('name', 'id')
                    ->toArray();
            }

            $data = $tasks;
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }



    public function getFilterBranchUsers(Request $request)
    {

        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failure',
                'errors' => $validator->errors(),
            ], 422);
        }
        $html = FiltersBranchUsers($request->branch_id);


        return response()->json([
            'status' => 'success',
            'data' => $html,
        ]);
    }


    public function getSources()
    {
        $sources = Source::pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $sources,
        ], 200);
    }

    /**
     * Get Branches
     */
    public function getBranches()
    {
        $branches = Branch::pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $branches,
        ], 200);
    }

    public function getJobCategories()
    {
        $categories = JobCategory::get()->pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ], 200);
    }

    /**
     * Get Stages
     */
    public function getStages()
    {
        $stages = LeadStage::all();
        return response()->json([
            'status' => 'success',
            'data' => $stages,
        ], 200);
    }


    public function getRolesPluck()
    {
        $excludedTypes = ['company', 'team', 'client'];
        $roles = Role::whereNotIn('name', $excludedTypes)->get()->unique('name')->pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ], 200);
    }

    public function getapplicationStagesPluck()
    {
         $stages = ApplicationStage::orderBy('id')->pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $stages,
        ], 200);
    }

    /**
     * Get Saved Filters
     */
    public function getSavedFilters(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = \Auth::user(); // Authenticated user

        $savedFilters = SavedFilter::where('created_by', $user->id)
            ->where('module', $request->module)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $savedFilters,
        ], 200);
    }


    public function getTags(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $tags = [];

            if (in_array($user->type, ['super admin', 'Admin Team'])) {
                $tags = LeadTag::pluck('tag', 'id')->toArray();
            } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('tag', 'id')->toArray();
            } elseif ($user->type === 'Region Manager') {
                $tags = LeadTag::where('region_id', $user->region_id)->pluck('tag', 'id')->toArray();
            } else {
                $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('tag', 'id')->toArray();
            }

            return response()->json([
                'status' => 'success',
                'data' => $tags,
            ], 200);
        }

        return response()->json([
            'status' => 'false',
            'message' => 'Unauthorized',
        ], 401);
    }
    public function getTagsByBrandId(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $tags = [];
            if($request->brand_id){
               $tags = LeadTag::where('brand_id', $request->brand_id)->pluck('tag', 'id')->toArray();
            }

            return response()->json([
                'status' => 'success',
                'data' => $tags,
            ], 200);
        }

        return response()->json([
            'status' => 'false',
            'message' => 'Unauthorized',
        ], 401);
    }
    public function FilterSave(Request $request)
    {
        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'filter_name' => 'required|string|max:255',
            'url' => 'required|url',
            'module' => 'required|string|max:255',
            'count' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failure',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Save the filter data
        try {
            $filter = new SavedFilter();
            $filter->filter_name = $request->filter_name;
            $filter->url = $request->url;
            $filter->module = $request->module;
            $filter->count = $request->count;
            $filter->created_by = auth()->id(); // Use the authenticated user's ID
            $filter->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Filter saved successfully.',
                'data' => $filter,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failure',
                'message' => 'An error occurred while saving the filter.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function UpdateFilterSave(Request $request)
    {
        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'filter_name' => 'required|string|max:255',
            'url' => 'required|url',
            'module' => 'required|string|max:255',
            'count' => 'required|integer|min:0',
            'FilterID' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failure',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Save the filter data
        try {
            $filter = SavedFilter::find($request->FilterID) ?? new SavedFilter();
            $filter->filter_name = $request->filter_name;
            $filter->url = $request->url;
            $filter->module = $request->module;
            $filter->count = $request->count;
            $filter->created_by = auth()->id(); // Use the authenticated user's ID
            $filter->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Filter Update successfully.',
                'data' => $filter,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failure',
                'message' => 'An error occurred while saving the filter.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function Country()
    {
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $Country,
        ], 200);

    }

    public function getAllcurrencies()
    {
        $currencyOptions = [
                'USD' => ' $ USD - US Dollar',
                'EUR' => ' € EUR - Euro',
                'GBP' => ' £ GBP - British Pound',
                'JPY' => ' ¥ JPY - Japanese Yen',
                'CAD' => ' C$ CAD - Canadian Dollar',
                'AUD' => ' A$ AUD - Australian Dollar',
                'CHF' => ' CHF CHF - Swiss Franc',
                'CNY' => ' ¥ CNY - Chinese Yuan',
                'INR' => ' ₹ INR - Indian Rupee',
                'RUB' => ' ₽ RUB - Russian Ruble',
                'BRL' => ' R$ BRL - Brazilian Real',
                'MXN' => ' $ MXN - Mexican Peso',
                'KRW' => ' ₩ KRW - South Korean Won',
                'TRY' => ' ₺ TRY - Turkish Lira',
                'ZAR' => ' R ZAR - South African Rand',
                'SEK' => ' kr SEK - Swedish Krona',
                'NOK' => ' kr NOK - Norwegian Krone',
                'DKK' => ' kr DKK - Danish Krone',
                'PLN' => ' zł PLN - Polish Złoty',
                'HUF' => ' Ft HUF - Hungarian Forint',
                'CZK' => ' Kč CZK - Czech Koruna',
                'ILS' => ' ₪ ILS - Israeli New Shekel',
                'AED' => ' د.إ AED - UAE Dirham',
                'SAR' => ' ﷼ SAR - Saudi Riyal',
                'THB' => ' ฿ THB - Thai Baht',
                'MYR' => ' RM MYR - Malaysian Ringgit',
                'SGD' => ' S$ SGD - Singapore Dollar',
                'HKD' => ' HK$ HKD - Hong Kong Dollar',
                'NZD' => ' NZ$ NZD - New Zealand Dollar',
                'PHP' => ' ₱ PHP - Philippine Peso',
                'IDR' => ' Rp IDR - Indonesian Rupiah',
                'VND' => ' ₫ VND - Vietnamese Dong',
                'BDT' => ' ৳ BDT - Bangladeshi Taka',
                'PKR' => ' ₨ PKR - Pakistani Rupee',
                'EGP' => ' £ EGP - Egyptian Pound',
                'NGN' => ' ₦ NGN - Nigerian Naira',
                'KES' => ' KSh KES - Kenyan Shilling',
                'GHS' => ' GH₵ GHS - Ghanaian Cedi',
                'ETB' => ' Br ETB - Ethiopian Birr',
                'COP' => ' $ COP - Colombian Peso',
                'ARS' => ' $ ARS - Argentine Peso',
                'CLP' => ' $ CLP - Chilean Peso',
                'PEN' => ' S/ PEN - Peruvian Sol',
                'UYU' => ' $ UYU - Uruguayan Peso',
                'BOB' => ' Bs BOB - Bolivian Boliviano',
                'PYG' => ' ₲ PYG - Paraguayan Guarani',
                'CRC' => ' ₡ CRC - Costa Rican Colón',
                'DOP' => ' RD$ DOP - Dominican Peso',
                'GTQ' => ' Q GTQ - Guatemalan Quetzal',
                'HNL' => ' L HNL - Honduran Lempira',
                'NIO' => ' C$ NIO - Nicaraguan Córdoba',
                'PAB' => ' B/. PAB - Panamanian Balboa',
                'SVC' => ' $ SVC - Salvadoran Colón',
                'TTD' => ' $ TTD - Trinidad and Tobago Dollar',
                'JMD' => ' $ JMD - Jamaican Dollar',
                'BSD' => ' $ BSD - Bahamian Dollar',
                'BBD' => ' $ BBD - Barbados Dollar',
                'BZD' => ' $ BZD - Belize Dollar',
                'XCD' => ' $ XCD - East Caribbean Dollar',
                'AWG' => ' ƒ AWG - Aruban Florin',
                'ANG' => ' ƒ ANG - Netherlands Antillean Guilder',
                'KYD' => ' $ KYD - Cayman Islands Dollar',
                'BMD' => ' $ BMD - Bermudian Dollar',
                'FJD' => ' $ FJD - Fijian Dollar',
                'WST' => ' T WST - Samoan Tala',
                'TOP' => ' T$ TOP - Tongan Paʻanga',
                'SBD' => ' $ SBD - Solomon Islands Dollar',
                'VUV' => ' Vt VUV - Vanuatu Vatu',
                'XPF' => ' ₣ XPF - CFP Franc',
                'KHR' => ' ៛ KHR - Cambodian Riel',
                'LAK' => ' ₭ LAK - Lao Kip',
                'MMK' => ' Ks MMK - Myanmar Kyat',
                'MNT' => ' ₮ MNT - Mongolian Tugrik',
                'BND' => ' $ BND - Brunei Dollar',
                'LKR' => ' Rs LKR - Sri Lankan Rupee',
                'MVR' => ' Rf MVR - Maldivian Rufiyaa',
                'NPR' => ' Rs NPR - Nepalese Rupee',
                'AFN' => ' ؋ AFN - Afghan Afghani',
                'IRR' => ' ﷼ IRR - Iranian Rial',
                'IQD' => ' ع.د IQD - Iraqi Dinar',
                'JOD' => ' د.ا JOD - Jordanian Dinar',
                'KWD' => ' د.ك KWD - Kuwaiti Dinar',
                'LBP' => ' ل.ل LBP - Lebanese Pound',
                'OMR' => ' ر.ع. OMR - Omani Rial',
                'QAR' => ' ر.ق QAR - Qatari Riyal',
                'SYP' => ' £ SYP - Syrian Pound',
                'YER' => ' ﷼ YER - Yemeni Rial',
                'BHD' => ' .د.ب BHD - Bahraini Dinar',
                'KZT' => ' ₸ KZT - Kazakhstani Tenge',
                'UZS' => ' UZS - Uzbekistani Som',
                'AZN' => ' ₼ AZN - Azerbaijani Manat',
                'GEL' => ' ₾ GEL - Georgian Lari',
                'AMD' => ' ֏ AMD - Armenian Dram',
                'BYN' => ' Br BYN - Belarusian Ruble',
                'MDL' => ' L MDL - Moldovan Leu',
                'RON' => ' lei RON - Romanian Leu',
                'BGN' => ' лв BGN - Bulgarian Lev',
                'ALL' => ' L ALL - Albanian Lek',
                'MKD' => ' ден MKD - Macedonian Denar',
                'RSD' => ' дин. RSD - Serbian Dinar',
                'HRK' => ' kn HRK - Croatian Kuna',
                'BAM' => ' KM BAM - Bosnia-Herzegovina Convertible Mark',
                'ISK' => ' kr ISK - Icelandic Króna',
                'UAH' => ' ₴ UAH - Ukrainian Hryvnia',
                'GIP' => ' £ GIP - Gibraltar Pound',
                'MOP' => ' MOP$ MOP - Macanese Pataca',
                'TWD' => ' NT$ TWD - New Taiwan Dollar',
                'PGK' => ' K PGK - Papua New Guinean Kina',
                'MWK' => ' MK MWK - Malawian Kwacha',
                'ZMW' => ' ZK ZMW - Zambian Kwacha',
                'ZWL' => ' $ ZWL - Zimbabwean Dollar',
                'NAD' => ' $ NAD - Namibian Dollar',
                'BWP' => ' P BWP - Botswana Pula',
                'LSL' => ' L LSL - Lesotho Loti',
                'SZL' => ' E SZL - Swazi Lilangeni',
                'MUR' => ' ₨ MUR - Mauritian Rupee',
                'SCR' => ' ₨ SCR - Seychellois Rupee',
                'MGA' => ' Ar MGA - Malagasy Ariary',
                'CDF' => ' FC CDF - Congolese Franc',
                'RWF' => ' FRw RWF - Rwandan Franc',
                'BIF' => ' FBu BIF - Burundian Franc',
                'DJF' => ' Fdj DJF - Djiboutian Franc',
                'ERN' => ' Nfk ERN - Eritrean Nakfa',
                'SOS' => ' Sh SOS - Somali Shilling',
                'TZS' => ' Sh TZS - Tanzanian Shilling',
                'UGX' => ' Sh UGX - Ugandan Shilling',
                'MZN' => ' MT MZN - Mozambican Metical',
                'STN' => ' Db STN - São Tomé and Príncipe Dobra',
                'CVE' => ' $ CVE - Cape Verdean Escudo',
                'KMF' => ' CF KMF - Comorian Franc',
                'GMD' => ' D GMD - Gambian Dalasi',
                'SLL' => ' Le SLL - Sierra Leonean Leone',
                'LRD' => ' $ LRD - Liberian Dollar',
                'GNF' => ' FG GNF - Guinean Franc',
                'MRU' => ' UM MRU - Mauritanian Ouguiya',
                'XOF' => ' CFA XOF - West African CFA Franc',
                'XAF' => ' FCFA XAF - Central African CFA Franc',
                'CUP' => ' $ CUP - Cuban Peso',
                'CUC' => ' $ CUC - Cuban Convertible Peso',
                'VED' => ' Bs VED - Venezuelan Bolívar Digital',
                'UYW' => ' UYW - Uruguayan Nominal Wage Index Unit',
                'CHE' => ' CHE - WIR Euro',
                'CHW' => ' CHW - WIR Franc',
                'COU' => ' COU - Colombian Real Value Unit',
                'BOV' => ' BOV - Bolivian Mvdol',
                'CLF' => ' CLF - Chilean Unit of Account (UF)',
                'MXV' => ' MXV - Mexican Investment Unit',
            ];

        return response()->json([
            'status' => 'success',
            'data' => $currencyOptions,
        ], 200);

    }

    public function CountryByCode()
    {
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'country_code')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $Country,
        ], 200);

    }
    public function CountryByID()
    {
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $Country,
        ], 200);

    }

    public function getLogActivity_old(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'type' => 'required|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => __('Validation Error.'),
            'errors' => $validator->errors(),
        ], 422);
    }

    // Fetch log activity records
    if( $request->type=='user'){
            $logs = LogActivity::with('createdBy:id,name')->where('created_by', $request->id)
                ->orderBy('created_at', 'desc')
                ->limit(500)
                ->get();
    }else{
        $logs = LogActivity::with('createdBy:id,name')->where('module_id', $request->id)
                ->where('module_type', $request->type)
                ->orderBy('created_at', 'desc')
                ->limit(500)
                ->get();
    }

    return response()->json([
        'status' => true,
        'message' => 'Log activities fetched successfully.',
        'data' => $logs
    ]);
}

public function getLogActivity(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'type' => 'required|string|max:100',
        'perPage' => 'sometimes|integer|min:1|max:500',
        'page' => 'sometimes|integer|min:1',
        'date' => 'sometimes|string', // Accept as string to parse flexibly
        'search' => 'sometimes|string|max:255',
        'module_type' => 'sometimes|string|max:100',
        'logtype' => 'sometimes|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => __('Validation Error.'),
            'errors' => $validator->errors(),
        ], 422);
    }

    // Determine pagination size
    $num_results_on_page = env("RESULTS_ON_PAGE", 50);
    if ($request->has('perPage')) {
        $num_results_on_page = (int)$request->get('perPage');
    }

    // Base query
    $query = LogActivity::with('createdBy:id,name')
        ->orderBy('created_at', 'desc');

    if ($request->type === 'user') {
        $query->where('created_by', $request->id);
        $query->where('module_type','!=', 'employeeprofile');
    } else {
        $query->where('module_id', $request->id) ;
    }

    // Apply start_date filter
    if ($request->filled('date')) {
        $rawDate = $request->date;

        try {
            $parsedDate = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');
            $query->whereDate('created_at', $parsedDate);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid start_date format. Please use a valid date.',
                'errors' => [
                    'start_date' => ['The start_date could not be parsed.']
                ]
            ], 422);
        }
    }

    // module_type filter
    if ($request->filled('type') && $request->type != 'user') {
        $query->where('module_type', $request->type);
    }

    // type filter
    if ($request->filled('logtype')) {
        $query->where('type', $request->logtype);
    }
    // type filter
    if ($request->filled('module_type')) {
        $query->where('module_type', $request->module_type);
    }

    // search filter
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('note', 'like', '%' . $search . '%')
              ->orWhereHas('createdBy', function ($subQuery) use ($search) {
                  $subQuery->where('name', 'like', '%' . $search . '%');
              });
        });
    }

    // Total record count before pagination
    $total_records = $query->count();

    // Paginate
    $logs = $query->paginate($num_results_on_page);

    return response()->json([
        'status' => true,
        'message' => 'Log activities fetched successfully.',
        'data' => $logs->items(),
        'total_records' => $total_records,
        'current_page' => $logs->currentPage(),
        'last_page' => $logs->lastPage(),
        'per_page' => $logs->perPage(),
    ]);
}

public function getDistinctModuleTypes(Request $request)
{

    // Base query
    $query = LogActivity::query();


    // Get distinct module types
    $distinctModuleTypes = $query
        ->select('module_type')
        ->distinct()
        ->orderBy('module_type')
        ->pluck('module_type')
        ->toArray();

    return response()->json([
        'status' => true,
        'message' => 'Distinct module types fetched successfully.',
        'data' => $distinctModuleTypes,
    ]);
}


public function DeleteSavedFilter(Request $request)
{
    // Validate the Request Data
    $validator = Validator::make(
        $request->all(),
        [
            'id' => 'required|exists:saved_filters,id',
        ]
    );

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 400);
    }

    // Attempt to Find the Log Entry
    $SavedFilter = SavedFilter::find($request->id);

    if ($SavedFilter) {
        $SavedFilter->delete();
        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => __('Filter successfully deleted!'),
        ], 200);
    } else {
        return response()->json([
            'status' => 'error',
            'message' => __('Filter Not Found'),
        ], 404); // Using 404 for "not found" is more appropriate
    }


}

public function UniversityByCountryCode(Request $request)
{
        $request->validate([
            'country' => 'required|string',
        ]);
        try {
            $country = $request->get('country');
            $country_code = Country::where('country_code', $country)->first();
            if ($country_code) {
                $universities = University::where('uni_status', '0')
                    ->whereRaw("FIND_IN_SET(?, country)", [$country_code->name])
                    ->pluck('name', 'id')
                    ->toArray();
                $universities = $universities;
            } else {
                $universities = [''];
            }
            return response()->json([
                'status' => "success",
                'data' => $universities,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => "success",
                'message' => $e->getMessage(),
            ], 500);
        }
}

public function GetBranchByType()
{
    ini_set('memory_limit', '256M');
    $type = $_POST['type'] ?? null;
    $BranchId = Auth::user()->type == 'super admin' ? 0 : Auth::user()->branch_id;
    if (!$type) {
        return json_encode([
            'status' => 'error',
            'message' => 'Type and Branch ID are required.',
        ]);
    }
    try {
        switch ($type) {
            case 'lead':
                $leadsQuery = \App\Models\Lead::query();
                $userType = \Auth::user()->type;

                if ($userType === 'company') {
                    $leadsQuery->where('brand_id', \Auth::user()->id);
                } elseif ($userType === 'Region Manager' && !empty(\Auth::user()->region_id)) {
                    $leadsQuery->where('region_id', \Auth::user()->region_id);
                } elseif ($userType === 'Branch Manager' && !empty(\Auth::user()->branch_id)) {
                    $leadsQuery->where('branch_id', \Auth::user()->branch_id);
                } elseif ($userType === 'Agent') {
                    $leadsQuery->where('agent_id', \Auth::user()->agent_id);
                }

                // Ensure data exists in the query result
                $data = $leadsQuery->select('id', 'name')->pluck('name', 'id')->toArray();
                break;
            case 'organization':
                $data = User::where('type', 'organization')->pluck('name', 'id')->toArray();
                break;

            case 'deal':
                $data = Deal::where('branch_id', $BranchId)->pluck('name', 'id')->toArray();
                break;

            case 'application':
                $data = DealApplication::join('deals', 'deals.id', '=', 'deal_applications.deal_id')
                    ->where('deals.branch_id', $BranchId)
                    ->pluck('deal_applications.name', 'deal_applications.id')
                    ->toArray();
                break;

            case 'toolkit':
                $data = University::pluck('name', 'id')->toArray();
                break;

            case 'agency':
                $data = User::join('agencies', 'agencies.user_id', '=', 'users.id')
                    ->where('approved_status', 2)
                    ->pluck('agencies.organization_name', 'agencies.id')
                    ->toArray();
                break;

            default:
                $data = User::where('branch_id', $BranchId)
                    ->where('type', 'organization')
                    ->pluck('name', 'id')
                    ->toArray();
                break;
        }

        return response()->json([
            'status' => "success",
            'data' => $data,
        ], 200);
    } catch (\Exception $e) {
        return json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

 public function leadsrequireddata(Request $request)
    {
        // Validate input
        $stages = LeadStage::pluck('name', 'id');

        // Get organizations that are not companies
        $organizations = User::where('type', 'organization')->pluck('name', 'id');
        $sources = Source::pluck('name', 'id');

        // Get approved agencies
        if (Auth::user()->type === 'Agent') {

            $agencies = User::join('agencies', 'agencies.user_id', '=', 'users.id')
                ->where('users.id', Auth::id())
                ->where('users.is_active', 1)
                ->selectRaw('COALESCE(agencies.organization_name, users.name) as display_name, agencies.id')
                ->pluck('display_name', 'agencies.id');

        } else {
            $agencies = User::join('agencies', 'agencies.user_id', '=', 'users.id')
                ->where('users.is_active', '1')
                ->pluck('agencies.organization_name', 'agencies.id');
        }
        $tags = [];

            if (Auth::check()) {
                $user = Auth::user();

                if (in_array($user->type, ['super admin', 'Admin Team'])) {
                    $tags = LeadTag::pluck('tag', 'id');
                } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                    $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('tag', 'id');
                } elseif (in_array($user->type, ['Region Manager'])) {
                    $tags = LeadTag::where('region_id', $user->region_id)->pluck('tag', 'id');
                } else {
                    $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('tag', 'id');
                }
            }

        // Fetch countries
        $countries = countries();

        // Return the response
        return response()->json([
            'status' => "success",
            'data' => [
                'stages' => $stages,
                'organizations' => $organizations,
                'sources' => $sources,
                'agencies' => $agencies,
                'countries' => $countries,
                'tags' => $tags,
            ]
        ]);
    }

    public function getCitiesOnCode(Request $request)
    {
        $countryCode = $request->input('code');
        $cities = City::where('country_code', $countryCode)->pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $cities
        ]);
    }

    public function getCitiesOnid(Request $request)
    {
        $countryCode = $request->input('code');
        $cities = City::where('coutry_id', $countryCode)->pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $cities
        ]);
    }


    public function DealTagPluck()
    {
        $LeadTag = LeadTag::pluck('id', 'tag')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $LeadTag
        ]);
    }

    public function DealStagPluck()
    {
        $Stage = Stage::pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $Stage
        ]);
    }

    public function ApplicationStagPluck()
    {
        $stages = ApplicationStage::pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $stages
        ]);
    }

    public function convertToBase64(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid URL provided',
                'errors' => $validator->errors()
            ], 422);
        }

        $imageUrl = $request->input('image_url');
        try {
            // Fetch the image with timeout and proper headers
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'image/*'
                ])
                ->get($imageUrl);

            if ($response->successful()) {
                $mimeType = $this->detectMimeType($response->body());

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'base64' => 'data:' . $mimeType . ';base64,' . base64_encode($response->body()),
                        'mime_type' => $mimeType,
                        'size' => strlen($response->body())
                    ]
                ]);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch image. Server responded with status: ' . $response->status()
            ], 400);
        } catch (\Exception $e) {
            Log::error("Image conversion error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => "Image conversion error: " . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Detect MIME type from binary data
     */
    private function detectMimeType($binaryData)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($binaryData);
    }

     public function getemailTags(Request $request)
    {
        $type = $request->type;

        $tags = DB::table('email_tags')
            ->where('type', 'universal')
            ->orWhere('type', $type)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }
   public function getemailTagstype(Request $request)
{
    $tags = DB::table('email_tags')
        ->where('type', '!=', 'universal')
        ->distinct()
        ->pluck('type'); // returns a flat array like ["employee", "leave", "attendance"]

    return response()->json([
        'success' => true,
        'data' => $tags
    ]);
}

    public function totalSummary(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'type' => 'required|string|in:week,month',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()
                ], 422);
            }

            $employeeId = $request->input('user_id');
            $type = $request->input('type', 'week');
            // Calculate date range based on type
            if ($type === 'week') {
                $startDate = now()->startOfWeek()->toDateString();
                $endDate = now()->endOfWeek()->toDateString();
            } elseif ($type === 'month') {
                $startDate = now()->startOfMonth()->toDateString();
                $endDate = now()->endOfMonth()->toDateString();
            }

            $countsQuery = DB::table('users')
                ->leftJoin('attendance_employees as attendances', function ($join) use ($startDate, $endDate) {
                    $join->on('attendances.employee_id', '=', 'users.id')
                        ->whereBetween('attendances.date', [$startDate, $endDate]);
                })
                ->where('users.id', $employeeId);

            $statusCounts = $countsQuery->select(
                DB::raw("SUM(CASE WHEN attendances.id IS NOT NULL AND attendances.clock_in IS NOT NULL AND attendances.clock_in <= DATE_ADD(attendances.shift_start, INTERVAL 30 MINUTE) AND attendances.status = 'Present' AND attendances.earlyCheckOutReason IS NULL  THEN 1 ELSE 0 END) as OnTime"),
                DB::raw("SUM(CASE WHEN attendances.id IS NOT NULL AND attendances.clock_in IS NOT NULL AND attendances.clock_in > DATE_ADD(attendances.shift_start, INTERVAL 30 MINUTE) AND attendances.status = 'Present' AND attendances.earlyCheckOutReason IS NULL THEN 1 ELSE 0 END) as Late"),
                DB::raw("SUM(CASE WHEN attendances.id IS NOT NULL AND attendances.status = 'Absent' THEN 1 ELSE 0 END ) as `Absent`"),
                DB::raw("SUM(CASE WHEN attendances.id IS NOT NULL AND attendances.status = 'Leave' THEN 1 ELSE 0 END) as `Leave`"),
                DB::raw("SUM(CASE WHEN attendances.id IS NOT NULL AND attendances.earlyCheckOutReason IS NOT NULL THEN 1 ELSE 0 END) as `Early_Clock_Out`")
            )->first();

            // Calculate summary
            $onTime = (int) ($statusCounts->OnTime ?? 0);
            $late = (int) ($statusCounts->Late ?? 0);
            $absent = (int) ($statusCounts->Absent ?? 0);
            $leave = (int) ($statusCounts->Leave ?? 0);
            $earlyClockOut = (int) ($statusCounts->Early_Clock_Out ?? 0);

            // Present = OnTime + Late (both are present but differentiated by punctuality)
            $present = $onTime + $late;

            $holiday = 3; // static or compute dynamically
            $total_days = $present + $absent + $leave + $holiday;
            $working_days = $present + $absent + $leave;

            // Calculate working hours
            $workingHoursQuery = DB::table('attendance_employees')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('clock_in')
                ->whereNotNull('clock_out')
                ->where('clock_in', '!=', '00:00:00')
                ->where('clock_out', '!=', '00:00:00');

            $total_working_seconds = 0;
            $records = $workingHoursQuery->get();

            foreach ($records as $record) {
                $clockIn = Carbon::parse($record->date . ' ' . $record->clock_in);
                $clockOut = Carbon::parse($record->date . ' ' . $record->clock_out);

                // Handle overnight shifts
                if ($clockOut->lessThan($clockIn)) {
                    $clockOut->addDay();
                }
                $total_working_seconds += $clockOut->diffInSeconds($clockIn);
            }

            $total_working_hours = gmdate('H:i', $total_working_seconds);
            $record_count = count($records);
            $average_working_seconds = $record_count > 0 ? $total_working_seconds / $record_count : 0;
            $average_working_hours = gmdate('H:i', $average_working_seconds);
            $on_time_rate = $working_days > 0 ? round(($onTime / $working_days) * 100, 2) : 0;

            $attendance_summary = [
                "present" => $present + $onTime,
                "late" => $late,
                "absent" => $total_days - ($present + $onTime + $late + $holiday),
                "leave" => $leave,
                "holiday" => $holiday,
                "total_days" => $total_days,
                "working_days" => $working_days,
                "total_working_hours" => $total_working_hours,
                "average_working_hours" => $average_working_hours,
                "on_time_rate" => $on_time_rate,
                "early_clock_out" => $earlyClockOut
            ];

            return $attendance_summary;
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getAttendanceReport(Request $request)
    {

        $validator = Validator::make($request->all(), [
                    'user_id' => 'required|exists:users,id',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $validator->errors()
                    ], 422);
                }

            $from = $request->input('from', date('Y-m-1'));
            $to = $request->input('to', date('Y-m-31'));
            $employeeId = $request->input('user_id');

        $records = DB::table('attendance_employees')
            ->where('employee_id', $request->user_id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date', 'desc')
            ->get();

        $data = $records->map(function ($record) {
                $shiftStart = strtotime($record->shift_start);
                $shiftEnd   = strtotime($record->shift_end);
                $shiftDuration = $shiftEnd - $shiftStart;

                $clockIn  = ($record->clock_in && $record->clock_in !== "00:00:00")
                    ? strtotime($record->clock_in)
                    : null;

                $clockOut = ($record->clock_out && $record->clock_out !== "00:00:00")
                    ? strtotime($record->clock_out)
                    : null;

                $graceShiftStart = strtotime("+30 minutes", $shiftStart);

                $clockInStatus = $clockOutStatus = $overallStatus = null;

                // --- If both clockin and clockout missing ---
                if (!$clockIn && !$clockOut) {
                    $clockInStatus = ['label' => $record->status, 'badge' => 'blue'];
                    $clockOutStatus = ['label' => $record->status, 'badge' => 'blue'];
                    $overallStatus = ['label' => $record->status, 'badge' => 'blue'];
                } else {
                    // Clock In Status
                    if (!$clockIn) {
                        $clockInStatus = ['label' => $record->status, 'badge' => 'gray'];
                    } elseif ($clockIn <= $graceShiftStart) {
                        $clockInStatus = ['label' => 'On Time', 'badge' => 'green'];
                    } elseif ($record->status === "Present") {
                        $clockInStatus = ['label' => 'Present', 'badge' => 'green'];
                    } else {
                        $clockInStatus = ['label' => 'Late', 'badge' => 'red'];
                    }

                    // Clock Out Status
                    if (!$clockOut || !$clockIn) {
                        $clockOutStatus = ['label' => $record->status, 'badge' => 'gray'];
                    } else {
                        $workedDuration = $clockOut - $clockIn;
                        if ($workedDuration >= $shiftDuration) {
                            $clockOutStatus = ['label' => 'Completed Shift', 'badge' => 'green'];
                        } else {
                            $minutes = round(($shiftDuration - $workedDuration) / 60);
                            $clockOutStatus = ['label' => $minutes . ' min Short', 'badge' => 'red'];
                        }
                    }

                    // --- Overall Status ---
                    if (
                        isset($clockInStatus['label'], $clockOutStatus['label']) &&
                        $clockInStatus['label'] === 'On Time' &&
                        $clockOutStatus['label'] === 'Completed Shift'
                    ) {
                        $overallStatus = ['label' => 'Good', 'badge' => 'green'];
                    } else {
                        $overallStatus = ['label' => 'Bad', 'badge' => 'red'];
                    }
                }

                return [
                    'date' => $record->date,
                    'clock_in' => $record->clock_in,
                    'clock_in_status' => $clockInStatus,
                    'clock_out' => $record->clock_out,
                    'clock_out_status' => $clockOutStatus,
                    'status' => $record->status,
                    'overall_status' => $overallStatus,
                ];
            });


        return response()->json([
            'employee_id' => $employeeId,
            'from' => $from,
            'to' => $to,
            'attendance' => $data,
        ]);
    }



    public function saveSystemSettings(Request $request)
    {


        $user = Auth::user();

        if (!$user->can('manage company settings')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $post = $request->except(['_token']);


        $settings = Utility::settings();

        // Capture original settings for logging
        $originalData = $settings;

        // Track changes
        $changes = [];
        $updatedFields = [];
       // dd($post);
        foreach ($post as $key => $data) {
            if (array_key_exists($key, $settings)) {
                    if ($settings[$key] != $data) {
                        $changes[$key] = [
                            'old' => $settings[$key],
                            'new' => $data
                        ];
                        $updatedFields[] = $key;
                    }
                 }

                DB::insert(
                    'insert into settings (`value`, `name`,`created_by`,`created_at`,`updated_at`)
                     values (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                    [
                        $data,
                        $key,
                        1,
                        now(),
                        now(),
                    ]
                );
        }

        // 🔹 Log only if changes exist
        if (!empty($changes)) {
            $typeoflog = ' system settings';
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $user->name. ucfirst($typeoflog) . ' updated ' ,
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $user->creatorId(),
                'module_type' => 'settings',
                'notification_type' => ucfirst($typeoflog) . ' Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Settings successfully updated.'),
            'updated_fields' => $updatedFields
        ], 200);
    }

    public function fetchSystemSettings(Request $request)
    {
        $user = Auth::user();

        // if (!$user->can('manage company settings')) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission denied.')
        //     ], 403);
        // }

        // Fetch settings for current company/creator
        $settings = DB::table('settings')
            ->pluck('value', 'name') // returns key-value pair
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ], 200);
    }

     public function getTables()
    {
        try {
            $driver = DB::getDriverName();
            $tables = [];

            switch ($driver) {
                case 'mysql':
                    $tables = DB::select('SHOW TABLES');
                    $tables = array_map('current', $tables);
                    break;

                case 'pgsql':
                    $tables = DB::table('pg_tables')
                        ->where('schemaname', 'public')
                        ->pluck('tablename')
                        ->toArray();
                    break;

                case 'sqlite':
                    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                    $tables = array_map(fn($t) => $t->name, $tables);
                    break;

                case 'sqlsrv':
                    $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                    $tables = array_map(fn($t) => $t->TABLE_NAME, $tables);
                    break;
            }

            return response()->json([
                'status' => 'success',
                'driver' => $driver,
                'tables' => $tables
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


public function getTableData_old(Request $request)
{
    // ✅ Validation
    $validator = Validator::make($request->all(), [
        'table'     => 'required|string',
        'last_sync' => 'nullable|date',
        'per_page'  => 'nullable|integer|min:1|max:1000',
        'filters'   => 'nullable|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    $table    = $request->input('table');
    $lastSync = $request->input('last_sync');
    $perPage  = $request->input('per_page', 500);
    $filters  = $request->input('filters', []);

    // ✅ Check if table exists
    if (!Schema::hasTable($table)) {
        return response()->json([
            'status'  => 'error',
            'message' => "Table '{$table}' does not exist."
        ], 404);
    }

    // ✅ Build query
    $query = DB::table($table);

    // Add last_sync condition (new + updated records)
    if ($lastSync) {
        $query->where(function ($q) use ($lastSync) {
            $q->where('created_at', '>=', $lastSync)
              ->orWhere('updated_at', '>=', $lastSync);
        });
    }

    // ✅ Apply filters safely (only existing columns)
    if (!empty($filters)) {
        foreach ($filters as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }
    }

    // ✅ Pagination
    $data = $query->paginate($perPage);

    return response()->json([
        'status'       => 'success',
        'table'        => $table,
        'last_sync'    => $lastSync,
        'filters'      => $filters,
        'total'        => $data->total(),
        'per_page'     => $data->perPage(),
        'current_page' => $data->currentPage(),
        'last_page'    => $data->lastPage(),
        'data'         => $data->items()
    ]);
}


public function getTableData(Request $request)
{
    // ✅ Validation
    $validator = Validator::make($request->all(), [
        'table'     => 'required|string',
        'last_sync' => 'nullable|date',
        'per_page'  => 'nullable|integer|min:1|max:1000',
        'filters'   => 'nullable|array',
        'last_id'   => 'nullable|integer|min:0', // keyset pagination cursor
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    $table    = $request->input('table');
    $lastSync = $request->input('last_sync');
    $perPage  = $request->input('per_page', 500);
    $filters  = $request->input('filters', []);
    $lastId   = $request->input('last_id', 0);

    // ✅ Check if table exists
    if (!Schema::hasTable($table)) {
        return response()->json([
            'status'  => 'error',
            'message' => "Table '{$table}' does not exist."
        ], 404);
    }

    // ✅ Build query
    $query = DB::table($table)->orderBy('id', 'asc');

    // Apply keyset pagination
    if ($lastId > 0) {
        $query->where('id', '>', $lastId);
    }

    // Add last_sync condition (new + updated records)
    if ($lastSync) {
        $query->where(function ($q) use ($lastSync) {
            $q->where('created_at', '>=', $lastSync)
              ->orWhere('updated_at', '>=', $lastSync);
        });
    }

    // ✅ Apply filters safely
   // ✅ Apply filters safely
        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                if (Schema::hasColumn($table, $column)) {
                    if (is_array($value)) {
                        $query->whereIn($column, $value);
                    } else {
                        $query->where($column, $value);
                    }
                }
            }
        }


    // ✅ Fetch with +1 trick to detect "has_more"
    $rows = $query->limit($perPage + 1)->get();

    $hasMore = $rows->count() > $perPage;
    $data    = $rows->take($perPage);

    return response()->json([
        'status'       => 'success',
        'table'        => $table,
        'last_sync'    => $lastSync,
        'filters'      => $filters,
        'per_page'     => $perPage,
        'next_id'      => $hasMore ? $data->last()->id : null, // 👈 cursor
        'has_more'     => $hasMore,
        'data'         => $data,
    ]);
}


}
