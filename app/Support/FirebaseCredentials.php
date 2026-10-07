<?php

namespace App\Support;

use InvalidArgumentException;
use JsonException;

class FirebaseCredentials
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $credentials = config('services.firebase.credentials');
        $json = config('services.firebase.service_account_json');

        if (is_string($credentials) && trim($credentials) !== '') {
            $path = trim($credentials);
            $path = str_starts_with($path, DIRECTORY_SEPARATOR)
                ? $path
                : base_path($path);

            if (! is_file($path) || ! is_readable($path)) {
                throw new InvalidArgumentException('Firebase credential file is missing or unreadable.');
            }

            $json = file_get_contents($path);
            if ($json === false) {
                throw new InvalidArgumentException('Firebase credential file could not be read.');
            }
        }

        if (! is_string($json) || trim($json) === '') {
            throw new InvalidArgumentException('Firebase credentials are not configured.');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Firebase credentials are not valid JSON.', previous: $exception);
        }

        if (
            ! is_array($decoded)
            || empty($decoded['project_id'])
            || empty($decoded['client_email'])
            || empty($decoded['private_key'])
            || $decoded['project_id'] !== config('services.firebase.project_id')
        ) {
            throw new InvalidArgumentException('Firebase credentials are incomplete or use a different project.');
        }

        return $decoded;
    }
}
