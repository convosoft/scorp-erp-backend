<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use App\Models\Course;
use App\Models\University;
use App\Models\CourseLevel;
use Illuminate\Http\Request;
use App\Models\CourseDuration;
use App\Models\Instalment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCourses(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
            'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Base query
        $query = Course::with(['instalments','university', 'created_by'])
            ->where('university_id', $request->university_id)->where('type', $request->type);



        $courses = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $courses,
        ], 200);
    }

    public function getCourseDetail(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:courses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Retrieve course detail
        $course = Course::with(['university', 'created_by'])
            ->where('id', $request->id)
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $course,
        ], 200);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addCourses(Request $request)
    {
        if (!Auth::user()->can('manage university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ], 403);
        }
        $request->merge([
            'OfferletterDownloadenabled' => filter_var($request->OfferletterDownloadenabled, FILTER_VALIDATE_BOOLEAN),
        ]);
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:150',
            'university_id' => 'required|exists:universities,id',
            'campus' => 'required',
            'intake_month' => 'required',
            'intakeYear' => 'required|integer|min:2000',
            'duration' => 'required',
            'gross_fees' => 'required|numeric|min:0',
            'net_fees' => 'required|numeric|min:0',
            'scholarship' => 'nullable|numeric|min:0',
            'first_instalment' => 'nullable|numeric|min:0',
            'second_instalment' => 'nullable|numeric|min:0',
            'third_instalment' => 'nullable|numeric|min:0',
            'final_instalment' => 'nullable|numeric|min:0',
            'installments' => 'nullable|array',
            'installments.*' => 'nullable|numeric|min:0',
            'OfferletterDownloadenabled' => 'nullable|boolean',
            'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $course = new Course();
        $course->name = $request->name;
        $course->type = $request->type;
        $course->university_id = $request->university_id;
        $course->campus = is_array($request->campus) ? implode(',', $request->campus) : $request->campus;
        $course->intake_month = is_array($request->intake_month) ? implode(',', $request->intake_month) : $request->intake_month;
        $course->intakeYear = $request->intakeYear;
        $course->duration = $request->duration;
        $course->gross_fees = $request->gross_fees;
        $course->net_fees = $request->net_fees;
        $course->scholarship = $request->scholarship;
        $course->first_instalment = $request->first_instalment;
        $course->second_instalment = $request->second_instalment;
        $course->third_instalment = $request->third_instalment;
        $course->final_instalment = $request->final_instalment;
        $course->created_by = Auth::user()->id;
        $course->OfferletterDownloadenabled = $request->OfferletterDownloadenabled ? 1 : 0;
        $course->save();

        if (!empty($request->installments)) {
            foreach ($request->installments as $installmentAmount) {
                $installment = new Instalment();
                $installment->course_id = $course->id;
                $installment->fee = $installmentAmount;
                $installment->save();
            }
        }

        // Optional: Log activity
          $typetext = $request->type == 1 ? 'international' : 'home';
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Course: ' . $course->name. ' created in ' . $typetext . ' ' .$course->university->name ,
                'message' => 'Course: ' . $course->name. ' created in ' .$typetext . ' ' . $course->university->name,
            ]),
            'module_id' => $course->university_id,
            'module_type' => 'university',
            'notification_type' => 'Course Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course successfully created!',
            'data' => [
                'course' => $course,
                'university' => $course->university,
            ]
        ]);
    }


    private function getCountryCurrency()
    {

        $currency_list = array(
            array("name" => "Afghan Afghani", "code" => "AFA"),
            array("name" => "Albanian Lek", "code" => "ALL"),
            array("name" => "Algerian Dinar", "code" => "DZD"),
            array("name" => "Angolan Kwanza", "code" => "AOA"),
            array("name" => "Argentine Peso", "code" => "ARS"),
            array("name" => "Armenian Dram", "code" => "AMD"),
            array("name" => "Aruban Florin", "code" => "AWG"),
            array("name" => "Australian Dollar", "code" => "AUD"),
            array("name" => "Azerbaijani Manat", "code" => "AZN"),
            array("name" => "Bahamian Dollar", "code" => "BSD"),
            array("name" => "Bahraini Dinar", "code" => "BHD"),
            array("name" => "Bangladeshi Taka", "code" => "BDT"),
            array("name" => "Barbadian Dollar", "code" => "BBD"),
            array("name" => "Belarusian Ruble", "code" => "BYR"),
            array("name" => "Belgian Franc", "code" => "BEF"),
            array("name" => "Belize Dollar", "code" => "BZD"),
            array("name" => "Bermudan Dollar", "code" => "BMD"),
            array("name" => "Bhutanese Ngultrum", "code" => "BTN"),
            array("name" => "Bitcoin", "code" => "BTC"),
            array("name" => "Bolivian Boliviano", "code" => "BOB"),
            array("name" => "Bosnia-Herzegovina Convertible Mark", "code" => "BAM"),
            array("name" => "Botswanan Pula", "code" => "BWP"),
            array("name" => "Brazilian Real", "code" => "BRL"),
            array("name" => "British Pound Sterling", "code" => "GBP"),
            array("name" => "Brunei Dollar", "code" => "BND"),
            array("name" => "Bulgarian Lev", "code" => "BGN"),
            array("name" => "Burundian Franc", "code" => "BIF"),
            array("name" => "Cambodian Riel", "code" => "KHR"),
            array("name" => "Canadian Dollar", "code" => "CAD"),
            array("name" => "Cape Verdean Escudo", "code" => "CVE"),
            array("name" => "Cayman Islands Dollar", "code" => "KYD"),
            array("name" => "CFA Franc BCEAO", "code" => "XOF"),
            array("name" => "CFA Franc BEAC", "code" => "XAF"),
            array("name" => "CFP Franc", "code" => "XPF"),
            array("name" => "Chilean Peso", "code" => "CLP"),
            array("name" => "Chilean Unit of Account", "code" => "CLF"),
            array("name" => "Chinese Yuan", "code" => "CNY"),
            array("name" => "Colombian Peso", "code" => "COP"),
            array("name" => "Comorian Franc", "code" => "KMF"),
            array("name" => "Congolese Franc", "code" => "CDF"),
            array("name" => "Costa Rican Colón", "code" => "CRC"),
            array("name" => "Croatian Kuna", "code" => "HRK"),
            array("name" => "Cuban Convertible Peso", "code" => "CUC"),
            array("name" => "Czech Republic Koruna", "code" => "CZK"),
            array("name" => "Danish Krone", "code" => "DKK"),
            array("name" => "Djiboutian Franc", "code" => "DJF"),
            array("name" => "Dominican Peso", "code" => "DOP"),
            array("name" => "East Caribbean Dollar", "code" => "XCD"),
            array("name" => "Egyptian Pound", "code" => "EGP"),
            array("name" => "Eritrean Nakfa", "code" => "ERN"),
            array("name" => "Estonian Kroon", "code" => "EEK"),
            array("name" => "Ethiopian Birr", "code" => "ETB"),
            array("name" => "Euro", "code" => "EUR"),
            array("name" => "Falkland Islands Pound", "code" => "FKP"),
            array("name" => "Fijian Dollar", "code" => "FJD"),
            array("name" => "Gambian Dalasi", "code" => "GMD"),
            array("name" => "Georgian Lari", "code" => "GEL"),
            array("name" => "German Mark", "code" => "DEM"),
            array("name" => "Ghanaian Cedi", "code" => "GHS"),
            array("name" => "Gibraltar Pound", "code" => "GIP"),
            array("name" => "Greek Drachma", "code" => "GRD"),
            array("name" => "Guatemalan Quetzal", "code" => "GTQ"),
            array("name" => "Guinean Franc", "code" => "GNF"),
            array("name" => "Guyanaese Dollar", "code" => "GYD"),
            array("name" => "Haitian Gourde", "code" => "HTG"),
            array("name" => "Honduran Lempira", "code" => "HNL"),
            array("name" => "Hong Kong Dollar", "code" => "HKD"),
            array("name" => "Hungarian Forint", "code" => "HUF"),
            array("name" => "Icelandic Króna", "code" => "ISK"),
            array("name" => "Indian Rupee", "code" => "INR"),
            array("name" => "Indonesian Rupiah", "code" => "IDR"),
            array("name" => "Iranian Rial", "code" => "IRR"),
            array("name" => "Iraqi Dinar", "code" => "IQD"),
            array("name" => "Israeli New Sheqel", "code" => "ILS"),
            array("name" => "Italian Lira", "code" => "ITL"),
            array("name" => "Jamaican Dollar", "code" => "JMD"),
            array("name" => "Japanese Yen", "code" => "JPY"),
            array("name" => "Jordanian Dinar", "code" => "JOD"),
            array("name" => "Kazakhstani Tenge", "code" => "KZT"),
            array("name" => "Kenyan Shilling", "code" => "KES"),
            array("name" => "Kuwaiti Dinar", "code" => "KWD"),
            array("name" => "Kyrgystani Som", "code" => "KGS"),
            array("name" => "Laotian Kip", "code" => "LAK"),
            array("name" => "Latvian Lats", "code" => "LVL"),
            array("name" => "Lebanese Pound", "code" => "LBP"),
            array("name" => "Lesotho Loti", "code" => "LSL"),
            array("name" => "Liberian Dollar", "code" => "LRD"),
            array("name" => "Libyan Dinar", "code" => "LYD"),
            array("name" => "Litecoin", "code" => "LTC"),
            array("name" => "Lithuanian Litas", "code" => "LTL"),
            array("name" => "Macanese Pataca", "code" => "MOP"),
            array("name" => "Macedonian Denar", "code" => "MKD"),
            array("name" => "Malagasy Ariary", "code" => "MGA"),
            array("name" => "Malawian Kwacha", "code" => "MWK"),
            array("name" => "Malaysian Ringgit", "code" => "MYR"),
            array("name" => "Maldivian Rufiyaa", "code" => "MVR"),
            array("name" => "Mauritanian Ouguiya", "code" => "MRO"),
            array("name" => "Mauritian Rupee", "code" => "MUR"),
            array("name" => "Mexican Peso", "code" => "MXN"),
            array("name" => "Moldovan Leu", "code" => "MDL"),
            array("name" => "Mongolian Tugrik", "code" => "MNT"),
            array("name" => "Moroccan Dirham", "code" => "MAD"),
            array("name" => "Mozambican Metical", "code" => "MZM"),
            array("name" => "Myanmar Kyat", "code" => "MMK"),
            array("name" => "Namibian Dollar", "code" => "NAD"),
            array("name" => "Nepalese Rupee", "code" => "NPR"),
            array("name" => "Netherlands Antillean Guilder", "code" => "ANG"),
            array("name" => "New Taiwan Dollar", "code" => "TWD"),
            array("name" => "New Zealand Dollar", "code" => "NZD"),
            array("name" => "Nicaraguan Córdoba", "code" => "NIO"),
            array("name" => "Nigerian Naira", "code" => "NGN"),
            array("name" => "North Korean Won", "code" => "KPW"),
            array("name" => "Norwegian Krone", "code" => "NOK"),
            array("name" => "Omani Rial", "code" => "OMR"),
            array("name" => "Pakistani Rupee", "code" => "PKR"),
            array("name" => "Panamanian Balboa", "code" => "PAB"),
            array("name" => "Papua New Guinean Kina", "code" => "PGK"),
            array("name" => "Paraguayan Guarani", "code" => "PYG"),
            array("name" => "Peruvian Nuevo Sol", "code" => "PEN"),
            array("name" => "Philippine Peso", "code" => "PHP"),
            array("name" => "Polish Zloty", "code" => "PLN"),
            array("name" => "Qatari Rial", "code" => "QAR"),
            array("name" => "Romanian Leu", "code" => "RON"),
            array("name" => "Russian Ruble", "code" => "RUB"),
            array("name" => "Rwandan Franc", "code" => "RWF"),
            array("name" => "Salvadoran Colón", "code" => "SVC"),
            array("name" => "Samoan Tala", "code" => "WST"),
            array("name" => "São Tomé and Príncipe Dobra", "code" => "STD"),
            array("name" => "Saudi Riyal", "code" => "SAR"),
            array("name" => "Serbian Dinar", "code" => "RSD"),
            array("name" => "Seychellois Rupee", "code" => "SCR"),
            array("name" => "Sierra Leonean Leone", "code" => "SLL"),
            array("name" => "Singapore Dollar", "code" => "SGD"),
            array("name" => "Slovak Koruna", "code" => "SKK"),
            array("name" => "Solomon Islands Dollar", "code" => "SBD"),
            array("name" => "Somali Shilling", "code" => "SOS"),
            array("name" => "South African Rand", "code" => "ZAR"),
            array("name" => "South Korean Won", "code" => "KRW"),
            array("name" => "South Sudanese Pound", "code" => "SSP"),
            array("name" => "Special Drawing Rights", "code" => "XDR"),
            array("name" => "Sri Lankan Rupee", "code" => "LKR"),
            array("name" => "St. Helena Pound", "code" => "SHP"),
            array("name" => "Sudanese Pound", "code" => "SDG"),
            array("name" => "Surinamese Dollar", "code" => "SRD"),
            array("name" => "Swazi Lilangeni", "code" => "SZL"),
            array("name" => "Swedish Krona", "code" => "SEK"),
            array("name" => "Swiss Franc", "code" => "CHF"),
            array("name" => "Syrian Pound", "code" => "SYP"),
            array("name" => "Tajikistani Somoni", "code" => "TJS"),
            array("name" => "Tanzanian Shilling", "code" => "TZS"),
            array("name" => "Thai Baht", "code" => "THB"),
            array("name" => "Tongan Pa'anga", "code" => "TOP"),
            array("name" => "Trinidad & Tobago Dollar", "code" => "TTD"),
            array("name" => "Tunisian Dinar", "code" => "TND"),
            array("name" => "Turkish Lira", "code" => "TRY"),
            array("name" => "Turkmenistani Manat", "code" => "TMT"),
            array("name" => "Ugandan Shilling", "code" => "UGX"),
            array("name" => "Ukrainian Hryvnia", "code" => "UAH"),
            array("name" => "United Arab Emirates Dirham", "code" => "AED"),
            array("name" => "Uruguayan Peso", "code" => "UYU"),
            array("name" => "US Dollar", "code" => "USD"),
            array("name" => "Uzbekistan Som", "code" => "UZS"),
            array("name" => "Vanuatu Vatu", "code" => "VUV"),
            array("name" => "Venezuelan BolÃvar", "code" => "VEF"),
            array("name" => "Vietnamese Dong", "code" => "VND"),
            array("name" => "Yemeni Rial", "code" => "YER"),
            array("name" => "Zambian Kwacha", "code" => "ZMK"),
            array("name" => "Zimbabwean dollar", "code" => "ZWL")
        );
        return  $currency_list;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\Response
     */
    public function __updateCourses(Request $request)
    {
        if (!in_array(Auth::user()->type, ['Product Coordinator', 'super admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:150',
            'university_id' => 'required|exists:universities,id',
            'id' => 'required|exists:courses,id',
            'campus' => 'required',
            'intake_month' => 'required',
            'intakeYear' => 'required|integer|min:2000',
            'duration' => 'required',
            'gross_fees' => 'required|numeric|min:0',
            'net_fees' => 'required|numeric|min:0',
            'scholarship' => 'nullable|numeric|min:0',
            'first_instalment' => 'nullable|numeric|min:0',
            'second_instalment' => 'nullable|numeric|min:0',
            'third_instalment' => 'nullable|numeric|min:0',
            'final_instalment' => 'nullable|numeric|min:0',
            'installments' => 'required|array',
            'installments.*.id' => 'sometimes|integer',
            'installments.*.fee' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $id = $request->id;
        $course = Course::findOrFail($id);
        $updatedFields = [];

        $fieldsToUpdate = [
            'name' => $request->name,
            'university_id' => $request->university_id,
            'campus' => is_array($request->campus) ? implode(',', $request->campus) : $request->campus,
            'intake_month' => is_array($request->intake_month) ? implode(',', $request->intake_month) : $request->intake_month,
            'intakeYear' => $request->intakeYear,
            'duration' => $request->duration,
            'gross_fees' => $request->gross_fees,
            'net_fees' => $request->net_fees,
            'scholarship' => $request->scholarship,
            'first_instalment' => $request->first_instalment,
            'second_instalment' => $request->second_instalment,
            'third_instalment' => $request->third_instalment,
            'final_instalment' => $request->final_instalment,
        ];

        foreach ($fieldsToUpdate as $field => $newValue) {
            if ($course->$field != $newValue) {
                $updatedFields[$field] = [
                    'old' => $course->$field,
                    'new' => $newValue
                ];
                $course->$field = $newValue;
            }
        }

        $course->save();

        // Sync Installments (optional reset logic, or update logic as needed)
        if (!empty($request->installments)) {
            $existingIds = [];

            foreach ($request->installments as $installmentData) {
                if (isset($installmentData['id'])) {
                    // Update existing installment
                    $installment = Instalment::updateOrCreate(
                        ['id' => $installmentData['id'], 'course_id' => $course->id],
                        ['fee' => $installmentData['fee']]
                    );
                    $existingIds[] = $installment->id;
                } else {
                    // Create new installment
                    $installment = new Instalment();
                    $installment->course_id = $course->id;
                    $installment->fee = $installmentData['fee'];
                    $installment->save();
                    $existingIds[] = $installment->id;
                }
            }

            // Delete any installments not in the current request
            Instalment::where('course_id', $course->id)
                      ->whereNotIn('id', $existingIds)
                      ->delete();

            $updatedFields['installments'] = 'Updated installments data';
        }

        // Log only if any field was actually changed
        if (!empty($updatedFields)) {

              $typetext = $course->type == 1 ? 'international' : 'home';
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $typetext . ' ' .'Course Updated',
                    'message' => 'The following fields were updated:',
                    'changes' => $updatedFields
                ]),
                'module_id' => $course->university_id,
                'module_type' => 'university',
                'notification_type' => 'Course Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Course successfully updated.',
            'data' => [
                'course' => $course,
                'university' => $course->university,
            ]
        ]);
    }

    public function updateCourses(Request $request)
{
    if (!in_array(Auth::user()->type, ['Product Coordinator', 'super admin'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission Denied.'
        ], 403);
    }
    $request->merge([
        'OfferletterDownloadenabled' => filter_var($request->OfferletterDownloadenabled, FILTER_VALIDATE_BOOLEAN),
    ]);
    $validator = Validator::make($request->all(), [
        'name' => 'required|max:150',
        'university_id' => 'required|exists:universities,id',
        'id' => 'required|exists:courses,id',
        'campus' => 'required',
        'intake_month' => 'required',
        'intakeYear' => 'required|integer|min:2000',
        'duration' => 'required',
        'gross_fees' => 'required|numeric|min:0',
        'net_fees' => 'required|numeric|min:0',
        'scholarship' => 'nullable|numeric|min:0',
        'first_instalment' => 'nullable|numeric|min:0',
        'second_instalment' => 'nullable|numeric|min:0',
        'third_instalment' => 'nullable|numeric|min:0',
        'final_instalment' => 'nullable|numeric|min:0',
        'course_location' => 'nullable|string|max:200',
        'course_information' => 'nullable|string|max:200',
        'installments' => 'required|array',
        'installments.*.id' => 'sometimes|integer',
        'installments.*.fee' => 'required|numeric',
        'OfferletterDownloadenabled' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    $id = $request->id;
    $course = Course::findOrFail($id);
    $updatedFields = [];

    $fieldsToUpdate = [
        'name' => $request->name,
        'university_id' => $request->university_id,
        'campus' => is_array($request->campus) ? implode(',', $request->campus) : $request->campus,
        'intake_month' => is_array($request->intake_month) ? implode(',', $request->intake_month) : $request->intake_month,
        'intakeYear' => $request->intakeYear,
        'duration' => $request->duration,
        'gross_fees' => $request->gross_fees,
        'net_fees' => $request->net_fees,
        'scholarship' => $request->scholarship,
        'first_instalment' => $request->first_instalment,
        'second_instalment' => $request->second_instalment,
        'third_instalment' => $request->third_instalment,
        'final_instalment' => $request->final_instalment,
        'course_information' => $request->course_information,
        'course_location' => $request->course_location,
        'min_requirements' => $request->min_requirements,
        'course_tags' => $request->course_tags,
    ];

    foreach ($fieldsToUpdate as $field => $newValue) {
        if ($course->$field != $newValue) {
            $updatedFields[$field] = [
                'old' => $course->$field,
                'new' => $newValue
            ];
            $course->$field = $newValue;
        }
    }
    $course->OfferletterDownloadenabled = $request->OfferletterDownloadenabled ? 1 : 0;
    $course->save();

    // Handle Installments
    if (!empty($request->installments)) {
        $existingIds = [];

        foreach ($request->installments as $installmentData) {
            if (isset($installmentData['id'])) {
                $installment = Instalment::updateOrCreate(
                    ['id' => $installmentData['id'], 'course_id' => $course->id],
                    ['fee' => $installmentData['fee']]
                );
                $existingIds[] = $installment->id;
            } else {
                $installment = new Instalment();
                $installment->course_id = $course->id;
                $installment->fee = $installmentData['fee'];
                $installment->save();
                $existingIds[] = $installment->id;
            }
        }

        Instalment::where('course_id', $course->id)
                  ->whereNotIn('id', $existingIds)
                  ->delete();

        $updatedFields['installments'] = 'Installments were updated';
    }

    // Log Activity in the desired format
    if (!empty($updatedFields)) {
        $user = \Auth::user();
        $universityName = University::where('id', $course->university_id)->value('name');
        $fieldList = implode(', ', array_map('ucwords', array_keys($updatedFields)));
          $typetext = $course->type == 1 ? 'international' : 'home';
        $logDetails = [
            'title' => "Course: {$course->name}  Updated in {$typetext} {$universityName}",
            'message' => "Fields updated: {$fieldList}",
            'changes' => $updatedFields
        ];

        addLogActivity([
            'type' => 'info',
            'note' => json_encode($logDetails),
            'module_id' => $course->university_id,
            'module_type' => 'university',
            'notification_type' => 'Course Updated',
            'created_by' => $user->id
        ]);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Course successfully updated.',
        'data' => [
            'course' => $course,
            'university' => $course->university,
        ]
    ]);
}


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\Response
     */
    public function deleteCourse(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:courses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check user type (permission)
        if (\Auth::user()->type !== 'Product Coordinator' && \Auth::user()->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Find the Course
        $course = Course::find($request->id);


          $typetext = $course->type == 1 ? 'international' : 'home';

        // Log the deletion
         addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => 'Course: ' . $course->name. ' deleted in ' .$typetext . ' ' . $course->university->name,
                'message' => 'Course: ' . $course->name. ' deleted in ' .$typetext . ' ' . $course->university->name,
            ]),
            'module_id' => $course->university_id,
            'module_type' => 'university',
            'notification_type' => 'Course deleted',
        ]);

        // Delete the course
        $course->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('Course successfully deleted!')
        ], 200);
    }


    public function get_course_campus()
    {
        $id = $_GET['id'];

        // Fetch campus details
        $campus = Course::where('id', $id)
            ->whereNotNull('campus')
            ->pluck('campus')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString));
            })
            ->toArray();
        if (empty($campus)) {
            return json_encode([
                'status' => 'success',
                'campus_html' => '',
                'intake_html' => '',
                'intake_year_html' => '',
            ]);
        }
        // Fetch intake month details
        $intake_month = Course::where('id', $id)
            ->whereNotNull('intake_month')
            ->pluck('intake_month')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString));
            })
            ->toArray();

        $course_html = '';
        $intake_html = '';
        $intake_year_html = '';

        // Campus dropdown (read-only)
        // ............
        $course_html = '<select name="campus" class="form form-control select2 validationSideColor" id="campus" disabled>';
        $course_html .= '<option value="">Select Campus</option>';
        if (!empty($campus)) {
            foreach ($campus as $course) {
                $course_html .= '<option value="' . $course . '" selected disabled> ' . $course . ' </option>';
            }
        }
        $course_html .= '</select>';

        // Intake month dropdown (read-only)
        $intake_html = '<select name="intake_month" class="form form-control select2 validationSideColor" id="intake_month" disabled>';
        $intake_html .= '<option value="">Select Month</option>';
        if (!empty($intake_month)) {
            foreach ($intake_month as $intake) {
                $intake_html .= '<option value="' . $intake . '" selected disabled> ' . $intake . ' </option>';
            }
        }
        $intake_html .= '</select>';

        // Fetch course details for intake year
        $campusfetch = Course::find($id);

        $intake_year_html = '<select name="intakeYear" class="form form-control select2 validationSideColor" id="intakeYear" disabled>';
        $intake_year_html .= '<option value="">Select Year</option>';

        // Check if $campusfetch is not null before accessing intakeYear
        if ($campusfetch && !empty(intakeYear())) {
            foreach (intakeYear() as $intakeYear) {
                $selected = (trim((string) $intakeYear) === trim((string) $campusfetch->intakeYear)) ? 'selected' : '';
                $intake_year_html .= '<option value="' . $intakeYear . '" ' . $selected . ' disabled> ' . $intakeYear . ' </option>';
            }
        }

        $intake_year_html .= '</select>';
        if (!empty($campusfetch)) {
            $intake_year_html .= '
        <input type="hidden" name="intakeYear" value="' . $campusfetch->intakeYear . '">
        <input type="hidden" name="intake_month" value="' . $campusfetch->intake_month . '">
        <input type="hidden" name="campus" value="' . $campusfetch->campus . '">
        ';
        }


        return json_encode([
            'status' => 'success',
            'campus_html' => $course_html,
            'intake_html' => $intake_html,
            'intake_year_html' => $intake_year_html,
        ]);
    }

    public function pluckCourse(Request $request)
        {
            $Course = Course::pluck('name', 'id')->where('type',1)->toArray();
            return response()->json([
                'status' => 'success',
                'data' => $Course

            ]);
        }


public function courseFinder(Request $request)
{
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

    $query = Course::query()
        ->with(['instalments'])
        ->leftJoin('universities', 'courses.university_id', '=', 'universities.id')
        ->leftJoin('countries as c_id', 'universities.country', '=', 'c_id.id')
        ->leftJoin('countries as c_name', 'universities.country', '=', 'c_name.name')
        ->select(
            'courses.*',
            'universities.name as university_name',
            'universities.country as raw_country',
            'universities.campuses'
        )
        ->selectRaw("$caseCountry AS resolved_country_name");

    /*
    |--------------------------------------------------------------------------
    | HARD FILTERS
    |--------------------------------------------------------------------------
    */

    if ($request->filled('type')) {
        $query->where('courses.type', $request->type);
    }

     $selectedCountryNames = [];
    if (!empty($request->countries)) {
        $selectedCountryNames = \App\Models\Country::whereIn('id', $request->countries)
            ->pluck('name')
            ->map(fn($name) => strtolower(trim($name)))
            ->toArray();
    }
    

    if (!empty($selectedCountryNames)) {

        $placeholders = implode(',', array_fill(0, count($selectedCountryNames), '?'));

        $query->whereRaw("LOWER(TRIM($caseCountry)) IN ($placeholders)", $selectedCountryNames);
    }

    if ($request->filled('universities')) {
        $query->whereIn('courses.university_id', (array)$request->universities);
    }

    /*
    |--------------------------------------------------------------------------
    | FLEXIBLE FILTERS
    |--------------------------------------------------------------------------
    */

    if ($request->is_intakeYear_flexible == 1 && $request->filled('intakeYear')) {
        $query->whereIn('courses.intakeYear', (array)$request->intakeYear);
    }

    if ($request->is_intakeMonth_flexible == 1 && $request->filled('intake_month')) {
        $months = (array)$request->intake_month;

        $query->where(function ($q) use ($months) {
            foreach ($months as $month) {
                $q->orWhereRaw("FIND_IN_SET(?, courses.intake_month)", [trim($month)]);
            }
        });
    }

            $keywords = array_filter([
            strtolower($request->course),
            strtolower($request->degree_level)
        ]);

        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhereRaw('LOWER(courses.name) LIKE ?', ["%{$word}%"]);
                }
            });
        }

    if ($request->is_campus_flexible == 1 && $request->filled('campus')) {
        $campuses = (array)$request->campus;

        $query->where(function ($q) use ($campuses) {
            foreach ($campuses as $campus) {
                $q->orWhere('courses.campus', 'LIKE', '%' . trim($campus) . '%');
            }
        });
    }

    if ($request->is_budget_flexible == 1 && $request->filled('budget')) {
        try {
            [$min, $max] = explode('-', $request->budget);

            $query->whereBetween(
                DB::raw('CAST(courses.gross_fees AS UNSIGNED)'),
                [(int)$min, (int)$max]
            );
        } catch (\Exception $e) {}
    }

    /*
    |--------------------------------------------------------------------------
    | AI SCORING & ENRICHMENT
    |--------------------------------------------------------------------------
    */

    $selectedUniversities = $request->universities ?? [];

    // $sql = str_replace('?', "'%s'", $query->toSql());
    // $sql = vsprintf($sql, $query->getBindings());
    // dd($sql);   

    $allCourses = $query->latest()->get();

    $scoredCourses = $allCourses->map(function ($course) use ($request, $selectedUniversities, $selectedCountryNames) {
        $score = 0;

        // University Match (+30)
        if (!empty($selectedUniversities) && in_array($course->university_id, $selectedUniversities)) {
            $score += 30;
        }

        // Country Match (+20)
        if (!empty($selectedCountryNames) && in_array(strtolower(trim($course->resolved_country_name)), $selectedCountryNames)) {
            $score += 20;
        }

        // Budget Match (+20)
        if ($request->filled('budget')) {
            try {
                [$min, $max] = explode('-', $request->budget);
                if ($course->gross_fees >= $min && $course->gross_fees <= $max) {
                    $score += 20;
                }
            } catch (\Exception $e) {}
        }

        // Course/Keyword Match (+15)
        if ($request->filled('course') && str_contains(strtolower($course->name), strtolower($request->course))) {
            $score += 15;
        }

        // Campus Match (+10)
        if ($request->filled('campus') && str_contains(strtolower($course->campus), strtolower(implode(',', (array)$request->campus)))) {
            $score += 10;
        }

        // Intake Match (+5)
        if ($request->filled('intake_month') && str_contains($course->intake_month, implode(',', (array)$request->intake_month))) {
            $score += 5;
        }
        if ($request->filled('intakeYear') && str_contains($course->intakeYear, implode(',', (array)$request->intakeYear))) {
            $score += 5;
        }

        $course->match_score = min($score, 100);
        return $course;
    });

    // Sort ALL courses by match_score DESC
    $sortedCourses = $scoredCourses->sortByDesc('match_score')->values();
    
    // Sort ALL courses by match_score DESC
    $sortedCourses = $scoredCourses->sortByDesc('match_score')->values();
    
    $aiSummary = "";
    $askAi = $request->ask_ai;

    //dd($askAi);

    // --- AI MODES (1: Recommendation, 2: PDF Generation) ---
    if ($askAi == 1 || $askAi == 2) {
        $selectedIds = (array)$request->selected_course_ids;
        $topCandidates = collect();

        if ($askAi == 1) {
            // Recommendation Logic: Selected courses + Top Database matches (up to 10 total)
            $selectedCourses = $sortedCourses->whereIn('id', $selectedIds)->take(10);
            $remainingCount = 10 - $selectedCourses->count();
            $otherTopCourses = $sortedCourses->whereNotIn('id', $selectedIds)->take($remainingCount);
            $topCandidates = $selectedCourses->concat($otherTopCourses)->values();
        } else {

            dd($selectedIds,$askAi);
            // PDF Logic: Only the manually selected courses
            $topCandidates = $sortedCourses->whereIn('id', $selectedIds)->values();
        }

        if ($topCandidates->isNotEmpty()) {
            $studentProfile = $request->only([
                'full_name', 'last_qualification', 'department', 'degree_name', 
                'cgpa', 'intakeYear', 'passingYear', 'language_test', 'total_bands', 
                'intake_month', 'course', 'degree_level', 'budget'
            ]);

            $coursesContext = $topCandidates->map(function($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'university' => $c->university_name,
                    'country' => $c->resolved_country_name,
                    'info' => $c->course_information,
                    'location' => $c->course_location,
                    'fees' => $c->gross_fees,
                    'requirements' => $c->min_requirements ?? ''
                ];
            })->toArray();

            $aiPrompt = "
            You are the SCORP AI Course Finder expert. Based on the student's profile and the matched courses, generate a personalized report.
            
            Student Profile:
            " . json_encode($studentProfile) . "
            
            Top Matched Courses:
            " . json_encode($coursesContext) . "
            
            Tasks:
            1. Write a short, professional greeting (2-3 sentences) addressed to the student by name ({$request->full_name}).
            2. Identify at most 1 course as a 'top_pick' (set is_top_pick to true).
            3. For each course, provide:
               - A 'strategic_rationale' (1 sentence explaining why this course is a good fit).
               - A 'final_match_score' (re-evaluated percentage from 0-100 based on profile fit).
               - A boolean 'is_top_pick'.
            
            Return exactly this JSON format:
            {
              \"greeting\": \"...\",
              \"recommendations\": [
                {
                  \"course_id\": 123,
                  \"rationale\": \"...\",
                  \"match_percentage\": 95,
                  \"is_top_pick\": true
                }
              ]
            }
            ";

            try {
                $aiResponse = OpenAI::chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are the SCORP AI Course Finder expert. Respond ONLY in JSON.'],
                        ['role' => 'user', 'content' => $aiPrompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

                $aiData = json_decode($aiResponse->choices[0]->message->content, true);
                $aiSummary = $aiData['greeting'] ?? "";
                
                // Map AI data back to candidates
                $topCandidates = $topCandidates->map(function($course) use ($aiData) {
                    $aiMatch = collect($aiData['recommendations'] ?? [])->firstWhere('course_id', $course->id);
                    
                    // Explode existing comma-separated tags from DB
                    $tagsArray = $course->course_tags ? array_map('trim', explode(',', $course->course_tags)) : [];
                    
                    if ($aiMatch) {
                        $course->ai_rationale = $aiMatch['rationale'];
                         if ($askAi == 1 ) {
                        $course->match_score = $aiMatch['match_percentage'];
                         }
                        // Add "TOP PICK" to the start of tags if AI identifies it as such
                        if ($aiMatch['is_top_pick'] ?? false) {
                            array_unshift($tagsArray, "TOP PICK");
                        }
                    }
                    
                    $course->course_tags = implode(', ', array_unique($tagsArray));
                    return $course;
                })->sortByDesc('match_score')->values();

                return response()->json([
                    'status' => 'success',
                    'ai_summary' => $aiSummary,
                    'courses' => $topCandidates,
                ], 200);

            } catch (\Exception $e) {
                \Log::error("CourseFinder AI Enrichment failed: " . $e->getMessage());
            }
        }
    }

    // Default Search Results: Return all (no AI) with tag cleaning
    $sortedCourses = $sortedCourses->map(function ($course) {
        $tagsArray = $course->course_tags ? array_map('trim', explode(',', $course->course_tags)) : [];
        $course->course_tags = implode(', ', array_unique($tagsArray));
        return $course;
    });

    return response()->json([
        'status' => 'success',
        'courses' => $sortedCourses,
    ], 200);
}

public function enrichCourseWithAI(Request $request)
{
   

    // Fetch courses that are missing either information or location
    $courses = Course::has('university')->with('university')
        ->where(function($query) {
            $query->whereNull('course_information')
                  ->orWhere('course_information', '')
                  ->orWhereNull('course_location')
                  ->orWhere('course_location', '')
                  ->orWhereNull('course_tags')
                  ->orWhere('course_tags', '');
        })
        ->limit(50) // Limit to avoid request timeouts
        ->get();

    if ($courses->isEmpty()) {
        return response()->json([
            'status' => 'success',
            'message' => 'No courses found needing enrichment.'
        ]);
    }



    $updatedCount = 0;
    $errors = [];

    foreach ($courses as $course) {
        $university = $course->university;
        $countryName = '';
        if ($university) {
            if (is_numeric($university->country)) {
                $country = \App\Models\Country::find($university->country);
                $countryName = $country?->name ?? $university->country;
            } else {
                $countryName = $university->country;
            }
        }

       // dd($countryName,$course,$university);

        $prompt = "
        Provide a professional description (course information), specific campus address (course location), and 2-3 short professional tags (e.g., 'Research-Led', 'High Employability', 'Career-Focused') for the course '{$course->name}' at '{$university->name}' in '{$countryName}', specifically for the '{$course->campus}' campus.
        
        Constraints:
        - 'information' and 'location' MUST be strictly under 200 characters each.
        - 'tags' should be an array of 2-3 short strings.
        - Return the result in JSON format with exactly three keys: 'information', 'location', and 'tags'.
        ";

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert in university courses. Respond only in JSON format.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = json_decode($response->choices[0]->message->content, true);

            if (isset($content['information']) && isset($content['location']) && isset($content['tags'])) {
                $course->course_information = substr($content['information'], 0, 200);
                $course->course_location = substr($content['location'], 0, 200);
                $course->course_tags = implode(', ', (array)$content['tags']);
                $course->save();
                $updatedCount++;
            } else {
                $errors[] = "Course ID {$course->id}: Invalid AI response.";
            }

        } catch (\Exception $e) {
            $errors[] = "Course ID {$course->id}: " . $e->getMessage();
        }
    }

    return response()->json([
        'status' => 'success',
        'message' => "Enrichment complete. Updated {$updatedCount} courses.",
        'errors' => $errors
    ]);
}


}
