<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // ✅ Idle Timeout Logic (15 minutes)
        // PersonalAccessToken::retrieved(function ($token) {
        //     if ($token->last_used_at) {
        //         $idleMinutes = now()->diffInMinutes($token->last_used_at);

        //         if ($idleMinutes > 15) {
        //             // delete expired token
        //             $token->delete();
        //         }
        //     }
        // });
    }
}
