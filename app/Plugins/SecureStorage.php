<?php

namespace App\Plugins;

class SecureStorage
{
    /**
     * Store a key-value pair securely.
     */
    public static function set(string $key, string $value): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $payload = json_encode(['key' => $key, 'value' => $value]);
        $resultJson = nativephp_call('SecureStorage.Set', $payload);

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }

    /**
     * Retrieve a stored value securely by its key.
     */
    public static function get(string $key): ?string
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $payload = json_encode(['key' => $key]);
        $resultJson = nativephp_call('SecureStorage.Get', $payload);

        if (empty($resultJson)) {
            return null;
        }

        $result = json_decode($resultJson, true);
        if ($result['success'] ?? false) {
            return isset($result['value']) && $result['value'] !== '' ? (string) $result['value'] : null;
        }

        return null;
    }

    /**
     * Delete a stored key-value pair.
     */
    public static function delete(string $key): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $payload = json_encode(['key' => $key]);
        $resultJson = nativephp_call('SecureStorage.Delete', $payload);

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }
}
