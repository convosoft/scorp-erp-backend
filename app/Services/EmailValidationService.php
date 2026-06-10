<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class EmailValidationService
{
    private string $apiKey;
    private Client $http;

    public function __construct()
    {
        // Retrieved from config/services.php → env('MAILBOXLAYER_KEY')
        $this->apiKey = config('services.mailboxlayer.key');

        $this->http = new Client([
            'base_uri' => 'https://apilayer.net/api/',
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Validate an email address via mailboxlayer.
     *
     * Returns the decoded JSON payload on success, or null on HTTP/network failure.
     * Note: mailboxlayer always returns HTTP 200 – check the 'error' key in the response.
     *
     * @param  string     $email
     * @return array|null
     */
    public function validate(string $email): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('Mailboxlayer: MAILBOXLAYER_KEY is not set in .env');
            return null;
        }

        try {
            $response = $this->http->request('GET', 'check', [
                'query' => [
                    'access_key' => $this->apiKey,
                    'email'      => $email,
                    'smtp'       => 1,
                    'format'     => 'json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // ✅ mailboxlayer returns HTTP 200 even on error – check for API-level errors
            if (!empty($data['error'])) {
                Log::error('Mailboxlayer API error', [
                    'code'    => $data['error']['code'] ?? 'unknown',
                    'type'    => $data['error']['type'] ?? 'unknown',
                    'info'    => $data['error']['info'] ?? '',
                ]);
                return null;
            }

            return $data;

        } catch (GuzzleException $e) {
            Log::error('Mailboxlayer HTTP request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Quick boolean check – returns true only if the address passes all key checks.
     *
     * @param  string $email
     * @return bool
     */
    public function isValid(string $email): bool
    {
        $result = $this->validate($email);

        if (!$result) {
            return false;
        }

        return !empty($result['format_valid'])
            && !empty($result['mx_found'])
            && !empty($result['smtp_check'])
            && (($result['score'] ?? 0) >= 0.8)
            && empty($result['disposable'])
            && empty($result['role']);
    }
}
