<?php

namespace App\Plugins;

/**
 * Custom haptic vibration plugin inspired by jvdluk/pro-vibration.
 *
 * Bridge methods: Vibration.Vibrate, Vibration.HasHaptics,
 * Vibration.Cancel, Vibration.PlayPattern.
 */
class Vibration
{
    /**
     * Trigger a single haptic vibration.
     *
     * @param  int  $duration  Duration in milliseconds (1–5000)
     * @param  float  $intensity  Strength 0.0–1.0
     * @param  float|null  $sharpness  Sharpness 0.0–1.0 (iOS only; ignored on Android)
     */
    public static function vibrate(int $duration, float $intensity, ?float $sharpness = null): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $duration = max(1, min(5000, $duration));
        $intensity = max(0.0, min(1.0, $intensity));

        $payload = [
            'duration' => $duration,
            'intensity' => $intensity,
        ];

        if ($sharpness !== null) {
            $payload['sharpness'] = max(0.0, min(1.0, $sharpness));
        }

        $resultJson = nativephp_call('Vibration.Vibrate', json_encode($payload));

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }

    /**
     * Whether the device supports haptic feedback.
     */
    public static function hasHaptics(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $resultJson = nativephp_call('Vibration.HasHaptics', '{}');

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['supported'] ?? $result['success'] ?? false);
    }

    /**
     * Cancel any in-progress vibration or pattern.
     */
    public static function cancelVibration(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $resultJson = nativephp_call('Vibration.Cancel', '{}');

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }

    /**
     * Start a fluent / array-based pattern builder.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    public static function pattern(array $steps = []): VibrationPatternBuilder
    {
        $builder = new VibrationPatternBuilder;

        if ($steps !== []) {
            $builder->fromArray($steps);
        }

        return $builder;
    }

    /**
     * Build a pattern from a named preset (string or enum).
     */
    public static function preset(string|VibrationPreset $preset): VibrationPatternBuilder
    {
        if (is_string($preset)) {
            $preset = VibrationPreset::tryFrom($preset)
                ?? throw new \InvalidArgumentException("Unknown vibration preset [{$preset}].");
        }

        return (new VibrationPatternBuilder)->fromArray($preset->steps());
    }

    /**
     * Execute a pattern of vibrate/pause steps on the device.
     *
     * @param  list<array{type: string, duration: int, intensity?: float, sharpness?: float}>  $steps
     */
    public static function playPattern(array $steps): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        if ($steps === []) {
            return false;
        }

        $resultJson = nativephp_call('Vibration.PlayPattern', json_encode(['steps' => $steps]));

        if (empty($resultJson)) {
            return false;
        }

        $result = json_decode($resultJson, true);

        return (bool) ($result['success'] ?? false);
    }
}
