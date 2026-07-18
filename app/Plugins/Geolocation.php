<?php

namespace App\Plugins;

class Geolocation
{
    /**
     * Get the current GPS location from the device.
     *
     * @return array{success: bool, latitude?: float, longitude?: float, accuracy?: float, error?: string}
     */
    public static function getCurrentPosition(): array
    {
        if (! function_exists('nativephp_call')) {
            return ['success' => false, 'error' => 'NativePHP bridge not available'];
        }

        $resultJson = nativephp_call('Geolocation.GetLocation', '{}');
        if (empty($resultJson)) {
            return ['success' => false, 'error' => 'No response from native bridge'];
        }

        $result = json_decode($resultJson, true);
        if (! is_array($result)) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }

        // Handle standard native bridge error payload
        if (($result['status'] ?? null) === 'error') {
            return [
                'success' => false,
                'error' => $result['message'] ?? $result['code'] ?? 'Unknown native bridge error',
            ];
        }

        // Ensure normalized output format
        if (! isset($result['success'])) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Invalid bridge response structure',
            ];
        }

        return $result;
    }
}
