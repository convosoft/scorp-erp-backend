<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/assignRole', function () {
    $user = \App\Models\User::find(3324); // Replace 1 with the actual user ID
     $user->assignRole('super admin');
 });
Route::get('/application', function () {
    // Prefetch data
    $universities = Illuminate\Support\Facades\DB::table('universities')->get()->keyBy('id');
    $countries = Illuminate\Support\Facades\DB::table('countries')->get()->keyBy('name');

    // Process applications in chunks
    Illuminate\Support\Facades\DB::table('deal_applications')->chunkById(100, function ($applications) use ($universities, $countries) {
        foreach ($applications as $application) {
            if ($application->university_id && empty($application->country_id)) {
                $university = $universities->get($application->university_id);

                if ($university) {
                    $country = $countries->firstWhere('name', $university->country);

                    if ($country && $application->country_id !== $country->id) {
                        Illuminate\Support\Facades\DB::table('deal_applications')
                            ->where('id', $application->id)
                            ->update(['country_id' => $country->id]);
                    }
                }
            }
        }
    });
});
