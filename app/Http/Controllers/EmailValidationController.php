<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EmailValidationService;

class EmailValidationController extends Controller
{
    /**
     * @var EmailValidationService
     */
    protected $emailValidator;

    public function __construct(EmailValidationService $emailValidator)
    {
        $this->emailValidator = $emailValidator;
    }

    /**
     * Validate an e‑mail address using mailboxlayer and return the full payload.
     *
     * POST /api/validate-email { "email": "user@example.com" }
     */
    public function validate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $result = $this->emailValidator->validate($request->email);

        if (is_null($result)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation service unavailable',
            ], 503);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ], 200);
    }

    /**
     * Simple boolean check – returns true if the address looks valid.
     *
     * GET /api/is-email-valid?email=user@example.com
     */
    public function isValid(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $valid = $this->emailValidator->isValid($request->email);

        return response()->json([
            'status' => 'success',
            'valid'  => $valid,
        ], 200);
    }
}
?>
