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
        // Retrieve the key from config/services.php
        $this->apiKey = config('services.mailboxlayer.key');
        $this->http   = new Client([
            'base_uri' => 'https://apilayer.net/api/',
            'timeout'  => 5.0,
        ]);
    }

    /**
     * Validate an email address via mailboxlayer.
     *
     * @param string $email
     * @return array|null Decoded JSON response or null on failure.
     */
    public function validate(string $email): ?array
    {
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
            return $data;
        } catch (GuzzleException $e) {
            Log::error('Mailboxlayer validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convenience helper that returns a boolean indicating if the address looks valid.
     */
    public function isValid(string $email): bool
    {
        $result = $this->validate($email);
        if (!$result) {
            return false;
        }
        return $result['format_valid']
            && $result['mx_found']
            && $result['smtp_check']
            && ($result['score'] ?? 0) >= 0.8
            && empty($result['disposable'])
            && empty($result['role']);
    }
}

?>
