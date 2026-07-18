<?php

namespace App\Plugins;

enum VibrationPreset: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Notification = 'notification';
    case DoubleClick = 'double_click';

    /**
     * Built-in pattern steps for this preset.
     *
     * @return list<array{type: string, duration: int, intensity?: float, sharpness?: float}>
     */
    public function steps(): array
    {
        return match ($this) {
            self::Success => [
                ['type' => 'vibrate', 'duration' => 40, 'intensity' => 0.5, 'sharpness' => 0.7],
                ['type' => 'pause', 'duration' => 60],
                ['type' => 'vibrate', 'duration' => 80, 'intensity' => 0.8, 'sharpness' => 0.5],
            ],
            self::Error => [
                ['type' => 'vibrate', 'duration' => 80, 'intensity' => 1.0, 'sharpness' => 0.3],
                ['type' => 'pause', 'duration' => 50],
                ['type' => 'vibrate', 'duration' => 80, 'intensity' => 1.0, 'sharpness' => 0.3],
                ['type' => 'pause', 'duration' => 50],
                ['type' => 'vibrate', 'duration' => 120, 'intensity' => 1.0, 'sharpness' => 0.2],
            ],
            self::Warning => [
                ['type' => 'vibrate', 'duration' => 120, 'intensity' => 0.7, 'sharpness' => 0.4],
                ['type' => 'pause', 'duration' => 80],
                ['type' => 'vibrate', 'duration' => 80, 'intensity' => 0.4, 'sharpness' => 0.3],
            ],
            self::Notification => [
                ['type' => 'vibrate', 'duration' => 50, 'intensity' => 0.5, 'sharpness' => 0.6],
            ],
            self::DoubleClick => [
                ['type' => 'vibrate', 'duration' => 35, 'intensity' => 0.6, 'sharpness' => 0.8],
                ['type' => 'pause', 'duration' => 40],
                ['type' => 'vibrate', 'duration' => 35, 'intensity' => 0.6, 'sharpness' => 0.8],
            ],
        };
    }
}
