<?php

namespace App\Plugins;

/**
 * Local (on-device) notifications for iOS and Android.
 *
 * Shows dismissible system notifications in the notification center / shade —
 * not in-app dialogs. Works offline; no FCM/APNs push server required.
 *
 * Bridge: LocalNotifications.RequestPermission|HasPermission|Show|Schedule|Cancel|CancelAll
 */
class LocalNotifications
{
    /**
     * Request notification permission from the user (required on iOS and Android 13+).
     */
    public static function requestPermission(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $resultJson = nativephp_call('LocalNotifications.RequestPermission', '{}');

        return self::boolResult($resultJson, 'granted');
    }

    /**
     * Whether the app currently has notification permission.
     */
    public static function hasPermission(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $resultJson = nativephp_call('LocalNotifications.HasPermission', '{}');

        return self::boolResult($resultJson, 'granted');
    }

    /**
     * Show a notification immediately.
     *
     * @param  string  $title  Notification title
     * @param  string  $body  Notification body
     * @param  string|null  $id  Optional stable id (for cancel / replace). Auto-generated if null.
     * @return array{success: bool, id?: string, error?: string}
     */
    public static function show(string $title, string $body, ?string $id = null): array
    {
        if (! function_exists('nativephp_call')) {
            return ['success' => false, 'error' => 'NativePHP bridge not available'];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'id' => $id ?: self::generateId(),
        ];

        $resultJson = nativephp_call('LocalNotifications.Show', json_encode($payload));

        return self::decode($resultJson);
    }

    /**
     * Schedule a notification after a delay (seconds).
     *
     * @return array{success: bool, id?: string, error?: string}
     */
    public static function schedule(string $title, string $body, int $delaySeconds, ?string $id = null): array
    {
        if (! function_exists('nativephp_call')) {
            return ['success' => false, 'error' => 'NativePHP bridge not available'];
        }

        $delaySeconds = max(1, min(86400 * 30, $delaySeconds));

        $payload = [
            'title' => $title,
            'body' => $body,
            'delaySeconds' => $delaySeconds,
            'id' => $id ?: self::generateId(),
        ];

        $resultJson = nativephp_call('LocalNotifications.Schedule', json_encode($payload));

        return self::decode($resultJson);
    }

    /**
     * Cancel a pending or displayed notification by id.
     */
    public static function cancel(string $id): bool
    {
        if (! function_exists('nativephp_call') || $id === '') {
            return false;
        }

        $resultJson = nativephp_call('LocalNotifications.Cancel', json_encode(['id' => $id]));

        return self::boolResult($resultJson, 'success');
    }

    /**
     * Cancel all notifications scheduled or shown by this plugin.
     */
    public static function cancelAll(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $resultJson = nativephp_call('LocalNotifications.CancelAll', '{}');

        return self::boolResult($resultJson, 'success');
    }

    private static function generateId(): string
    {
        return 'ln_'.bin2hex(random_bytes(8));
    }

    /**
     * @return array{success: bool, id?: string, error?: string, granted?: bool}
     */
    private static function decode(?string $resultJson): array
    {
        if (empty($resultJson)) {
            return ['success' => false, 'error' => 'No response from native bridge'];
        }

        $result = json_decode($resultJson, true);
        if (! is_array($result)) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }

        // Normalize: native may return success with NSNull error field
        if (array_key_exists('error', $result) && ($result['error'] === null || $result['error'] === '')) {
            unset($result['error']);
        }

        if (($result['success'] ?? false) === true || ($result['success'] ?? null) === 1) {
            $result['success'] = true;
        }

        return $result;
    }

    private static function boolResult(?string $resultJson, string $key): bool
    {
        $result = self::decode($resultJson);

        if (array_key_exists($key, $result)) {
            return (bool) $result[$key];
        }

        return (bool) ($result['success'] ?? false);
    }
}
