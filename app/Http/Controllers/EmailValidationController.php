<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EmailValidationService;

class EmailValidationController extends Controller
{
    protected EmailValidationService $emailValidator;

    public function __construct(EmailValidationService $emailValidator)
    {
        $this->emailValidator = $emailValidator;
    }

    /**
     * Validate an e-mail address using mailboxlayer.
     * Returns the full mailboxlayer payload.
     *
     * POST /api/validate-email
     * Body: { "email": "user@example.com" }
     */
    public function validate(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
        ]);

        $result = $this->emailValidator->validate($request->email);

        if (is_null($result)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email validation service unavailable. Check MAILBOXLAYER_KEY in .env or see Laravel logs.',
            ], 503);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ], 200);
    }

    /**
     * Quick boolean email validity check.
     *
     * GET /api/is-email-valid?email=user@example.com
     */
    public function isValid(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
        ]);

        $valid = $this->emailValidator->isValid($request->email);

        return response()->json([
            'status' => 'success',
            'email'  => $request->email,
            'valid'  => $valid,
        ], 200);
    }
}
