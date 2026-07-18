<?php

use App\Plugins\Vibration;
use App\Plugins\VibrationPreset;
use Livewire\Component;

new class extends Component
{
    public bool $supportsHaptics = false;

    public string $statusMessage = '';

    public int $duration = 100;

    public float $intensity = 0.5;

    public float $sharpness = 0.5;

    /** @var list<array{label: string, intensity: float}> */
    public array $intensityLevels = [
        ['label' => 'Very Light', 'intensity' => 0.1],
        ['label' => 'Light', 'intensity' => 0.3],
        ['label' => 'Medium', 'intensity' => 0.5],
        ['label' => 'Strong', 'intensity' => 0.75],
        ['label' => 'Max', 'intensity' => 1.0],
    ];

    /** @var list<array{label: string, duration: int}> */
    public array $durationLevels = [
        ['label' => '50ms', 'duration' => 50],
        ['label' => '100ms', 'duration' => 100],
        ['label' => '200ms', 'duration' => 200],
        ['label' => '500ms', 'duration' => 500],
        ['label' => '1s', 'duration' => 1000],
    ];

    public function mount(): void
    {
        $this->supportsHaptics = Vibration::hasHaptics();
        $this->statusMessage = $this->supportsHaptics
            ? 'Device reports haptic support. Tap a level to test.'
            : 'Haptics not detected (browser, simulator without engine, or bridge offline).';
    }

    public function setIntensity(float $intensity): void
    {
        $this->intensity = max(0.0, min(1.0, $intensity));
    }

    public function setDuration(int $duration): void
    {
        $this->duration = max(1, min(5000, $duration));
    }

    public function setSharpness(float $sharpness): void
    {
        $this->sharpness = max(0.0, min(1.0, $sharpness));
    }

    public function vibrateAtLevel(float $intensity): void
    {
        $this->setIntensity($intensity);
        $this->playCustom();
    }

    public function vibrateDuration(int $duration): void
    {
        $this->setDuration($duration);
        $this->playCustom();
    }

    public function playCustom(): void
    {
        $ok = Vibration::vibrate(
            duration: $this->duration,
            intensity: $this->intensity,
            sharpness: $this->sharpness,
        );

        $this->statusMessage = $ok
            ? sprintf(
                'Vibrated %dms · intensity %.0f%% · sharpness %.0f%%',
                $this->duration,
                $this->intensity * 100,
                $this->sharpness * 100,
            )
            : 'Vibration failed or bridge unavailable.';
    }

    public function playPreset(string $preset): void
    {
        try {
            $ok = Vibration::preset($preset)->play();
            $this->statusMessage = $ok
                ? "Preset “{$preset}” played."
                : "Preset “{$preset}” failed or bridge unavailable.";
        } catch (\InvalidArgumentException $e) {
            $this->statusMessage = $e->getMessage();
        }
    }

    public function playPatternDemo(): void
    {
        $ok = Vibration::pattern()
            ->vibrate(60, 0.9, 0.8)
            ->pause(100)
            ->vibrate(80, 1.0, 0.6)
            ->pause(400)
            ->play();

        $this->statusMessage = $ok
            ? 'Heartbeat pattern played.'
            : 'Pattern failed or bridge unavailable.';
    }

    public function cancel(): void
    {
        $ok = Vibration::cancelVibration();
        $this->statusMessage = $ok
            ? 'Vibration cancelled.'
            : 'Cancel failed or bridge unavailable.';
    }

    /**
     * @return list<string>
     */
    public function presetNames(): array
    {
        return array_map(
            fn (VibrationPreset $preset) => $preset->value,
            VibrationPreset::cases(),
        );
    }
};
