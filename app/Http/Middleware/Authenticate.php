<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Force JSON response for API routes
        if ($request->is('api/*')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Token not found or invalid. Please authenticate.'
            ], 401));
        }

        // Default behavior for web routes
        return route('login');
    }
}
