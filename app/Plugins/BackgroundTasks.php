<?php

namespace App\Plugins;

/**
 * Custom background-task registry inspired by nativephp/mobile-background-tasks.
 *
 * Stores scheduled task definitions on-device via the native bridge so they
 * can be created, listed, updated, and deleted from PHP. Intervals follow the
 * official plugin guidance (minimum 15 minutes on mobile schedulers).
 *
 * Bridge methods:
 *   BackgroundTasks.Create|Get|List|Update|Delete|Sync|RunNow
 *
 * Native layers schedule enabled tasks with the OS (WorkManager / BGTaskScheduler)
 * and execute the stored artisan `command` when the OS fires a job.
 *
 * @see https://nativephp.com/plugins/nativephp/mobile-background-tasks
 */
class BackgroundTasks
{
    public const MIN_INTERVAL_MINUTES = 15;

    /**
     * Create a new background task definition.
     *
     * @param  array{
     *     name: string,
     *     command?: string,
     *     intervalMinutes?: int,
     *     enabled?: bool,
     *     longRunning?: bool,
     *     constraints?: array<string, bool>
     * }  $attributes
     * @return array<string, mixed>|null
     */
    public static function create(array $attributes): ?array
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $payload = self::normalizeAttributes($attributes, requireName: true);
        if ($payload === null) {
            return null;
        }

        $resultJson = nativephp_call('BackgroundTasks.Create', json_encode($payload));

        return self::decodeTask($resultJson);
    }

    /**
     * Get a single task by id.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $id): ?array
    {
        if (! function_exists('nativephp_call') || $id === '') {
            return null;
        }

        $resultJson = nativephp_call('BackgroundTasks.Get', json_encode(['id' => $id]));

        return self::decodeTask($resultJson);
    }

    /**
     * List all registered background tasks.
     *
     * @return list<array<string, mixed>>
     */
    public static function list(): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        $resultJson = nativephp_call('BackgroundTasks.List', '{}');

        if (empty($resultJson)) {
            return [];
        }

        $result = json_decode($resultJson, true);
        if (! is_array($result) || ! ($result['success'] ?? false)) {
            return [];
        }

        $tasks = $result['tasks'] ?? [];

        return is_array($tasks) ? array_values($tasks) : [];
    }

    /**
     * Update an existing task by id.
     *
     * @param  array{
     *     name?: string,
     *     command?: string,
     *     intervalMinutes?: int,
     *     enabled?: bool,
     *     longRunning?: bool,
     *     constraints?: array<string, bool>
     * }  $attributes
     * @return array<string, mixed>|null
     */
    public static function update(string $id, array $attributes): ?array
    {
        if (! function_exists('nativephp_call') || $id === '') {
            return null;
        }

        $payload = self::normalizeAttributes($attributes, requireName: false);
        if ($payload === null) {
            $payload = [];
        }

        $payload['id'] = $id;

        $resultJson = nativephp_call('BackgroundTasks.Update', json_encode($payload));

        return self::decodeTask($resultJson);
    }

    /**
     * Delete a task by id (also cancels OS scheduling).
     */
    public static function delete(string $id): bool
    {
        if (! function_exists('nativephp_call') || $id === '') {
            return false;
        }

        $resultJson = nativephp_call('BackgroundTasks.Delete', json_encode(['id' => $id]));

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }

    /**
     * Re-register all enabled tasks with the OS scheduler.
     * Call on app boot if you manage tasks outside this plugin's CRUD helpers.
     */
    public static function sync(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $resultJson = nativephp_call('BackgroundTasks.Sync', '{}');

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }

    /**
     * Immediately run one task (by id) or all enabled tasks, bypassing intervals/constraints.
     * Useful for development/testing (similar to the official BackgroundTasks::runNow()).
     *
     * @return array{success: bool, results?: list<array<string, mixed>>, error?: string}
     */
    public static function runNow(?string $id = null): array
    {
        if (! function_exists('nativephp_call')) {
            return ['success' => false, 'error' => 'NativePHP bridge not available'];
        }

        $payload = $id !== null && $id !== '' ? ['id' => $id] : [];
        $resultJson = nativephp_call('BackgroundTasks.RunNow', json_encode($payload));

        if (empty($resultJson)) {
            return ['success' => false, 'error' => 'No response from native bridge'];
        }

        $result = json_decode($resultJson, true);

        return is_array($result) ? $result : ['success' => false, 'error' => 'Invalid JSON response'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    private static function normalizeAttributes(array $attributes, bool $requireName): ?array
    {
        $name = isset($attributes['name']) ? trim((string) $attributes['name']) : null;

        if ($requireName && ($name === null || $name === '')) {
            return null;
        }

        $payload = [];

        if ($name !== null && $name !== '') {
            $payload['name'] = $name;
            $payload['command'] = trim((string) ($attributes['command'] ?? $name));
        } elseif (isset($attributes['command'])) {
            $payload['command'] = trim((string) $attributes['command']);
        }

        if (array_key_exists('intervalMinutes', $attributes)) {
            $payload['intervalMinutes'] = max(
                self::MIN_INTERVAL_MINUTES,
                (int) $attributes['intervalMinutes']
            );
        } elseif ($requireName) {
            $payload['intervalMinutes'] = self::MIN_INTERVAL_MINUTES;
        }

        if (array_key_exists('enabled', $attributes)) {
            $payload['enabled'] = (bool) $attributes['enabled'];
        } elseif ($requireName) {
            $payload['enabled'] = true;
        }

        if (array_key_exists('longRunning', $attributes)) {
            $payload['longRunning'] = (bool) $attributes['longRunning'];
        } elseif ($requireName) {
            $payload['longRunning'] = false;
        }

        if (isset($attributes['constraints']) && is_array($attributes['constraints'])) {
            $payload['constraints'] = self::normalizeConstraints($attributes['constraints']);
        } elseif ($requireName) {
            $payload['constraints'] = self::normalizeConstraints([]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $constraints
     * @return array<string, bool>
     */
    private static function normalizeConstraints(array $constraints): array
    {
        $keys = [
            'onAnyNetwork',
            'onWifi',
            'whileCharging',
            'whenBatteryNotLow',
            'whenStorageNotLow',
            'whenIdle',
        ];

        $normalized = [];
        foreach ($keys as $key) {
            $normalized[$key] = (bool) ($constraints[$key] ?? false);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeTask(?string $resultJson): ?array
    {
        if (empty($resultJson)) {
            return null;
        }

        $result = json_decode($resultJson, true);
        if (! is_array($result) || ! ($result['success'] ?? false)) {
            return null;
        }

        $task = $result['task'] ?? null;

        return is_array($task) ? $task : null;
    }
}
