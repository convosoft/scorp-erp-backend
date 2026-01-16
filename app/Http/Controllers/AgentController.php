<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomField;
use App\Models\Employee;
use App\Models\Region;
use Illuminate\Support\Str;
use App\Models\SavedFilter;
use App\Models\User;
use App\Models\Agency;
use App\Models\Utility;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

use Illuminate\Support\Facades\Validator;
use File;
class AgentController extends Controller
{


public function agentRequestGet(Request $request)
{
    try {
        // Get authenticated user
        $userDetail = \Auth::user();
        $userDetail->customField = CustomField::getData($userDetail, 'user');

        // Fetch custom fields for the user
        $customFields = CustomField::where('created_by', '=', \Auth::user()->creatorId())
                                   ->where('module', '=', 'user')
                                   ->get();

        // Fetch agency details
        $agency = Agency::where('user_id', \Auth::id())->first();

        // Fetch list of countries (assuming countries() is a helper function)
        $countries = countries();

        // Return JSON response
        return response()->json([
            'success' => true,
            'message' => 'Request data retrieved successfully',
            'data' => [
                'userDetail' => $userDetail,
                'customFields' => $customFields,
                'agency' => $agency,
                'countries' => $countries,
            ]
        ], 200);
    } catch (\Exception $e) {
        // Handle errors
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve request data',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function agentRequestPost(Request $request)
    {
        $userDetail = auth()->user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $userDetail->id,
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'google_profile_link' => 'nullable|url',
            'website' => 'nullable|url',
            'identity_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $userDetail->name = $request->name;
        $userDetail->email = $request->email;
        $userDetail->phone = $request->phone;
        $userDetail->approved_status = '1';
        $userDetail->save();

        $fileNameToStore = null;

        if ($request->hasFile('identity_document')) {
            $filenameWithExt = $request->file('identity_document')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('identity_document')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $settings = Utility::getStorageSetting();
            $dir = ($settings['storage_setting'] === 'local') ? '/uploads/identity_document/' : 'uploads/identity_document/';

            $image_path = $dir . $userDetail['identity_document'];
            if (File::exists($image_path)) {
                File::delete($image_path);
            }

            $path = Utility::upload_file($request, 'identity_document', $fileNameToStore, $dir, []);

            if ($path['flag'] !== 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => $path['msg']
                ], 400);
            }
        }

        $agency = Agency::where('user_id', auth()->id())->first();

        if ($agency) {
            $agency->organization_name = $request->company_name;
            $agency->organization_email = $request->email;
            $agency->billing_street = $request->address;
            $agency->billing_country = $request->country;
            $agency->google_profile_link = $request->google_profile_link;
            $agency->website = $request->website;
            $agency->identity_document = $fileNameToStore ?? $agency->identity_document;
            $agency->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Your request for the agent portal has been submitted.',
                'data' => [
                    'user' => $userDetail,
                    'agency' => $agency
                ]
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Agency not found.'
            ], 404);
        }
    }



}
