<?php

namespace App\Plugins;

class VibrationPatternBuilder
{
    /**
     * @param  list<array{type: string, duration: int, intensity?: float, sharpness?: float}>  $steps
     */
    public function __construct(private array $steps = []) {}

    public function vibrate(int $duration, float $intensity, ?float $sharpness = null): self
    {
        $step = [
            'type' => 'vibrate',
            'duration' => max(1, min(5000, $duration)),
            'intensity' => max(0.0, min(1.0, $intensity)),
        ];

        if ($sharpness !== null) {
            $step['sharpness'] = max(0.0, min(1.0, $sharpness));
        }

        $this->steps[] = $step;

        return $this;
    }

    public function pause(int $duration): self
    {
        $this->steps[] = [
            'type' => 'pause',
            'duration' => max(0, min(5000, $duration)),
        ];

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    public function fromArray(array $steps): self
    {
        foreach ($steps as $step) {
            if (isset($step['pause'])) {
                $this->pause((int) $step['pause']);

                continue;
            }

            if (($step['type'] ?? null) === 'pause') {
                $this->pause((int) ($step['duration'] ?? 0));

                continue;
            }

            $duration = (int) ($step['duration'] ?? 0);
            $intensity = (float) ($step['intensity'] ?? 0.5);
            $sharpness = array_key_exists('sharpness', $step) ? (float) $step['sharpness'] : null;

            if ($duration > 0) {
                $this->vibrate($duration, $intensity, $sharpness);
            }
        }

        return $this;
    }

    /**
     * @return list<array{type: string, duration: int, intensity?: float, sharpness?: float}>
     */
    public function toArray(): array
    {
        return $this->steps;
    }

    public function play(): bool
    {
        return Vibration::playPattern($this->steps);
    }
}
